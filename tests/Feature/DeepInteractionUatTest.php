<?php

use App\Enums\DocumentStatus;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\Event;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Ai\ReceiptReader;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Calendar\EventCalendar;
use App\Services\Membership\ReceiptReading;
use App\Services\Roles\StaffAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Random\Engine\Mt19937;
use Random\Randomizer;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', config('app.timezone')));
    config(['services.anthropic.key' => null]);
    $this->f = proposalFixture();
    $this->student = otherStudent();
});

dataset('deep student action orders', [11, 29, 71, 113, 211, 337, 503, 769]);

it('keeps one students many tasks consistent across two clubs events calendar feed profile and settings', function (int $seed) {
    $f = $this->f;
    $student = $this->student;
    $other = Club::create(['name' => 'Deep second club']);
    foreach ([$f['club'], $other] as $club) {
        ClubFee::create(['club_id' => $club->id, 'session_id' => $f['event']->session_id,
            'amount_sen' => 0, 'set_by' => $f['president']->id, 'approved_at' => now()]);
        $this->actingAs($student)->post('/clubs/'.$club->slug.'/join', ['acknowledged' => true])->assertSessionHas('success');
    }
    $f['event']->update(['status' => DocumentStatus::Approved, 'registration_open' => true,
        'members_only' => true, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)]);
    $second = Event::create(['club_id' => $other->id, 'session_id' => $f['event']->session_id,
        'name' => 'Deep second activity', 'status' => DocumentStatus::Approved, 'registration_open' => true,
        'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
    $post = $f['club']->posts()->create(['author_id' => $f['president']->id, 'type' => 'general', 'caption' => 'Deep activity announcement', 'published_at' => now()]);
    $tasks = (new Randomizer(new Mt19937($seed)))->shuffleArray(['follow', 'like', 'save', 'comment', 'theme', 'privacy', 'register-first', 'register-second', 'tour']);
    foreach ($tasks as $task) {
        $response = match ($task) {
            'follow' => $this->post('/clubs/'.$f['club']->slug.'/follow'),
            'like' => $this->post('/posts/'.$post->id.'/like'),
            'save' => $this->post('/posts/'.$post->id.'/save'),
            'comment' => $this->post('/posts/'.$post->id.'/comments', ['body' => 'I joined both clubs and will attend these activities.']),
            'theme' => $this->put('/me/theme', ['theme' => 'dark']),
            'privacy' => $this->post('/me', ['hidden_sections' => ['events', 'cca']]),
            'register-first' => $this->post('/events/'.$f['event']->slug.'/register'),
            'register-second' => $this->post('/events/'.$second->slug.'/register'),
            'tour' => $this->post('/me/tours', ['tour' => 'student', 'seen' => true]),
        };
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(session('error'), 'Seed '.$seed.' / '.$task)->toBeNull();
    }
    expect($student->memberships()->where('status', 'active')->count())->toBe(2)
        ->and($student->eventRegistrations()->count())->toBe(2)
        ->and($post->likers()->count())->toBe(1)->and($post->comments()->count())->toBe(1)
        ->and($student->savedPosts()->count())->toBe(1)->and($student->fresh()->theme)->toBe('dark')
        ->and($student->fresh()->hidden_sections)->toBe(['events', 'cca']);
    $this->get('/calendar?month=2026-11')->assertOk()->assertInertia(fn ($page) => $page
        ->has('events', 2)->where('events.0.registered', true)->where('events.1.registered', true));
    $this->get('/me')->assertOk()->assertInertia(fn ($page) => $page->has('upcoming', 2)->where('auth.user.theme', 'dark'));
    $this->get('/activities')->assertOk()->assertInertia(fn ($page) => $page->has('tickets', 2));
    $this->get('/p/'.$student->qr_token)->assertOk()->assertInertia(fn ($page) => $page
        ->where('profile.events', null)->where('profile.cca', null)->missing('profile.email'));
    $this->delete('/events/'.$second->slug.'/register')->assertSessionHas('success');
    $this->get('/me')->assertInertia(fn ($page) => $page->has('upcoming', 1));
    $ticket = $f['event']->registrations()->where('user_id', $student->id)->firstOrFail()->ensureTicket();
    $this->actingAs(otherStudent('DEEPOTHER', 'Deep Other Student'))->get('/tickets/'.$ticket->ticket_code)->assertForbidden();
    expect($student->memberships()->count())->toBe(2);
})->with('deep student action orders')->group('deep-uat');

dataset('deep hidden post interactions', function () {
    foreach (['student', 'committee', 'advisor', 'staff'] as $role) {
        foreach (['like', 'save', 'comments'] as $action) {
            yield "$role / $action" => [$role, $action];
        }
    }
});

it('prevents interactions with a post hidden by SLD', function (string $role, string $action) {
    $post = $this->f['club']->posts()->create(['author_id' => $this->f['president']->id,
        'type' => 'general', 'caption' => 'Moderated content', 'published_at' => now(), 'hidden_at' => now()]);
    $actor = match ($role) {
        'committee' => $this->f['president'],
        'advisor' => $this->f['advisor'],
        'staff' => User::where('email', 'advisor2.demo@qiu.edu.my')->firstOrFail(),
        default => $this->student,
    };
    $this->actingAs($actor)->get('/posts/'.$post->id)->assertNotFound();
    $response = $this->post('/posts/'.$post->id.'/'.$action, $action === 'comments' ? ['body' => 'Should not be accepted after moderation'] : []);
    expect($post->likers()->count())->toBe(0)->and($post->comments()->count())->toBe(0)->and($post->savers()->count())->toBe(0);
    $response->assertNotFound();
})->with('deep hidden post interactions')->group('deep-uat');

dataset('deep private event statuses', ['draft', 'in_review', 'returned', 'rejected']);

it('does not disclose a confidential event assignment through the public profile', function (string $status) {
    $event = $this->f['event'];
    $event->update(['status' => $status, 'name' => 'Confidential draft launch']);
    $committee = $event->committee()->where('position', 'President')->firstOrFail()->user;
    $this->get('/p/'.$committee->qr_token)->assertOk()->assertInertia(fn ($page) => $page
        ->where('profile.positions', fn ($positions) => collect($positions)->where('organisation', 'Confidential draft launch')->isEmpty()));
})->with('deep private event statuses')->group('deep-uat');

dataset('deep malformed query requests', [
    ['/search?q%5B%5D=hello'],
    ['/search?type%5B%5D=clubs'],
    ['/calendar?month%5B%5D=2026-11'],
    ['/calendar?month=2026-99'],
    ['/calendar?month=9999-99'],
    ['/calendar?month=0000-00'],
    ['/notifications?group%5B%5D=money'],
    ['/notifications?limit%5B%5D=30'],
    ['/admin/finance?q%5B%5D=payment'],
    ['/admin/roles?search%5B%5D=staff'],
]);

it('handles malformed browser query parameters without an internal server error', function (string $url) {
    $response = $this->actingAs(superAdmin())->getJson($url);
    expect($response->getStatusCode())->toBeLessThan(500);
})->with('deep malformed query requests')->group('deep-uat');

dataset('deep profile states', function () {
    foreach (['student', 'committee', 'advisor', 'staff', 'admin', 'superadmin'] as $role) {
        foreach (['light', 'dark', 'system'] as $theme) {
            yield "$role / $theme" => [$role, $theme];
        }
    }
});

it('keeps profile preferences per account across roles and rejects privilege injection', function (string $role, string $theme) {
    $actor = match ($role) {
        'committee' => $this->f['president'], 'advisor' => $this->f['advisor'],
        'staff' => User::where('email', 'advisor2.demo@qiu.edu.my')->firstOrFail(),
        'admin' => sldOfficer(), 'superadmin' => superAdmin(), default => $this->student,
    };
    $before = $actor->globalRoles();
    $this->actingAs($actor)->put('/me/theme', ['theme' => $theme, 'roles' => ['superadmin']])->assertSessionHasNoErrors();
    $this->post('/me/tours', ['tour' => 'student', 'seen' => true])->assertSessionHasNoErrors();
    $this->post('/me', ['hidden_sections' => ['cca'], 'roles' => ['superadmin'], 'email' => 'forged@qiu.edu.my'])->assertSessionHasNoErrors();
    expect($actor->fresh()->theme)->toBe($theme)->and($actor->fresh()->globalRoles())->toBe($before)
        ->and($this->student->fresh()->theme)->toBe($actor->is($this->student) ? $theme : 'system');
    $this->get('/me')->assertOk()->assertInertia(fn ($page) => $page->where('auth.user.theme', $theme));
})->with('deep profile states')->group('deep-uat');

it('leaves unseen notifications unread when a student opens only the first page', function () {
    for ($i = 0; $i < 35; $i++) {
        $this->student->notify(new DocumentNotification('Deep notification '.$i, 'Dummy update', '/activities'));
    }
    $this->actingAs($this->student)->get('/notifications')->assertOk()->assertInertia(fn ($page) => $page->has('items', 30)->where('hasMore', true));
    expect($this->student->unreadNotifications()->count())->toBe(5);
})->group('deep-uat');

it('requires renewed advisor review after signed proposal text is changed', function () {
    $f = $this->f;
    $engine = app(ApprovalEngine::class);
    $data = completeProposal($f);
    $engine->submit($f['document'], $f['president'], $data, signaturePng(), signatureStrokes());
    $engine->approve($engine->currentStep($f['document']->fresh()), $f['advisor'], signaturePng());
    $engine->returnToClub($engine->currentStep($f['document']->fresh()), sldOfficer(), 'Correct the objectives');
    $engine->submit($f['document']->fresh(), $f['president'], [...$data, 'objectives' => ['Different purpose', 'Changed purpose']], signaturePng(), signatureStrokes());
    expect($engine->currentStep($f['document']->fresh())->role->value)->toBe('advisor');
})->group('deep-uat');

it('preserves profile privacy choices when only the personal email setting is patched', function () {
    $this->student->update(['hidden_sections' => ['events', 'cca']]);
    $this->actingAs($this->student)->post('/me', ['personal_email' => 'deep.student@example.com'])->assertSessionHasNoErrors();
    expect($this->student->fresh()->hidden_sections)->toBe(['events', 'cca']);
})->group('deep-uat');

it('cannot file a report against a comment belonging to another post', function () {
    $club = $this->f['club'];
    $first = $club->posts()->create(['type' => 'general', 'caption' => 'First', 'published_at' => now()]);
    $second = $club->posts()->create(['type' => 'general', 'caption' => 'Second', 'published_at' => now()]);
    $comment = $second->comments()->create(['user_id' => $this->f['president']->id, 'body' => 'Belongs to the second post']);
    $this->actingAs($this->student)->post('/posts/'.$first->id.'/report', ['reason' => 'Wrong association', 'comment_id' => $comment->id])
        ->assertSessionHasErrors('comment_id');
})->group('deep-uat');

it('does not allow a club assignment removal route to delete an event assignment with the same numeric scope id', function () {
    $assignment = $this->f['event']->committee()->firstOrFail();
    // The scenario needs a club whose id equals the event assignment's scope id. A fresh SQLite database gives
    // that by chance (both are 1); on MySQL auto-increments are not reset between tests, so make one explicitly.
    $club = Club::find($assignment->scope_id) ?? Club::forceCreate(['id' => $assignment->scope_id, 'name' => 'Same Id Club', 'slug' => 'same-id-club']);
    expect($assignment->scope_id)->toBe($club->id);
    $this->actingAs(sldOfficer())->delete('/admin/clubs/'.$club->slug.'/assignments/'.$assignment->id)->assertNotFound();
    $this->assertDatabaseHas('role_assignments', ['id' => $assignment->id]);
})->group('deep-uat');

it('preserves the paid registration audit link when a student cancels and registers again', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'registration_open' => true,
        'fee_sen' => 1500, 'payment_account_name' => 'Deep Club QIU']);
    $student = $this->student;
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(
        new ReceiptReading(1500, today()->toDateString(), 'DEEPCANCEL1', 'Deep Club QIU', 0.99, $student->name),
    );
    $this->actingAs($student)->post('/events/'.$event->slug.'/register')->assertSessionHas('success');
    $this->post('/events/'.$event->slug.'/pay/receipt', ['receipt' => UploadedFile::fake()->image('paid.png')])->assertSessionHasNoErrors();
    $payment = MembershipPayment::where('user_id', $student->id)->firstOrFail();
    expect($payment->fresh()->status->isPaid())->toBeTrue();
    $registrationId = $payment->event_registration_id;
    $this->delete('/events/'.$event->slug.'/register')->assertSessionHas('success');
    $this->post('/events/'.$event->slug.'/register')->assertSessionHas('success');

    expect($payment->fresh()->event_registration_id)->toBe($registrationId);
    $this->assertDatabaseHas('event_registrations', ['id' => $registrationId]);
})->group('deep-uat');

it('escapes bare carriage returns in calendar fields instead of exposing injected content lines', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'name' => "Deep calendar\rATTENDEE:mailto:dummy@example.invalid"]);
    $ics = app(EventCalendar::class)->ics();
    expect(preg_match('/\r(?!\n)/', $ics))->toBe(0);
})->group('deep-uat');

it('revokes an existing SLD staff session when its staff account is disabled', function () {
    $sld = User::where('email', 'sld.officer.demo@qiu.edu.my')->firstOrFail();
    expect($sld->isSldAdmin())->toBeTrue();
    app(StaffAccounts::class)->update($sld->staff, ['name' => $sld->name, 'active' => false]);

    $this->actingAs($sld)->getJson('/admin')->assertForbidden();
})->group('deep-uat');
