<?php

namespace App\Http\Controllers\Finance;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\Document;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Payee;
use App\Models\Receipt;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\FinanceRecordExport;
use App\Services\Documents\PdfConverter;
use App\Services\Finance\FinanceReport;
use App\Services\Finance\FundWithdrawals;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Club finance: ledger (02 Finance Record), payees and fund withdrawals (07). */
class FinanceController extends Controller
{
    public function __construct(private readonly Ledger $ledger) {}

    public function show(Request $request, Club $club, DocumentPresenter $presenter, FundWithdrawals $funds, FinanceReport $reports, SessionResolver $sessions): Response
    {
        Gate::authorize('viewInternal', $club);
        $user = $request->user();
        $filters = $this->filters($request);
        $session = $this->session($request, $sessions);
        $report = $reports->build($club, $session, $filters);
        $report['rows'] = array_map(fn (array $r) => [...$r, ...$this->evidence($club, $r)], $report['rows']);

        return Inertia::render('Finance/Ledger', [
            'club' => $club->only('slug', 'name'),
            'ready' => $this->ledger->isReady($club),
            'opening' => $club->openingBalance ? ['amount_sen' => $club->openingBalance->amount_sen, 'as_of' => $club->openingBalance->as_of->toDateString(), 'note' => $club->openingBalance->note] : null,
            'report' => $report,
            'filters' => (object) $filters,
            'sessions' => AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name'),
            'categories' => LedgerEntry::CATEGORIES,
            'methods' => LedgerEntry::METHODS,
            'clubEvents' => Event::where('club_id', $club->id)->orderByDesc('starts_at')->get(['id', 'name', 'starts_at'])
                ->map(fn (Event $e) => ['value' => $e->id, 'label' => "{$e->name} · {$e->starts_at->format('j M Y')}"]),
            'balance_sen' => $this->ledger->balance($club),
            'payees' => Payee::where('club_id', $club->id)->orderBy('kind')->get()->map(fn (Payee $p) => [
                'id' => $p->id, 'kind' => $p->kind, 'name' => $p->name, 'bank_name' => $p->bank_name, 'account' => $p->maskedAccount(),
            ]),
            'requests' => Document::with('event', 'approvals')->where('club_id', $club->id)->where('type', DocumentType::FundWithdrawal)->latest()->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id, 'event' => $d->event?->name, 'purpose' => $d->data()['purpose'] ?? null,
                    'total_sen' => $d->data()['total_sen'] ?? 0, 'status' => $d->status->value, 'progress' => $presenter->progress($d),
                ]),
            'events' => Event::where('club_id', $club->id)->where('status', DocumentStatus::Approved)->orderByDesc('starts_at')->get()
                ->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->name, 'lines' => $funds->budgetLines($e)]),
            'fee' => ($fee = ClubFee::where('club_id', $club->id)->where('session_id', $sessions->current()->id)->first()) ? [
                'amount_sen' => $fee->amount_sen, 'payment_url' => $fee->payment_url, 'account_name' => $fee->account_name, 'approved' => $fee->approved_at !== null,
                'qr' => collect($fee->payment_qr_paths ?? [])->map(fn (string $p) => ['path' => $p, 'url' => Storage::disk('public')->url($p)])->values(),
            ] : null,
            'can' => ['edit' => $user->can('treasure', $club), 'sld' => $user->isSldAdmin()],
        ]);
    }

    public function opening(Request $request, Club $club): RedirectResponse
    {
        abort_unless($request->user()->isSldAdmin(), 403);
        $data = $request->validate(['amount_rm' => ['required', 'numeric', 'between:-1000000,1000000'], 'as_of' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:500']]);
        $this->ledger->setOpeningBalance($club, (int) round($data['amount_rm'] * 100), CarbonImmutable::parse($data['as_of']), $data['note'] ?? null, $request->user());

        return back()->with('success', 'Opening balance saved. The club can now use its ledger.');
    }

    public function entry(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('treasure', $club);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('club_id', $club->id)],
            'method' => ['nullable', Rule::in(array_keys(LedgerEntry::METHODS))],
            'kind' => ['required', 'in:income,expense'],
            'amount_rm' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'receipt' => ['nullable', 'required_if:kind,expense', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'original' => ['required_if:kind,expense', 'accepted_if:kind,expense'],
        ], ['original.accepted_if' => 'Confirm this is the original receipt (bank screenshots are not accepted).']);
        $sen = (int) round($data['amount_rm'] * 100);

        try {
            $this->ledger->manual($club, CarbonImmutable::parse($data['date']), $data['description'], $data['kind'] === 'income' ? $sen : 0, $data['kind'] === 'expense' ? $sen : 0,
                $request->file('receipt'), $request->user(), eventId: $data['event_id'] ?? null, method: $data['method'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Entry added.');
    }

    public function receipt(Club $club, Receipt $receipt): StreamedResponse
    {
        Gate::authorize('viewInternal', $club);
        abort_unless($receipt->club_id === $club->id, 404);

        return Storage::disk('local')->response($receipt->path, $receipt->original_name);
    }

    /** Attach the original receipt to a transaction afterwards (e.g. purchases paid by a fund withdrawal). */
    public function attachReceipt(Request $request, Club $club, LedgerEntry $entry): RedirectResponse
    {
        Gate::authorize('treasure', $club);
        abort_unless($entry->club_id === $club->id, 404);
        $data = $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192']]);
        $this->ledger->attachReceipt($entry, $data['receipt'], $request->user());

        return back()->with('success', 'Receipt attached.');
    }

    /** The official 02 Finance Record for the chosen session, exactly as filtered on screen. */
    public function export(Request $request, Club $club, string $format, FinanceRecordExport $export, PdfConverter $pdf, FinanceReport $reports, SessionResolver $sessions): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $report = $reports->build($club, $this->session($request, $sessions), $this->filters($request));
        $file = $export->render($club, $reports->exportRows($report));
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, "{$club->name} - Finance Record ".str_replace('/', '-', $report['session']).".{$format}")->deleteFileAfterSend();
    }

    public function payee(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('treasure', $club);
        $data = $request->validate([
            'kind' => ['required', 'in:treasurer,vendor'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact_no' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_holder' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'regex:/^[0-9\-\s]{6,30}$/'],
        ]);
        Payee::create([...$data, 'club_id' => $club->id]);

        return back()->with('success', 'Payee saved. Bank details are stored encrypted.');
    }

    public function requestForm(Request $request, Club $club, FundWithdrawals $funds): Response
    {
        Gate::authorize('viewInternal', $club);

        return Inertia::render('Finance/FundRequest', [
            'club' => $club->only('slug', 'name'),
            'events' => Event::where('club_id', $club->id)->where('status', DocumentStatus::Approved)->orderByDesc('starts_at')->get()
                ->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->name, 'date' => $e->starts_at->format('j M Y'), 'lines' => $funds->budgetLines($e)]),
            'payees' => Payee::where('club_id', $club->id)->get()->map(fn (Payee $p) => ['id' => $p->id, 'label' => "{$p->name} · {$p->bank_name} {$p->maskedAccount()} ({$p->kind})"]),
            'selectedEvent' => $request->integer('event') ?: null,
            'earliestWeekly' => today()->addDays(FundWithdrawals::WEEKLY_LEAD_DAYS)->toDateString(),
            'secretary' => $club->roleAssignments()->where('position', 'Secretary')->latest('id')->with('user')->first()?->user?->name,
        ]);
    }

    public function submitRequest(Request $request, Club $club, FundWithdrawals $funds, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('viewInternal', $club);
        $input = $request->validate([
            'event_id' => ['nullable', 'integer'],
            'purpose' => ['required', 'string', 'max:300'],
            'needed_by' => ['nullable', 'required_without:event_id', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit_sen' => ['required', 'integer', 'min:1', 'max:100000000'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.method' => ['required', 'in:vendor,self'],
            'payees' => ['required', 'array', 'min:1', 'max:10'],
            'payees.*.payee_id' => ['required', 'integer'],
            'payees.*.amount_sen' => ['required', 'integer', 'min:1'],
            'signature' => ['required', 'string'],
            'signature_strokes' => ['required', 'string', 'max:307200'],
        ]);

        try {
            $document = DB::transaction(function () use ($club, $input, $funds, $engine, $request) {
                $data = $funds->normalise($club, $input);
                $document = $funds->draft($club, $request->user(), $data['event_id'] ? Event::find($data['event_id']) : null);
                $engine->submit($document, $request->user(), $data, $input['signature'], $input['signature_strokes']);

                return $document;
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('documents.show', $document)->with('success', 'Fund withdrawal signed and sent to your President.');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'category' => ['nullable', Rule::in(array_keys(LedgerEntry::CATEGORIES))],
            'event_id' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::in(array_keys(LedgerEntry::METHODS))],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(FinanceReport::SORTS)],
        ]), fn ($v) => $v !== null && $v !== '');
    }

    private function session(Request $request, SessionResolver $sessions): AcademicSession
    {
        $name = $request->validate(['session' => ['nullable', 'regex:/^\d{4}\/\d{4}$/']])['session'] ?? null;

        return $name ? AcademicSession::where('name', $name)->firstOrFail() : $sessions->current();
    }

    /**
     * Private evidence for one transaction, as links that expire after 30 minutes: the uploaded receipt, the
     * student's online payment receipt, the cash handover photo, or the fund withdrawal form.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, ?string>
     */
    private function evidence(Club $club, array $row): array
    {
        $signed = fn (string $route, array $params) => URL::temporarySignedRoute($route, now()->addMinutes(30), $params);

        return [
            'receipt_url' => $row['receipt_id'] ? $signed('finance.receipt', [$club, $row['receipt_id']]) : null,
            'payment_receipt_url' => $row['has_payment_receipt'] ? $signed('payments.receipt', [$row['payment_id']]) : null,
            'photo_url' => $row['has_photo'] ? $signed('handovers.photo', [$row['handover_id']]) : null,
            'document_url' => $row['document_id'] ? route('documents.show', $row['document_id']) : null,
        ];
    }
}
