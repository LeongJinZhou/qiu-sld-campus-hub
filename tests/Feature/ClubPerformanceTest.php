<?php

use App\Enums\DocumentStatus;
use App\Models\Club;
use Inertia\Testing\AssertableInertia as Assert;

it('ranks clubs by score, groups them by category and drills into one club by month', function () {
    $f = proposalFixture();
    $f['event']->update(['status' => DocumentStatus::Approved, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(2)]);
    $student = otherStudent();
    $f['event']->registrations()->create(['user_id' => $student->id]);
    $f['event']->attendance()->create(['user_id' => $student->id, 'role' => 'participant', 'clock_in_at' => now()->subDays(3), 'method' => 'qr']);
    Club::create(['name' => 'Quiet Club']);

    $this->actingAs(sldOfficer())->get('/admin/performance')->assertInertia(fn (Assert $p) => $p->component('Admin/Performance/Index')
        ->where('clubs.0.slug', $f['club']->slug)->where('clubs.0.rank', 1)->where('clubs.0.events', 1)->where('clubs.0.attendance', 1)
        ->where('clubs.1.name', 'Quiet Club')->has('categories'));

    $this->actingAs(sldOfficer())->get("/admin/performance?club={$f['club']->slug}")->assertInertia(fn (Assert $p) => $p
        ->where('club.slug', $f['club']->slug)->where('club.events.0.attended', 1)
        ->where('club.months', fn ($months) => collect($months)->sum('attendance') === 1));
    $this->actingAs($student)->get('/admin/performance')->assertForbidden();
});
