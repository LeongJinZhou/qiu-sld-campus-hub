<?php

use App\Enums\DocumentStatus;
use App\Models\Club;
use App\Models\Post;
use App\Models\User;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Events\ProposalForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->f = proposalFixture());

function completeProposal(array $f, array $overrides = []): array
{
    $data = app(ProposalForm::class)->defaults($f['event']);

    return array_replace_recursive($data, [
        'summary' => 'Clean the beach.', 'venue' => 'Pantai Remis', 'introduction' => 'SDG 14.', 'eligibility' => 'All students',
        'nature' => ['environmental'], 'source_of_fund' => ['club'], 'objectives' => ['Clean', 'Learn', 'Bond'],
        'activities' => [['name' => 'Cleanup', 'description' => 'Pick litter']],
        'itinerary' => [['start' => '08:00', 'end' => '12:30', 'agenda' => 'Cleanup']],
        'budget' => ['revenue' => [['description' => 'Club fund', 'price_sen' => 50000, 'quantity' => 1]], 'expenses' => [['description' => 'Gloves', 'price_sen' => 500, 'quantity' => 40]]],
        'conclusion' => 'Good for all.',
    ], $overrides);
}

it('pre-fills locked fields and CCA from the event hours', function () {
    $this->actingAs($this->f['president'])->get("/events/{$this->f['event']->slug}/proposal")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Proposal')
            ->where('locked.to', 'Mr Nicholas Goh, COO')
            ->where('locked.from', 'Fixture Club')
            ->where('data.cca_participant', 4.5)
            ->where('missingCommittee', []));
});

it('keeps students outside the club out of the proposal', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("/events/{$this->f['event']->slug}/proposal")->assertForbidden();
});

it('saves a draft and syncs the event row', function () {
    $this->actingAs($this->f['president'])
        ->put("/events/{$this->f['event']->slug}/proposal", completeProposal($this->f, ['cca_participant' => 3]))
        ->assertSessionHasNoErrors();

    expect($this->f['event']->fresh()->cca_participant)->toBe(3.0)->and($this->f['event']->fresh()->venue)->toBe('Pantai Remis');
});

it('enforces the lead-time rule on submit', function () {
    $data = completeProposal($this->f, ['starts_at' => now()->addWeeks(2)->format('Y-m-d\TH:i'), 'ends_at' => now()->addWeeks(2)->addHours(3)->format('Y-m-d\TH:i')]);

    $this->actingAs($this->f['president'])
        ->post("/events/{$this->f['event']->slug}/proposal/submit", [...$data, 'signature' => signaturePng()])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, '1.5 months'));
});

it('submits, runs the chain over HTTP and lands in the feed', function () {
    $this->actingAs($this->f['president'])
        ->post("/events/{$this->f['event']->slug}/proposal/submit", [...completeProposal($this->f), 'signature' => signaturePng()])
        ->assertRedirect(route('documents.show', $this->f['document']));

    $step = app(ApprovalEngine::class)->currentStep($this->f['document']->fresh());
    $this->actingAs($this->f['advisor'])->get('/approvals')->assertInertia(fn (Assert $p) => $p->has('items', 1));
    $this->actingAs($this->f['advisor'])->post("/approvals/{$step->id}", ['decision' => 'approve', 'signature' => signaturePng()])->assertRedirect('/approvals');

    approveRemainingSteps($this->f['document']);

    expect($this->f['event']->fresh()->status)->toBe(DocumentStatus::Approved);
    $this->actingAs($this->f['president'])->get('/feed')
        ->assertInertia(fn (Assert $p) => $p->component('Feed/Index')->where('posts.data.0.event.name', 'Beach Cleanup'));
});

it('shows a public verification page without the signature image', function () {
    app(ApprovalEngine::class)->submit($this->f['document'], $this->f['president'], completeProposal($this->f), signaturePng());
    $code = $this->f['document']->signatures()->value('verification_code');

    $this->get("/verify/{$code}")->assertInertia(fn (Assert $p) => $p
        ->where('record.signer', $this->f['president']->name)
        ->where('record.position', 'President')
        ->missing('record.image_path'));
    $this->get('/verify/NOPE')->assertInertia(fn (Assert $p) => $p->where('record', null));
});

it('lets students register once the event is approved, not before', function () {
    $student = $this->f['president'];
    $this->actingAs($student)->post("/events/{$this->f['event']->slug}/register")->assertSessionHas('error');

    $this->f['event']->update(['status' => DocumentStatus::Approved, 'registration_open' => true]);
    $this->actingAs($student)->post("/events/{$this->f['event']->slug}/register")->assertSessionHas('success');

    expect($this->f['event']->registrations()->count())->toBe(1);
});

it('orders the feed: pinned, then followed clubs, then newest; hides moderated posts', function () {
    $other = Club::create(['name' => 'Other Club']);
    $viewer = $this->f['president'];
    $old = Post::create(['club_id' => $this->f['club']->id, 'type' => 'general', 'caption' => 'followed old', 'published_at' => now()->subDays(3)]);
    $new = Post::create(['club_id' => $other->id, 'type' => 'general', 'caption' => 'newest', 'published_at' => now()]);
    $pinned = Post::create(['club_id' => $other->id, 'type' => 'general', 'caption' => 'pinned', 'published_at' => now()->subWeek(), 'pinned_at' => now()]);
    Post::create(['club_id' => $other->id, 'type' => 'general', 'caption' => 'hidden', 'published_at' => now(), 'hidden_at' => now()]);
    $this->f['club']->followers()->attach($viewer->id);

    $this->actingAs($viewer)->get('/feed')->assertInertia(fn (Assert $p) => $p
        ->where('posts.data.0.caption', 'pinned')
        ->where('posts.data.1.caption', 'followed old')
        ->where('posts.data.2.caption', 'newest')
        ->has('posts.data', 3));
});

it('toggles likes and saves, and accepts comments and reports', function () {
    $post = Post::create(['club_id' => $this->f['club']->id, 'type' => 'general', 'published_at' => now()]);
    $user = $this->f['president'];

    $this->actingAs($user)->post("/posts/{$post->id}/like");
    $this->actingAs($user)->post("/posts/{$post->id}/save");
    $this->actingAs($user)->post("/posts/{$post->id}/comments", ['body' => 'Nice!']);
    $this->actingAs($user)->post("/posts/{$post->id}/report", ['reason' => 'Spam']);

    expect($post->likers()->count())->toBe(1)->and($post->savers()->count())->toBe(1)
        ->and($post->comments()->count())->toBe(1)->and($post->reports()->count())->toBe(1);

    $this->actingAs($user)->post("/posts/{$post->id}/like");
    expect($post->likers()->count())->toBe(0);
});

it('only lets the club and SLD publish, and only SLD moderate', function () {
    Storage::fake('public');
    $outsider = User::factory()->create();
    $photo = UploadedFile::fake()->image('a.jpg');

    $this->actingAs($outsider)->post("/clubs/{$this->f['club']->slug}/posts", ['type' => 'general', 'photos' => [$photo]])->assertForbidden();
    $this->actingAs($this->f['president'])->post("/clubs/{$this->f['club']->slug}/posts", ['type' => 'general', 'photos' => [$photo]])->assertRedirect('/feed');

    $post = Post::latest('id')->first();
    $this->actingAs($this->f['president'])->post("/admin/moderation/posts/{$post->id}/pin")->assertForbidden();
    $this->actingAs(sldOfficer())->post("/admin/moderation/posts/{$post->id}/pin");
    expect($post->fresh()->pinned_at)->not->toBeNull();
});
