<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\PdfConversionFailed;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Document;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\SimpleTableExport;
use App\Services\Events\ProposalRules;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use App\Support\QueryInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * SLD: every event proposal in one list, each downloadable as PDF or Word, plus one ZIP per event with all of its
 * papers (proposal, fund withdrawals, attendance, post mortem) and its finance lines. The list itself exports to Excel/PDF.
 */
class ProposalExportController extends Controller
{
    private const STATUSES = ['in_review', 'returned', 'rejected', 'approved'];

    public function __construct(private readonly ProposalRules $rules) {}

    public function index(Request $request, SessionResolver $sessions): Response
    {
        $session = $this->session($request, $sessions);
        $proposals = $this->query($request, $session)->paginate(25)->withQueryString();

        return Inertia::render('Admin/Proposals/Index', [
            'proposals' => $proposals->through(fn (Document $d) => $this->row($d)),
            'filters' => [
                'q' => QueryInput::text($request, 'q', 100), 'club' => QueryInput::text($request, 'club', 100),
                'status' => QueryInput::oneOf($request, 'status', self::STATUSES), 'session' => $session->name,
            ],
            'clubs' => Club::orderBy('name')->get(['slug', 'name']),
            'sessions' => AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name'),
        ]);
    }

    /** The filtered list as a spreadsheet (or PDF). */
    public function list(Request $request, string $format, SessionResolver $sessions, SimpleTableExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $session = $this->session($request, $sessions);
        $rows = $this->query($request, $session)->get()->map(function (Document $d) {
            $r = $this->row($d);

            return [$r['event'], $r['club'], $r['starts_at'], Str::headline($r['status']), $r['submitted_at'] ?? '-', $r['revenue'], $r['expenses'], $r['profit']];
        });
        $file = $export->render('Event proposals', "Session {$session->name} · {$rows->count()} proposals",
            ['Event', 'Club', 'Event date', 'Status', 'Submitted', 'Revenue (RM)', 'Expenses (RM)', 'Profit / Loss (RM)'], $rows);
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, 'Event proposals '.str_replace('/', '-', $session->name).".{$format}")->deleteFileAfterSend();
    }

    /** Everything on file for one event, together: every document (native + PDF) and the event's finance lines. */
    public function package(Document $document, DocumentExporter $exporter, PdfConverter $pdf, Ledger $ledger, SimpleTableExport $export): BinaryFileResponse
    {
        abort_unless($document->type === DocumentType::Proposal && $document->event, 404);
        $event = $document->event;
        $zipPath = storage_path('app/private/generated/package-'.$document->id.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.zip');
        @mkdir(dirname($zipPath), 0775, true);
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $temp = [];
        $notes = [];

        $documents = Document::with('latestVersion')->where('event_id', $event->id)->orderBy('type')->orderBy('id')->get();
        foreach ($documents as $i => $doc) {
            $formats = $exporter->formats($doc);
            if ($formats === []) {
                continue;
            }
            $label = sprintf('%02d %s', $i + 1, $doc->type->label());
            foreach ($formats as $format) {
                try {
                    $temp[] = $file = $exporter->export($doc, $format);
                    $zip->addFile($file, Str::of($label)->replace('/', '-')->append(".{$format}")->toString());
                } catch (PdfConversionFailed) {
                    $notes[] = "{$label}: PDF could not be made on this server; the editable file is included.";
                }
            }
        }

        $lines = collect($ledger->rows($event->club))->where('event_id', $event->id)->values();
        $temp[] = $finance = $export->render("{$event->name} · Finance", "{$event->club->name} · lines filed under this event",
            ['Date', 'Order ID', 'Description', 'Category', 'Income (RM)', 'Expense (RM)'],
            $lines->map(fn (array $r) => [$r['date'], $r['order_id'] ?? '-', $r['description'], Str::headline((string) $r['category']), $r['income_sen'] / 100, $r['expense_sen'] / 100])
                ->push(['', '', 'Total', '', $lines->sum('income_sen') / 100, $lines->sum('expense_sen') / 100]));
        $zip->addFile($finance, 'Event finance.xlsx');
        if ($notes) {
            $zip->addFromString('README.txt', implode("\n", $notes)."\n");
        }
        $zip->close();
        foreach ($temp as $file) {
            @unlink($file);
        }

        return response()->download($zipPath, Str::of("{$event->club->name} - {$event->name} - all documents.zip")->replace('/', '-')->toString())->deleteFileAfterSend();
    }

    private function query(Request $request, AcademicSession $session): Builder
    {
        $q = QueryInput::text($request, 'q', 100);
        $club = QueryInput::text($request, 'club', 100);

        return Document::query()->with(['event', 'club', 'latestVersion'])
            ->where('type', DocumentType::Proposal)->where('session_id', $session->id)
            ->whereIn('status', self::STATUSES)->whereNotNull('event_id')
            ->when(QueryInput::oneOf($request, 'status', self::STATUSES), fn (Builder $b, string $s) => $b->where('status', $s))
            ->when($club !== '', fn (Builder $b) => $b->whereHas('club', fn (Builder $c) => $c->where('slug', $club)))
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->whereHas('event', fn (Builder $e) => $e->where('name', 'like', "%{$q}%"))
                ->orWhereHas('club', fn (Builder $c) => $c->where('name', 'like', "%{$q}%"))))
            ->orderByDesc('submitted_at')->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function row(Document $d): array
    {
        $totals = $this->rules->budgetTotals((array) ($d->data()['budget'] ?? []));

        return [
            'id' => $d->id,
            'event' => $d->event->name,
            'event_slug' => $d->event->slug,
            'club' => $d->club->name,
            'starts_at' => $d->event->starts_at->format('j M Y'),
            'status' => $d->status->value,
            'submitted_at' => $d->submitted_at?->format('j M Y'),
            'revenue' => $totals['revenue_sen'] / 100,
            'expenses' => $totals['expenses_sen'] / 100,
            'profit' => $totals['profit_sen'] / 100,
            'downloadable' => $d->latestVersion !== null,
            'approved' => $d->status === DocumentStatus::Approved,
        ];
    }

    private function session(Request $request, SessionResolver $sessions): AcademicSession
    {
        return AcademicSession::where('name', QueryInput::text($request, 'session', 9))->first() ?? $sessions->current();
    }
}
