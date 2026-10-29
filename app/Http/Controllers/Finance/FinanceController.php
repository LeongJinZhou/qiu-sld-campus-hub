<?php

namespace App\Http\Controllers\Finance;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\Payee;
use App\Models\Receipt;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\FinanceRecordExport;
use App\Services\Documents\PdfConverter;
use App\Services\Finance\FundWithdrawals;
use App\Services\Finance\Ledger;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Club finance: ledger (02 Finance Record), payees and fund withdrawals (07). */
class FinanceController extends Controller
{
    public function __construct(private readonly Ledger $ledger) {}

    public function show(Request $request, Club $club, DocumentPresenter $presenter, FundWithdrawals $funds): Response
    {
        Gate::authorize('viewInternal', $club);
        $user = $request->user();

        return Inertia::render('Finance/Ledger', [
            'club' => $club->only('slug', 'name'),
            'ready' => $this->ledger->isReady($club),
            'opening' => $club->openingBalance ? ['amount_sen' => $club->openingBalance->amount_sen, 'as_of' => $club->openingBalance->as_of->toDateString(), 'note' => $club->openingBalance->note] : null,
            'rows' => $this->ledger->rows($club),
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
            'kind' => ['required', 'in:income,expense'],
            'amount_rm' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'receipt' => ['nullable', 'required_if:kind,expense', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'original' => ['required_if:kind,expense', 'accepted_if:kind,expense'],
        ], ['original.accepted_if' => 'Confirm this is the original receipt (bank screenshots are not accepted).']);
        $sen = (int) round($data['amount_rm'] * 100);

        try {
            $this->ledger->manual($club, CarbonImmutable::parse($data['date']), $data['description'], $data['kind'] === 'income' ? $sen : 0, $data['kind'] === 'expense' ? $sen : 0, $request->file('receipt'), $request->user());
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

    public function export(Club $club, string $format, FinanceRecordExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $file = $export->render($club);
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, "{$club->name} - Finance Record.{$format}")->deleteFileAfterSend();
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
                ->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->name, 'date' => $e->starts_at->toFormattedDateString(), 'lines' => $funds->budgetLines($e)]),
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
}
