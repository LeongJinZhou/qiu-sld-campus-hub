<?php

namespace App\Http\Controllers\Agm;

use App\Http\Controllers\Controller;
use App\Jobs\DraftAnnualReport;
use App\Models\Club;
use App\Models\GeneratedFile;
use App\Services\Agm\AgmPeriods;
use App\Services\Reports\AnnualReports;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Annual Activities Report: edit each event's Details (AI can draft them), pick a theme, generate PPTX + PDF. */
class AnnualReportController extends Controller
{
    public function __construct(private readonly AnnualReports $reports, private readonly AgmPeriods $periods) {}

    public function save(Request $request, Club $club): RedirectResponse
    {
        $file = $this->file($request, $club);
        $data = $request->validate([
            'details' => ['present', 'array'],
            'details.*' => ['nullable', 'string', 'max:1500'],
            'theme_id' => ['nullable', Rule::exists('report_themes', 'id')],
        ]);
        $this->reports->saveDetails($file, $data['details'], $data['theme_id'] ?? null);

        return back()->with('success', 'Saved.');
    }

    public function draft(Request $request, Club $club): RedirectResponse
    {
        $file = $this->file($request, $club);
        Cache::put(DraftAnnualReport::statusKey($file->id), 'pending', now()->addMinutes(10));
        DraftAnnualReport::dispatch($file->id);

        return back()->with('success', 'Writing the details for each event…');
    }

    public function generate(Request $request, Club $club): RedirectResponse
    {
        $file = $this->file($request, $club);
        try {
            $this->reports->generate($file);
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'The report could not be generated. Please try again or contact SLD.');
        }

        return back()->with('success', 'Annual Activities Report generated.');
    }

    public function download(Request $request, Club $club, string $format): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);
        abort_unless(in_array($format, ['pptx', 'pdf'], true), 404);
        $file = GeneratedFile::with('session')->where(['club_id' => $club->id, 'kind' => GeneratedFile::ANNUAL_REPORT])
            ->when($request->query('session'), fn ($q, $id) => $q->where('session_id', $id))
            ->whereNotNull('generated_at')->latest('generated_at')->firstOrFail();
        $path = $format === 'pptx' ? $file->pptx_path : $file->pdf_path;
        abort_unless($path && is_file($path), 404);

        return response()->download($path, "{$club->name} - Annual Activities Report ".str_replace('/', '-', $file->session->name).".{$format}");
    }

    /** Generated when the AGM period opens; locked outside it. */
    private function file(Request $request, Club $club): GeneratedFile
    {
        Gate::authorize('viewInternal', $club);
        try {
            $period = $this->periods->assertOpen($this->periods->current());
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        return $this->reports->for($club, $period->session, $request->user());
    }
}
