<?php

use App\Enums\DocumentStatus;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

it('shows every approved event to everyone by month, past months included, with a Google Calendar link and feed', function () {
    $f = proposalFixture();
    $event = $f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'starts_at' => '2025-03-10 09:00', 'ends_at' => '2025-03-10 12:00']);
    $viewer = otherStudent();

    $this->actingAs($viewer)->get('/calendar?month=2025-03')->assertInertia(fn (Assert $p) => $p->component('Calendar/Index')
        ->has('events', 1)->where('events.0.name', $event->name)->where('events.0.google_url', fn ($u) => str_contains($u, 'calendar.google.com') && str_contains($u, '20250310T010000Z')));
    $this->actingAs($viewer)->get('/calendar?month=2025-04')->assertInertia(fn (Assert $p) => $p->has('events', 0));

    $event->update(['status' => DocumentStatus::InReview]);
    $this->actingAs($viewer)->get('/calendar?month=2025-03')->assertInertia(fn (Assert $p) => $p->has('events', 0));

    $event->update(['status' => DocumentStatus::Approved, 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHours(2)]);
    $this->get(URL::signedRoute('calendar.feed', ['user' => $viewer->id]))->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->assertSee('BEGIN:VEVENT')->assertSee('SUMMARY:'.$event->name, false);
    $this->get("/calendar/feed/{$viewer->id}.ics")->assertForbidden();
});
