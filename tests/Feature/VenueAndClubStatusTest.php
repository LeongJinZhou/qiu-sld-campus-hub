<?php

use App\Models\User;
use App\Models\VenueBooking;
use App\Services\ClubStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->f = proposalFixture();
});

function venueRequest(array $overrides = []): array
{
    return [...[
        'type' => 'la_place', 'date' => today()->addDays(2)->toDateString(), 'starts_at' => '10:00', 'ends_at' => '14:00',
        'purpose' => 'Membership booth', 'items' => 'T-shirts', 'activity' => 'Recruit members', 'rules_accepted' => true,
    ], ...$overrides];
}

it('lets a committee member book La Place and SLD confirm it', function () {
    ['club' => $club, 'president' => $president] = $this->f;

    $this->actingAs($president)->post("/clubs/{$club->slug}/venues", venueRequest())->assertSessionHas('success');
    $booking = VenueBooking::sole();
    expect($booking->venue)->toBe('La Place')->and($booking->status)->toBe('pending');

    $sld = sldOfficer();
    $this->actingAs($sld)->post("/admin/venues/{$booking->id}", ['confirm' => true, 'note' => 'Table 3'])->assertSessionHas('success');
    expect($booking->fresh()->status)->toBe('confirmed');
    $this->actingAs($sld)->post("/admin/venues/{$booking->id}", ['confirm' => false])->assertSessionHas('error');
});

it('requires the La Place rules to be accepted and big rooms a week ahead', function () {
    ['club' => $club, 'president' => $president] = $this->f;

    $this->actingAs($president)->post("/clubs/{$club->slug}/venues", venueRequest(['rules_accepted' => false]))->assertSessionHasErrors('rules_accepted');
    $this->actingAs($president)->post("/clubs/{$club->slug}/venues", venueRequest(['type' => 'big_room', 'venue' => 'Hall', 'date' => today()->addDays(3)->toDateString()]))
        ->assertSessionHas('error', 'Big rooms must be booked at least 1 week ahead.');
    $this->actingAs($president)->post("/clubs/{$club->slug}/venues", venueRequest(['type' => 'big_room', 'venue' => 'Hall', 'date' => today()->addDays(8)->toDateString()]))
        ->assertSessionHas('success');
    expect(VenueBooking::count())->toBe(1);
});

it('keeps students outside the club and non-SLD users out', function () {
    ['club' => $club, 'president' => $president] = $this->f;
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->post("/clubs/{$club->slug}/venues", venueRequest())->assertForbidden();
    $this->actingAs($president)->post("/clubs/{$club->slug}/venues", venueRequest());
    $this->actingAs($president)->post('/admin/venues/'.VenueBooking::sole()->id, ['confirm' => true])->assertForbidden();
});

it('marks clubs active, at risk and inactive from their last activity', function () {
    $service = app(ClubStatusService::class);
    $today = CarbonImmutable::parse('2026-10-29');
    $club = $this->f['club'];
    $club->events()->delete();
    $club->posts()->delete();
    $club->meetings()->delete();

    $club->forceFill(['last_activity_at' => $today->subWeeks(3)])->save();
    expect($service->statusFor($club, $today))->toBe('active');
    $club->forceFill(['last_activity_at' => $today->subMonths(2)->subDay()])->save();
    expect($service->statusFor($club, $today))->toBe('at_risk');
    $club->forceFill(['last_activity_at' => $today->subMonths(4)])->save();
    expect($service->statusFor($club, $today))->toBe('inactive');

    $service->run($today);
    expect($club->fresh()->status)->toBe('inactive');
});
