<?php

use App\Enums\DocumentStatus;
use App\Models\ClubFee;
use App\Models\Post;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SocialNotification;
use App\Services\Membership\CashHandovers;
use App\Services\Membership\MembershipService;
use App\Services\Notifications\ActionItems;
use App\Services\Reminders\Reminders;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    $this->student = Student::where('status', 'active')->whereNotIn('user_id', $this->club->roleAssignments()->pluck('user_id'))->whereNotNull('user_id')->first()->user;
});

it('tells the club committee about likes, grouped, and respects mutes', function () {
    $post = Post::create(['club_id' => $this->club->id, 'author_id' => $this->treasurer->id, 'type' => 'general', 'caption' => 'Hi', 'published_at' => now()]);
    $fans = User::factory()->count(3)->create();
    $this->f['president']->forceFill(['notification_mutes' => ['likes']])->save();

    foreach ($fans as $fan) {
        $this->actingAs($fan)->post("/posts/{$post->id}/like");
    }
    $secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $notes = $secretary->notifications()->where('type', SocialNotification::class)->get();
    expect($notes)->toHaveCount(1)
        ->and($notes->first()->data['title'])->toBe("{$fans[2]->name} and 2 others liked your post")
        ->and($this->f['president']->notifications()->where('type', SocialNotification::class)->count())->toBe(0);

    $this->actingAs($secretary)->put('/notifications/mutes', ['muted' => ['comments', 'nonsense']])->assertSessionHasErrors();
    $this->actingAs($secretary)->put('/notifications/mutes', ['muted' => ['comments']])->assertSessionHas('success');
    expect($secretary->fresh()->hasMuted('comments'))->toBeTrue();
});

it('keeps unfinished payments and handovers under Action needed, and chases them', function () {
    app(ClubFee::class)->create(['club_id' => $this->club->id, 'session_id' => app(SessionResolver::class)->current()->id, 'amount_sen' => 1000, 'set_by' => $this->treasurer->id, 'approved_at' => now()]);
    $membership = app(MembershipService::class)->join($this->club, $this->student, true);
    $actions = app(ActionItems::class);
    expect(collect($actions->for($this->student))->pluck('kind'))->toContain('payment');

    $h = app(CashHandovers::class)->request($membership, $this->treasurer);
    expect(collect($actions->for($this->treasurer))->firstWhere('kind', 'handover')['body'])->toContain('Accept or decline')
        ->and(collect($actions->for($this->student))->pluck('kind')->all())->toBe(['handover']);

    $this->travel(2)->hours();
    $sent = app(Reminders::class)->run(CarbonImmutable::today());
    expect($sent)->toBeGreaterThanOrEqual(2)
        ->and($this->student->notifications()->where('data->title', 'It seems you have half-way done this')->exists())->toBeTrue()
        ->and(app(Reminders::class)->run(CarbonImmutable::today()))->toBe(0); // once a day per item

    $this->actingAs($this->student)->get('/notifications')->assertInertia(fn ($p) => $p->has('actions', 1)->where('actions.0.kind', 'handover'));
});

it('leaves an in-app reminder when someone leaves a payment half-way, at most every 30 minutes', function () {
    $this->actingAs($this->student)->postJson('/notifications/left-halfway', ['title' => 'Your cash payment', 'path' => '/handovers/1'])->assertNoContent();
    $this->actingAs($this->student)->postJson('/notifications/left-halfway', ['title' => 'Your cash payment', 'path' => '/handovers/1'])->assertNoContent();
    expect($this->student->notifications()->count())->toBe(1);
    $this->actingAs($this->student)->postJson('/notifications/left-halfway', ['title' => 'x', 'path' => 'https://evil.example'])->assertUnprocessable();
});

it('sends outdoor-event registrants straight to the indemnity form', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'is_outdoor' => true, 'registration_open' => true]);
    $this->actingAs($this->student)->post("/events/{$event->slug}/register")->assertRedirect(route('events.indemnity', $event));
    expect(collect(app(ActionItems::class)->for($this->student))->pluck('kind'))->toContain('indemnity');
});
