<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Services\Dashboard\ClubPerformance;
use App\Services\Time\SessionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** SLD: which clubs perform best, by category (drill up), by club, then month by month and event by event (drill down). */
class PerformanceController extends Controller
{
    public function __invoke(Request $request, ClubPerformance $performance, SessionResolver $sessions): Response
    {
        $name = (string) $request->query('session', '');
        $session = AcademicSession::where('name', $name)->first() ?? $sessions->current();
        $clubs = $performance->clubs($session);
        $focus = $request->query('club') ? Club::where('slug', (string) $request->query('club'))->first() : null;

        return Inertia::render('Admin/Performance/Index', [
            'session' => $session->name,
            'sessions' => AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name'),
            'weights' => ClubPerformance::WEIGHTS,
            'clubs' => $clubs,
            'categories' => $performance->categories($clubs),
            'club' => $focus ? ['slug' => $focus->slug, 'name' => $focus->name, ...$performance->club($focus, $session)] : null,
        ]);
    }
}
