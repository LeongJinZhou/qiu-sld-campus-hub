<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\EventCalendar;
use App\Support\QueryInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /** Month view (?month=2026-11), with the weeks around it so the grid is full. */
    public function index(Request $request, EventCalendar $calendar): Response
    {
        $user = $request->user();
        $asked = QueryInput::month($request, 'month');
        $month = $asked ? CarbonImmutable::createFromFormat('!Y-m', $asked) : CarbonImmutable::now()->startOfMonth();
        $from = $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY);
        $to = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addDay()->startOfDay();
        $mine = $user->eventRegistrations()->pluck('event_id')->all();
        $followed = $user->followedClubs()->pluck('clubs.id')->all();
        $first = Event::where('status', DocumentStatus::Approved)->min('starts_at');

        return Inertia::render('Calendar/Index', [
            'month' => $month->format('Y-m'),
            'today' => CarbonImmutable::now()->toDateString(),
            'events' => $calendar->between($from, $to)->map(fn (Event $e) => $calendar->present($e, $user, $mine, $followed))->values(),
            'years' => range((int) CarbonImmutable::parse($first ?? now())->year, (int) now()->addYear()->year),
            'feedUrl' => URL::signedRoute('calendar.feed', ['user' => $user->id]),
        ]);
    }

    /** Private subscription link for Google Calendar (or any calendar app); no sign-in, the link is the key. */
    public function feed(User $user, EventCalendar $calendar): HttpResponse
    {
        return response($calendar->ics(), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'inline; filename="qiu-campus-events.ics"']);
    }
}
