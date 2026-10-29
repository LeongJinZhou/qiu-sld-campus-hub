<?php

use App\Enums\DocumentStatus;
use App\Models\Student;
use App\Notifications\DocumentNotification;
use App\Services\Events\AttendanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->event = $this->f['event'];
    $this->event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true, 'cca_participant' => 4.5]);
    $this->student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->travelTo($this->event->starts_at);
    app(AttendanceService::class)->addManually($this->event, $this->student, $this->f['president']);
});

it('builds the full profile: stats, events attended and CCA record', function () {
    $this->actingAs($this->student)->get('/me')->assertInertia(fn (Assert $p) => $p
        ->component('Profile/Me')
        ->where('profile.stats.events', 1)
        ->where('profile.stats.cca_hours', 4.5)
        ->where('profile.events.0.name', 'Beach Cleanup')
        ->where('profile.cca.total', 4.5)
        ->where('profile.status', 'active'));
});

it('shows club positions with logos and the current position', function () {
    $this->actingAs($this->f['president'])->get('/me')->assertInertia(fn (Assert $p) => $p
        ->where('profile.clubs.0.name', 'Fixture Club')
        ->where('profile.clubs.0.current_position', 'President')
        ->where('profile.positions.0.kind', 'club'));
});

it('hides events and CCA on the public profile when the student turns them off', function () {
    $this->student->update(['hidden_sections' => ['events', 'cca']]);

    $this->get("/p/{$this->student->qr_token}")->assertInertia(fn (Assert $p) => $p
        ->where('profile.events', null)
        ->where('profile.cca', null)
        ->where('profile.stats.events', null)
        ->where('profile.stats.cca_hours', null)
        ->missing('profile.email'));
});

it('keeps profile settings when only the photo changes', function () {
    Storage::fake('public');
    $this->student->update(['hidden_sections' => ['cca'], 'personal_email' => 'me@gmail.com']);

    $this->actingAs($this->student)->post('/me', ['photo' => UploadedFile::fake()->image('me.jpg')])->assertSessionHasNoErrors();

    $fresh = $this->student->fresh();
    expect($fresh->avatar_path)->not->toBeNull()
        ->and($fresh->hidden_sections)->toBe(['cca'])
        ->and($fresh->personal_email)->toBe('me@gmail.com');
});

it('lists notifications, shows the unread badge and marks them read', function () {
    $this->student->notify(new DocumentNotification('Proposal approved', 'All signed', '/feed'));

    $this->actingAs($this->student)->get('/feed')->assertInertia(fn (Assert $p) => $p->where('nav.unread', 1));
    $this->actingAs($this->student)->get('/notifications')->assertInertia(fn (Assert $p) => $p
        ->component('Notifications/Index')->where('items.0.title', 'Proposal approved'));
    expect($this->student->unreadNotifications()->count())->toBe(0);
});
