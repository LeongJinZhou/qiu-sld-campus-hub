<?php

namespace App\Http\Controllers\Agm;

use App\Http\Controllers\Controller;
use App\Jobs\DraftAnnualReport;
use App\Models\Agm;
use App\Models\Club;
use App\Models\ReportTheme;
use App\Models\Student;
use App\Models\User;
use App\Services\Agm\AgmPackage;
use App\Services\Agm\AgmPeriods;
use App\Services\Agm\Agms;
use App\Services\Documents\DocumentPresenter;
use App\Services\Events\AttendanceQr;
use App\Services\Google\CalendarMeetService;
use App\Services\Meetings\Meetings;
use App\Services\QrCodes;
use App\Services\Reports\AnnualReports;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The club's AGM hub: countdown, scheduling, QR attendance, election, report, annual report and package. */
class AgmController extends Controller
{
    public function __construct(private readonly Agms $agms, private readonly AgmPeriods $periods, private readonly AttendanceQr $qr) {}

    public function show(Request $request, Club $club, CalendarMeetService $calendar, QrCodes $codes, AgmPackage $package, AnnualReports $annual, Meetings $meetings, DocumentPresenter $presenter): Response
    {
        Gate::authorize('viewInternal', $club);
        $period = $this->periods->current();
        $agm = $this->agms->for($club, $period)?->load(['report', 'election', 'attendees']);
        $file = $period ? $annual->for($club, $period->session, $request->user()) : null;

        return Inertia::render('Agm/Show', [
            'club' => $club->only('slug', 'name'),
            'period' => $period ? [
                'session' => $period->session->name,
                'starts_on' => $period->starts_on->toDateString(),
                'agm_deadline' => $period->agm_deadline->toDateString(),
                'report_deadline' => $period->report_deadline->toDateString(),
            ] : null,
            'calendarConnected' => $calendar->isConnected($request->user()),
            'agm' => $agm ? [
                'id' => $agm->id,
                'starts_at' => $agm->starts_at->toIso8601String(), 'ends_at' => $agm->ends_at->toIso8601String(),
                'venue' => $agm->venue, 'meet_link' => $agm->meet_link,
                'attendance_open' => now()->between($agm->starts_at->subHour(), $agm->ends_at->addHour()),
                'qr_svg' => now()->between($agm->starts_at->subHour(), $agm->ends_at->addHour()) ? $codes->svg($this->url($agm)) : null,
                'present' => $agm->attendees->pluck('id'),
                'report' => $agm->report ? $presenter->progress($agm->report) : null,
                'election' => $agm->election?->phase(),
                'package_submitted_at' => $agm->package_submitted_at?->format('D, j M Y, g:i A'),
                'checklist' => $package->checklist($agm),
            ] : null,
            'people' => $agm ? $meetings->committee($club)
                ->merge($club->memberships()->whereIn('status', ['active', 'grace'])->with('user')->get()->pluck('user'))
                ->unique('id')->sortBy('name')->values()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'photo_url' => $u->photoUrl()]) : [],
            'annual' => $file ? [
                'events' => $annual->events($file)->map(fn ($e) => [
                    'id' => $e->id, 'name' => $e->name, 'date' => $e->starts_at->format('j M Y'), 'details' => $file->data['details'][$e->id] ?? '',
                ])->values(),
                'theme_id' => $file->report_theme_id,
                'generated_at' => $file->generated_at?->format('D, j M Y, g:i A'),
                'ai' => Cache::get(DraftAnnualReport::statusKey($file->id)),
                'ai_available' => (bool) config('services.anthropic.key'),
            ] : null,
            'themes' => ReportTheme::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('viewInternal', $club);
        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'venue' => ['nullable', 'required_unless:online,true', 'string', 'max:150'],
            'online' => ['boolean'],
        ]);
        try {
            $this->agms->schedule($club, $request->user(), [...$data, 'online' => $request->boolean('online')]);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AGM scheduled and every member notified.');
    }

    /** Members scan the QR on screen at the AGM. */
    public function attend(Request $request, Agm $agm): RedirectResponse
    {
        if (! $this->qr->isValid($agm, 'in', (string) $request->query('code'))) {
            return to_route('feed')->with('error', 'This QR code has expired. Scan the code on screen again.');
        }
        try {
            $this->agms->attend($agm, $request->user(), 'qr');
        } catch (DomainException $e) {
            return to_route('feed')->with('error', $e->getMessage());
        }

        return to_route('clubs.show', $agm->club)->with('success', "You're marked present at the {$agm->club->name} AGM.");
    }

    /** Committee marks people present by hand, or by student ID. */
    public function record(Request $request, Agm $agm): RedirectResponse
    {
        Gate::authorize('viewInternal', $agm->club);
        $data = $request->validate(['user_id' => ['nullable', 'required_without:student_number', 'integer'], 'student_number' => ['nullable', 'string', 'max:50']]);
        $user = isset($data['student_number'])
            ? Student::where('student_number', strtoupper(trim($data['student_number'])))->first()?->ensureUser()
            : User::find($data['user_id']);
        if (! $user) {
            return back()->with('error', 'No student found.');
        }
        try {
            $this->agms->attend($agm, $user, 'manual');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$user->name}: present.");
    }

    public function submitPackage(Request $request, Agm $agm, AgmPackage $package): RedirectResponse
    {
        Gate::authorize('viewInternal', $agm->club);
        try {
            $package->submit($agm, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AGM package submitted to SLD.');
    }

    private function url(Agm $agm): string
    {
        return route('agm.attend', ['agm' => $agm, 'code' => $this->qr->code($agm, 'in')]);
    }
}
