<?php

namespace App\Services\Approvals;

use App\Enums\ApproverRole;
use App\Enums\ChainRole;
use App\Models\Approval;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Higher-authority signatures on paper, at SLD's (final) step. SLD chooses who must sign, exports the official
 * document, has it signed, uploads the signed scan and ticks each signatory. Only when every one is ticked and
 * the scan is on file can SLD give the final approval (ApprovalEngine::approve checks this).
 */
class PaperSignoff
{
    public function __construct(private readonly ApprovalEngine $engine, private readonly AuditLogger $audit) {}

    /** @return list<string> the higher authorities SLD can ask to sign, with the current office holder */
    public function options(): array
    {
        return array_map(function (ApproverRole $role): string {
            $name = Staff::where('approver_role', $role->value)->where('active', true)->value('name');

            return $name ? "{$role->label()} · {$name}" : $role->label();
        }, ApproverRole::cases());
    }

    /** @param list<string> $signers */
    public function start(Approval $approval, User $sld, array $signers): void
    {
        $this->assertSldStep($approval, $sld);
        $signers = array_values(array_unique(array_filter(array_map('trim', $signers))));
        if ($signers === []) {
            throw new DomainException('Choose at least one person who signs on paper.');
        }
        $approval->update(['paper' => ['signers' => array_map(fn (string $s) => ['label' => $s, 'signed' => false], $signers), 'started_at' => now()->toIso8601String(), 'by' => $sld->id]]);
        $this->audit->log('approval.paper_started', $approval->document, ['signers' => $signers]);
        $document = $approval->document;
        if ($creator = $document->creator) {
            $creator->notify(new DocumentNotification("{$document->type->label()}: with higher authorities",
                'SLD has sent it for signature by '.implode(', ', $signers).'. You are told when it is approved.', route('approvals.show', $approval)));
        }
    }

    /** @param list<string> $signed the signatories ticked as signed on the scan */
    public function record(Approval $approval, User $sld, ?UploadedFile $scan, array $signed): void
    {
        $this->assertSldStep($approval, $sld);
        $paper = $approval->paper ?? throw new DomainException('Choose who signs on paper first.');
        if ($scan) {
            if ($approval->paper_scan_path) {
                Storage::disk('local')->delete($approval->paper_scan_path);
            }
            $approval->paper_scan_path = $scan->store("paper-signoffs/{$approval->document_id}", 'local');
        }
        $paper['signers'] = array_map(fn (array $s) => [...$s, 'signed' => in_array($s['label'], $signed, true)], $paper['signers']);
        $approval->paper = $paper;
        $approval->save();
        $this->audit->log('approval.paper_recorded', $approval->document, ['signed' => $signed, 'scan' => (bool) $scan]);
    }

    /** SLD decides no paper signatures are needed after all. */
    public function cancel(Approval $approval, User $sld): void
    {
        $this->assertSldStep($approval, $sld);
        if ($approval->paper_scan_path) {
            Storage::disk('local')->delete($approval->paper_scan_path);
        }
        $approval->update(['paper' => null, 'paper_scan_path' => null]);
    }

    /** Null when no paper signatures were asked for, else what still stops the final approval. */
    public static function blocker(Approval $approval): ?string
    {
        if (! $approval->paper) {
            return null;
        }
        $missing = collect($approval->paper['signers'])->where('signed', false)->pluck('label');
        if ($missing->isNotEmpty()) {
            return 'Still to sign on paper: '.$missing->implode(', ').'.';
        }

        return $approval->paper_scan_path ? null : 'Upload the signed scan first.';
    }

    /** @return array<string, mixed>|null */
    public function present(Approval $approval): ?array
    {
        return $approval->paper ? [
            'signers' => $approval->paper['signers'],
            'scan_url' => $approval->paper_scan_path ? route('approvals.paper.scan', $approval) : null,
            'complete' => self::blocker($approval) === null,
        ] : null;
    }

    private function assertSldStep(Approval $approval, User $user): void
    {
        if ($approval->role !== ChainRole::SldOfficer || ! $this->engine->canAct($approval, $user)) {
            throw new DomainException('Only SLD, at its own step, arranges signatures on paper.');
        }
    }
}
