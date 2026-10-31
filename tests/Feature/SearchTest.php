<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->user = $this->f['president'];
});

it('finds clubs, approved events and people from one box', function () {
    Club::create(['name' => 'Beach Volleyball Club']);
    $this->f['event']->update(['status' => 'approved']);
    Event::create(['club_id' => $this->f['club']->id, 'session_id' => $this->f['event']->session_id, 'name' => 'Beach Party Draft', 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addHour()]);

    $this->actingAs($this->user)->get('/search?q=beach')->assertInertia(fn (Assert $p) => $p
        ->component('Search/Index')
        ->where('clubs.0.name', 'Beach Volleyball Club')
        ->has('events', 1)
        ->where('events.0.name', 'Beach Cleanup'));

    $someone = Student::where('status', 'active')->orderByDesc('student_number')->first();
    $this->actingAs($this->user)->get('/search?type=people&q='.urlencode(explode(' ', $someone->name)[0]))->assertInertia(fn (Assert $p) => $p
        ->where('people.0.name', $someone->name)
        ->missing('people.0.email')
        ->missing('people.0.student_number')
        ->has('clubs', 0));
});

it('sends the old clubs page to search', function () {
    $this->actingAs($this->user)->get('/clubs?search=gps')->assertRedirect('/search?type=clubs&q=gps');
});
