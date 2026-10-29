<?php

use App\Enums\DocumentStatus;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\User;
use App\Services\Profiles\Certificates;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->secretary = $this->f['club']->roleAssignments()->where('position', 'Secretary')->first()->user;
});

it('offers certificates only for what the records prove', function () {
    $certificates = app(Certificates::class);
    // Club committee position: yes. Event committee: only after the approved event has happened.
    $kinds = collect($certificates->available($this->secretary))->pluck('kind')->all();
    expect($kinds)->toBe(['club_committee']);

    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'starts_at' => now()->subDays(3)->setTime(8, 0), 'ends_at' => now()->subDays(3)->setTime(12, 0)]);
    $member = $event->committee()->first()->user;
    $participant = User::factory()->create();
    Attendance::create(['event_id' => $event->id, 'user_id' => $participant->id, 'role' => 'participant', 'method' => 'qr', 'clock_in_at' => now()->subDays(3)->setTime(8, 5), 'clock_out_at' => now()->subDays(3)->setTime(12, 0)]);

    expect(collect($certificates->available($member))->pluck('kind'))->toContain('event_committee')
        ->and($certificates->available($participant)[0]['statement'])->toContain("participated in {$event->name}");
    // Someone else's record cannot be issued to you.
    $this->actingAs($participant)->get('/me/certificates/club_committee/'.$this->f['club']->roleAssignments()->first()->id)->assertNotFound();
});

it('issues a certificate once, with a public verification page', function () {
    $source = collect(app(Certificates::class)->available($this->secretary))->first();
    $first = app(Certificates::class)->issue($this->secretary, $source['kind'], $source['source_id']);
    $again = app(Certificates::class)->issue($this->secretary, $source['kind'], $source['source_id']);
    expect($again->is($first))->toBeTrue()->and(Certificate::count())->toBe(1)->and($first->statement)->toContain('served as Secretary of Fixture Club');

    $this->get("/certificates/{$first->code}")->assertOk()->assertInertia(fn ($page) => $page->where('certificate.name', $this->secretary->name));
    $this->get('/certificates/NOPE')->assertOk()->assertInertia(fn ($page) => $page->where('certificate', null));
});

it('downloads the certificate as a PDF', function () {
    $source = collect(app(Certificates::class)->available($this->secretary))->first();
    $this->actingAs($this->secretary)->get("/me/certificates/{$source['kind']}/{$source['source_id']}")
        ->assertOk()->assertHeader('content-type', 'application/pdf');
})->skip(fn () => ! is_executable((string) config('sld.soffice')), 'LibreOffice not installed');
