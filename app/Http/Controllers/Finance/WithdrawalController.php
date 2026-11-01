<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\ApproverResolver;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\Drafts;
use App\Services\Finance\FundWithdrawals;
use App\Services\Finance\WithdrawalProgress;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fund withdrawals end to end: any committee member fills one in (for an event, or for the club), the Treasurer
 * signs and submits, Advisor and SLD sign, then Finance signs, the money is banked in, the Treasurer uploads proof
 * and SLD confirms. Committee, advisor and SLD can follow every step and open every file.
 */
class WithdrawalController extends Controller
{
    public function __construct(private readonly FundWithdrawals $funds, private readonly WithdrawalProgress $progress, private readonly ApproverResolver $approvers) {}

    public function create(Request $request, Club $club): Response
    {
        $user = $request->user();
        abort_unless($this->canPrepare($user, $club), 403);

        return Inertia::render('Finance/FundRequest', [
            'club' => $club->only('slug', 'name'),
            'events' => $this->eventsFor($user, $club)->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->name, 'date' => $e->starts_at->format('j M Y'), 'lines' => $this->funds->budgetLines($e)])->values(),
            'selectedEvent' => $request->integer('event') ?: null,
            'earliestWeekly' => today()->addDays(FundWithdrawals::WEEKLY_LEAD_DAYS)->toDateString(),
            'clubTreasurer' => $club->roleAssignments()->where('position', 'Treasurer')->latest('id')->with('user')->first()?->user?->only('id', 'name'),
            'me' => $user->id,
            'eventTreasurers' => RoleAssignment::with('user')->where('scope_type', ScopeType::Event)->whereIn('scope_id', $this->eventsFor($user, $club)->pluck('id'))
                ->where('position', 'Treasurer')->get()->mapWithKeys(fn (RoleAssignment $a) => [$a->scope_id => $a->user->only('id', 'name')]),
        ]);
    }

    /** Saves the form; the Treasurer signs it now, or is asked to. */
    public function store(Request $request, Club $club, ApprovalEngine $engine, Drafts $drafts): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canPrepare($user, $club), 403);
        $input = $request->validate([
            'scope' => ['required', 'in:event,club'],
            'event_id' => ['nullable', 'required_if:scope,event', 'integer'],
            'purpose' => ['nullable', 'required_if:scope,club', 'string', 'max:300'],
            'needed_by' => ['nullable', 'required_if:scope,club', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit_sen' => ['required', 'integer', 'min:1', 'max:100000000'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.method' => ['required', 'in:vendor,self'],
            'bank.bank_name' => ['required', 'string', 'max:100'],
            'bank.account_name' => ['required', 'string', 'max:150'],
            'bank.account_number' => ['required', 'string', 'regex:/^[0-9\s-]{6,30}$/'],
            'bank_confirmed' => ['accepted'],
            'signature' => ['nullable', 'string'],
            'signature_strokes' => ['nullable', 'required_with:signature', 'string', 'max:307200'],
        ], ['bank_confirmed.accepted' => 'Confirm the bank details are correct before sending.']);

        try {
            $document = DB::transaction(function () use ($club, $input, $user, $engine, $drafts): Document {
                $data = $this->funds->normalise($club, $input);
                $document = $this->funds->draft($club, $user, $data['event_id'] ? Event::find($data['event_id']) : null);
                $drafts->save($document, $data, $user->id);
                if ($input['signature'] ?? null) {
                    $engine->submit($document, $user, $data, $input['signature'], $input['signature_strokes']);
                }

                return $document;
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        if ($document->fresh()->status === DocumentStatus::Draft) {
            $this->askTreasurer($document, $user);

            return to_route('withdrawals.show', $document)->with('success', 'Saved. The Treasurer has been asked to check and sign it.');
        }

        return to_route('withdrawals.show', $document)->with('success', 'Signed and sent to your Advisor.');
    }

    public function show(Request $request, Document $document, Drafts $drafts, DocumentPresenter $presenter): Response
    {
        $user = $request->user();
        abort_unless($document->type === DocumentType::FundWithdrawal && $this->canFollow($user, $document), 403);
        $document->loadMissing('club', 'event', 'creator');
        $data = $document->status === DocumentStatus::Draft ? $drafts->current($document, []) : $document->data();
        $entry = LedgerEntry::where('source_type', $document->getMorphClass())->where('source_id', $document->id)->first();
        $file = fn (string $kind) => URL::temporarySignedRoute('withdrawals.file', now()->addMinutes(30), [$document, $kind]);

        return Inertia::render('Finance/Withdrawal', [
            'withdrawal' => [
                'id' => $document->id, 'status' => $document->status->value, 'stage' => $document->finance_stage,
                'stage_label' => WithdrawalProgress::STAGES[$document->finance_stage] ?? null,
                'club' => $document->club->only('slug', 'name'), 'event' => $document->event?->only('slug', 'name'),
                'scope' => $data['scope'] ?? ($document->event_id ? 'event' : 'club'), 'purpose' => $data['purpose'] ?? null, 'needed_by' => $data['needed_by'] ?? null,
                'items' => $data['items'] ?? [], 'total_sen' => (int) ($data['total_sen'] ?? 0), 'bank' => $data['bank'] ?? null,
                'prepared_by' => $document->creator?->name, 'created_at' => $document->created_at?->toIso8601String(),
                'bankin_reference' => $document->bankin_reference, 'note' => $document->finance_note,
                'order_id' => $entry?->order_id, 'completed_at' => $document->completed_at?->toIso8601String(),
                'files' => array_filter([
                    'form' => $document->status !== DocumentStatus::Draft ? route('documents.download', [$document, 'pdf']) : null,
                    'finance' => $document->finance_file_path ? $file('finance') : null,
                    'bankin' => $document->bankin_path ? $file('bankin') : null,
                ]),
            ],
            'approval' => $document->approvals()->exists() ? ['progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']] : null,
            'stages' => WithdrawalProgress::STAGES,
            'can' => [
                'sign' => $document->status->isEditable() && $this->approvers->canAct($user, $document, ChainRole::Treasurer),
                'sld' => $user->isSldAdmin(),
                'bankin' => $document->finance_stage === 'awaiting_bankin' && $this->approvers->canAct($user, $document, ChainRole::Treasurer),
            ],
        ]);
    }

    /** The Treasurer checks a saved withdrawal and signs it. */
    public function sign(Request $request, Document $document, ApprovalEngine $engine, Drafts $drafts): RedirectResponse
    {
        $data = $request->validate(['signature' => ['required', 'string'], 'signature_strokes' => ['required', 'string', 'max:307200'], 'bank_confirmed' => ['accepted']],
            ['bank_confirmed.accepted' => 'Confirm the bank details are correct before signing.']);

        return $this->attempt(fn () => $engine->submit($document, $request->user(), $drafts->current($document, []), $data['signature'], $data['signature_strokes']), 'Signed and sent to your Advisor.');
    }

    public function financeSigned(Request $request, Document $document): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480']]);

        return $this->attempt(fn () => $this->progress->financeSigned($document, $request->user(), $request->file('file')), 'Finance-signed copy uploaded. The Treasurer is told to watch for the bank-in.');
    }

    public function bankIn(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'], 'reference' => ['nullable', 'string', 'max:100']]);

        return $this->attempt(fn () => $this->progress->bankIn($document, $request->user(), $request->file('file'), $data['reference'] ?? null), 'Bank-in proof sent to SLD.');
    }

    public function complete(Request $request, Document $document): RedirectResponse
    {
        return $this->attempt(fn () => $this->progress->complete($document, $request->user()), 'Confirmed. The withdrawal is now in the club finance.');
    }

    public function rejectProof(Request $request, Document $document): RedirectResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'max:500']])['note'];

        return $this->attempt(fn () => $this->progress->rejectProof($document, $request->user(), $note), 'Sent back to the Treasurer.');
    }

    /** Evidence files: committee, advisor and SLD of that club only. */
    public function file(Request $request, Document $document, string $kind): StreamedResponse
    {
        abort_unless($this->canFollow($request->user(), $document), 403);
        $path = match ($kind) {
            'finance' => $document->finance_file_path,
            'bankin' => $document->bankin_path,
            default => abort(404),
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function canPrepare(User $user, Club $club): bool
    {
        return $user->can('viewInternal', $club) || $this->eventsFor($user, $club)->isNotEmpty();
    }

    private function canFollow(User $user, Document $document): bool
    {
        return $user->can('viewInternal', $document->club) || ($document->event && $user->can('organises', $document->event));
    }

    /** @return Collection<int, Event> approved events this person may withdraw for */
    private function eventsFor(User $user, Club $club)
    {
        return Event::where('club_id', $club->id)->where('status', DocumentStatus::Approved)->orderByDesc('starts_at')->get()
            ->filter(fn (Event $e) => $user->can('viewInternal', $club) || $user->can('organises', $e))->values();
    }

    private function askTreasurer(Document $document, User $by): void
    {
        $treasurers = $this->approvers->candidates($document, ChainRole::Treasurer)->reject(fn (User $u) => $u->is($by));
        Notification::send($treasurers, new DocumentNotification(
            'Sign a fund withdrawal',
            "{$by->name} filled in a withdrawal of RM ".number_format(((int) ($document->data()['total_sen'] ?? 0)) / 100, 2).($document->event ? " for {$document->event->name}" : '').'. Check the bank details and sign.',
            route('withdrawals.show', $document),
        ));
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
