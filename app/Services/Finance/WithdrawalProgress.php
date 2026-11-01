<?php

namespace App\Services\Finance;

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Approvals\ApproverResolver;
use App\Services\AuditLogger;
use App\Services\Meetings\Meetings;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * What happens after the Treasurer, Advisor and SLD have signed a fund withdrawal:
 *   awaiting_finance → SLD exports the 07 form for Finance to sign, then uploads the signed copy
 *   awaiting_bankin  → Finance transfers the money; the Treasurer uploads proof it arrived
 *   bankin_uploaded  → SLD checks the proof and confirms (or sends it back)
 *   completed        → only now is the withdrawal recorded in the club ledger, with its transaction ID and proof.
 * Everyone involved is told at every step.
 */
class WithdrawalProgress
{
    public const STAGES = [
        'awaiting_finance' => 'Waiting for Finance to sign',
        'awaiting_bankin' => 'Waiting for the bank-in',
        'bankin_uploaded' => 'Bank-in proof sent, waiting for SLD',
        'completed' => 'Completed',
    ];

    public function __construct(
        private readonly ApproverResolver $approvers,
        private readonly Meetings $meetings,
        private readonly AuditLogger $audit,
    ) {}

    /** Called when the last signature (SLD) is in. */
    public function approved(Document $document): void
    {
        if ($document->type !== DocumentType::FundWithdrawal || $document->finance_stage !== null) {
            return;
        }
        $document->update(['finance_stage' => 'awaiting_finance']);
        $this->tell($document, 'Fund withdrawal approved: next, Finance signs', 'SLD exports the form for Finance to sign and uploads the signed copy.');
    }

    public function financeSigned(Document $document, User $sld, UploadedFile $file): void
    {
        $this->assertStage($document, 'awaiting_finance');
        $this->assertSld($sld);
        $document->update(['finance_stage' => 'awaiting_bankin', 'finance_file_path' => $file->store("withdrawals/{$document->club_id}", 'local'), 'finance_note' => null]);
        $this->audit->log('withdrawal.finance_signed', $document, ['by' => $sld->id]);
        $this->tell($document, 'Finance signed your fund withdrawal', 'Finance will bank in RM '.$this->rm($document).'. Treasurer: once it arrives, upload the bank-in receipt.');
    }

    public function bankIn(Document $document, User $treasurer, UploadedFile $proof, ?string $reference): void
    {
        $this->assertStage($document, 'awaiting_bankin');
        if (! $this->approvers->canAct($treasurer, $document, ChainRole::Treasurer)) {
            throw new DomainException('Only the Treasurer who signed for this withdrawal uploads the bank-in proof.');
        }
        $document->update(['finance_stage' => 'bankin_uploaded', 'bankin_path' => $proof->store("withdrawals/{$document->club_id}", 'local'),
            'bankin_reference' => $reference ? mb_substr(trim($reference), 0, 100) : null, 'finance_note' => null]);
        $this->audit->log('withdrawal.bankin_uploaded', $document, ['by' => $treasurer->id]);
        $this->tell($document, 'Bank-in proof uploaded', "{$treasurer->name} uploaded proof that RM {$this->rm($document)} arrived. SLD: check and confirm.");
    }

    /** SLD confirms the money arrived: recorded in the ledger under the event (or the club), with the proof. */
    public function complete(Document $document, User $sld): LedgerEntry
    {
        $this->assertStage($document, 'bankin_uploaded');
        $this->assertSld($sld);
        $entry = DB::transaction(function () use ($document, $sld): LedgerEntry {
            $copy = "receipts/{$document->club_id}/".basename((string) $document->bankin_path);
            Storage::disk('local')->copy((string) $document->bankin_path, $copy);
            $receipt = Receipt::create(['club_id' => $document->club_id, 'path' => $copy, 'original_name' => 'Bank-in proof.'.pathinfo($copy, PATHINFO_EXTENSION), 'uploaded_by' => $sld->id]);
            $d = $document->data();
            $entry = $document->club->ledger()->create([
                'entry_date' => today(), 'expense_sen' => (int) ($d['total_sen'] ?? 0), 'category' => 'withdrawal', 'event_id' => $document->event_id, 'method' => 'transfer',
                'description' => 'Fund withdrawal (07) – '.($document->event ? $document->event->name : ($d['purpose'] ?? 'club')).($document->bankin_reference ? " · bank ref {$document->bankin_reference}" : ''),
                'receipt_id' => $receipt->id, 'handled_by' => $sld->id, 'created_by' => $sld->id,
                'source_type' => $document->getMorphClass(), 'source_id' => $document->id,
            ]);
            $document->update(['finance_stage' => 'completed', 'completed_at' => now()]);

            return $entry;
        });
        $this->audit->log('withdrawal.completed', $document, ['entry_id' => $entry->id, 'order_id' => $entry->order_id]);
        $this->tell($document, 'Fund withdrawal completed', "RM {$this->rm($document)} is recorded in the club finance (transaction {$entry->order_id}).");

        return $entry;
    }

    /** SLD sends the bank-in proof back (wrong file, amount not matching). */
    public function rejectProof(Document $document, User $sld, string $note): void
    {
        $this->assertStage($document, 'bankin_uploaded');
        $this->assertSld($sld);
        $document->update(['finance_stage' => 'awaiting_bankin', 'finance_note' => mb_substr($note, 0, 500)]);
        $this->tell($document, 'Bank-in proof not accepted', $note.' Upload the bank-in receipt again.');
    }

    /** @return Collection<int, User> Treasurer, club committee and advisor, and SLD */
    public function people(Document $document): Collection
    {
        return $this->approvers->candidates($document, ChainRole::Treasurer)
            ->merge($this->meetings->committee($document->club))
            ->merge($this->approvers->candidates($document, ChainRole::SldOfficer))
            ->unique('id')->values();
    }

    private function tell(Document $document, string $title, string $body): void
    {
        $what = $document->event ? "{$document->event->name} (event)" : "{$document->club->name} (club)";
        Notification::send($this->people($document), new DocumentNotification($title, "{$what}: {$body}", route('withdrawals.show', $document)));
    }

    private function assertStage(Document $document, string $stage): void
    {
        if ($document->type !== DocumentType::FundWithdrawal || $document->status !== DocumentStatus::Approved || $document->finance_stage !== $stage) {
            throw new DomainException('This withdrawal is not at that step.');
        }
    }

    private function assertSld(User $user): void
    {
        if (! $user->isSldAdmin()) {
            throw new DomainException('Only SLD can do this step.');
        }
    }

    private function rm(Document $document): string
    {
        return number_format(((int) ($document->data()['total_sen'] ?? 0)) / 100, 2);
    }
}
