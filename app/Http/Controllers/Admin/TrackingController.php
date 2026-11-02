<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Services\Dashboard\ClubTracking;
use App\Services\Time\SessionResolver;
use App\Support\QueryInput;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** SLD: every club's submitted and missing reports, completion packages, AGMs and overdue transfers. */
class TrackingController extends Controller
{
    public function __invoke(Request $request, ClubTracking $tracking, SessionResolver $sessions): Response
    {
        $session = AcademicSession::where('name', QueryInput::text($request, 'session', 9))->first() ?? $sessions->current();

        return Inertia::render('Admin/Tracking/Index', [
            ...$tracking->build($session),
            'sessions' => AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name'),
        ]);
    }
}
