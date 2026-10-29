<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agm;
use App\Models\AgmPeriod;
use App\Models\Club;
use App\Models\GeneratedFile;
use App\Models\ReportTheme;
use App\Services\Agm\AgmPackage;
use App\Services\Agm\AgmPeriods;
use App\Services\Agm\Elections;
use App\Services\Documents\DocumentPresenter;
use App\Services\Time\SessionResolver;
use App\Services\Uploads\ImageUpload;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** SLD: AGM periods, every club's AGM progress and package checklist, and report themes. */
class AgmController extends Controller
{
    public function __construct(private readonly AgmPeriods $periods) {}

    public function index(AgmPackage $package, Elections $elections, DocumentPresenter $presenter): Response
    {
        $period = $this->periods->current();
        $agms = $period ? Agm::with(['report', 'election', 'club'])->withCount('attendees')->where('agm_period_id', $period->id)->get()->keyBy('club_id') : collect();
        $annual = $period ? GeneratedFile::where('session_id', $period->session_id)->where('kind', GeneratedFile::ANNUAL_REPORT)->whereNotNull('generated_at')->pluck('club_id')->flip() : collect();

        return Inertia::render('Admin/Agm/Index', [
            'period' => $period ? [
                'id' => $period->id, 'session' => $period->session->name, 'session_id' => $period->session_id,
                'starts_on' => $period->starts_on->toDateString(), 'agm_deadline' => $period->agm_deadline->toDateString(),
                'report_deadline' => $period->report_deadline->toDateString(),
            ] : null,
            'history' => AgmPeriod::with('session')->latest('starts_on')->limit(10)->get()->map(fn (AgmPeriod $p) => [
                'id' => $p->id, 'session' => $p->session->name, 'starts_on' => $p->starts_on->toDateString(),
                'report_deadline' => $p->report_deadline->toDateString(), 'closed' => $p->closed_at !== null || ! $p->isOpen(),
            ]),
            'clubs' => $period ? Club::orderBy('name')->get()->map(function (Club $club) use ($agms, $annual, $package, $elections, $presenter) {
                $agm = $agms[$club->id] ?? null;

                return [
                    'slug' => $club->slug, 'name' => $club->name,
                    'agm' => $agm ? [
                        'starts_at' => $agm->starts_at->toDayDateTimeString(), 'attended' => $agm->attendees_count,
                        'report' => $agm->report ? $presenter->progress($agm->report) : null,
                        'report_id' => $agm->report?->id,
                        'election' => $agm->election?->phase(),
                        'flags' => $agm->election ? $elections->flags($agm->election) : [],
                        'checklist' => $package->checklist($agm),
                        'package_submitted_at' => $agm->package_submitted_at?->toDayDateTimeString(),
                    ] : null,
                    'annual' => isset($annual[$club->id]),
                ];
            }) : [],
            'themes' => ReportTheme::orderBy('name')->get()->map(fn (ReportTheme $t) => [
                ...$t->only('id', 'name', 'background_color', 'title_color', 'body_color', 'font', 'is_default'),
                'background_url' => $t->background_path ? Storage::disk('public')->url($t->background_path) : null,
            ]),
            'defaultSession' => app(SessionResolver::class)->current()->name,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'starts_on' => ['required', 'date'],
            'agm_deadline' => ['required', 'date', 'after_or_equal:starts_on'],
            'report_deadline' => ['required', 'date', 'after_or_equal:agm_deadline'],
        ]);
        try {
            $this->periods->open($request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AGM period opened. Clubs can now schedule their AGMs.');
    }

    public function close(AgmPeriod $period): RedirectResponse
    {
        $this->periods->close($period);

        return back()->with('success', 'AGM period closed. The AGM module is locked for clubs.');
    }

    public function theme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'title_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'body_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font' => ['required', 'string', 'max:60'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'is_default' => ['boolean'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            if ($request->boolean('is_default')) {
                ReportTheme::query()->update(['is_default' => false]);
            }
            ReportTheme::create([
                ...collect($data)->except('background')->all(),
                'is_default' => $request->boolean('is_default'),
                'background_path' => $request->hasFile('background') ? app(ImageUpload::class)->store($request->file('background'), 'report-themes') : null,
            ]);
        });

        return back()->with('success', 'Theme saved.');
    }
}
