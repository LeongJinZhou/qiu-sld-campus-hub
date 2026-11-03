<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Meeting;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\SimpleTableExport;
use App\Services\Meetings\MeetingReport;
use App\Services\Time\SessionResolver;
use App\Support\QueryInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The meetings report: one club's for its committee and advisor, every club's for SLD; both export to Excel/PDF. */
class MeetingReportController extends Controller
{
    public function __construct(private readonly MeetingReport $report, private readonly SessionResolver $sessions) {}

    public function club(Request $request, Club $club): Response
    {
        Gate::authorize('viewInternal', $club);

        return $this->page($request, $club);
    }

    public function admin(Request $request): Response
    {
        return $this->page($request, null);
    }

    public function clubExport(Request $request, Club $club, string $format, SimpleTableExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);

        return $this->download($request, $club, $format, $export, $pdf);
    }

    public function adminExport(Request $request, string $format, SimpleTableExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        return $this->download($request, null, $format, $export, $pdf);
    }

    private function page(Request $request, ?Club $club): Response
    {
        $session = $this->session($request);
        $filters = $this->report->filters($request);
        $page = $this->report->query($session, $filters, $club)->paginate(30)->withQueryString();
        $rows = $page->getCollection()->map(fn (Meeting $m) => $this->report->row($m));

        return Inertia::render('Meetings/Report', [
            'club' => $club?->only('slug', 'name'),
            'meetings' => $page->setCollection($rows),
            'totals' => $this->report->totals($this->report->query($session, $filters, $club)->get()->map(fn (Meeting $m) => $this->report->row($m))),
            'filters' => [...$filters, 'session' => $session->name],
            'kinds' => Meeting::KINDS,
            'clubs' => $club ? [] : Club::orderBy('name')->get(['slug', 'name']),
            'sessions' => AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name'),
            'exportBase' => $club ? "/clubs/{$club->slug}/meetings/report" : '/admin/meetings',
        ]);
    }

    private function download(Request $request, ?Club $club, string $format, SimpleTableExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $session = $this->session($request);
        $rows = $this->report->query($session, $this->report->filters($request), $club)->get()->map(fn (Meeting $m) => $this->report->line($this->report->row($m)));
        $title = ($club ? "{$club->name} · " : '').'Meetings report';
        $file = $export->render($title, "Session {$session->name} · {$rows->count()} meetings", MeetingReport::HEADERS, $rows);
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, str_replace(['/', '·'], ['-', '-'], $title).' '.str_replace('/', '-', $session->name).".{$format}")->deleteFileAfterSend();
    }

    private function session(Request $request): AcademicSession
    {
        return AcademicSession::where('name', QueryInput::text($request, 'session', 9))->first() ?? $this->sessions->current();
    }
}
