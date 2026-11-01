<?php

use App\Models\Post;
use App\Models\Student;
use App\Notifications\SocialNotification;
use App\Services\Social\FeedQuery;
use App\Support\ContentFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->president = $this->f['president'];
    $this->student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
});

$post = fn (object $t, array $data = []) => $t->actingAs($t->president)->post("/clubs/{$t->club->slug}/posts", [
    'type' => 'general', 'caption' => 'Join our beach cleanup this Saturday!', 'photos' => [UploadedFile::fake()->image('a.jpg')], ...$data,
]);

it('refuses bad words and insults, however they are spelt', function () {
    expect(ContentFilter::offences('you are so b0d0h'))->toBe(['bodoh'])
        ->and(ContentFilter::offences('f u c k this'))->toBe(['fuck'])
        ->and(ContentFilter::isClean('Great beach trash pickup, class!'))->toBeTrue();
});

it('stops a committee posting the same thing twice or flooding one event', function () use ($post) {
    $post($this)->assertSessionHasNoErrors();
    $post($this)->assertSessionHasErrors('caption');
    $post($this, ['caption' => 'What a cibai event'])->assertSessionHasErrors('caption');

    $event = $this->f['event'];
    $post($this, ['type' => 'recap', 'event_id' => $event->id, 'caption' => 'Recap one'])->assertSessionHasNoErrors();
    $post($this, ['type' => 'recap', 'event_id' => $event->id, 'caption' => 'Recap two, more photos'])->assertSessionHasErrors('event_id');
    expect(Post::where('club_id', $this->club->id)->count())->toBe(2);
});

it('blocks rude and repeated comments', function () {
    $p = $this->club->posts()->create(['type' => 'general', 'caption' => 'Hi', 'published_at' => now()]);

    $this->actingAs($this->student)->post("/posts/{$p->id}/comments", ['body' => 'so bodoh'])->assertSessionHasErrors('body');
    $this->actingAs($this->student)->post("/posts/{$p->id}/comments", ['body' => 'Nice!'])->assertSessionHasNoErrors();
    $this->actingAs($this->student)->post("/posts/{$p->id}/comments", ['body' => 'nice'])->assertSessionHasErrors('body');
});

it('tells followers about new posts, and the committee about likes and comments', function () use ($post) {
    $this->club->followers()->attach($this->student->id);

    $post($this)->assertSessionHasNoErrors();
    $p = Post::latest('id')->first();
    expect($this->student->notifications()->first()->data)->toMatchArray(['category' => 'club_posts', 'title' => "{$this->club->name} shared a new post"]);

    $this->actingAs($this->student)->post("/posts/{$p->id}/like");
    $this->actingAs($this->student)->post("/posts/{$p->id}/comments", ['body' => 'See you there']);
    expect($this->president->notifications()->pluck('data')->pluck('category')->all())->toContain('likes', 'comments');

    $this->actingAs($this->president)->get("/posts/{$p->id}")->assertInertia(fn (Assert $page) => $page
        ->where('likers.0.name', $this->student->name)->where('comments.0.user', $this->student->name));

    $this->student->update(['notification_mutes' => ['club_posts']]);
    $this->travel(2)->days();
    $post($this, ['caption' => 'Another update'])->assertSessionHasNoErrors();
    expect($this->student->notifications()->where('type', SocialNotification::class)->count())->toBe(1);
});

it('shows only the last 7 days in full and folds older posts by fortnight, month and year', function () {
    $make = fn ($at) => $this->club->posts()->create(['type' => 'general', 'caption' => 'x', 'published_at' => $at, 'created_at' => $at]);
    $make(now()->subDays(2));
    $make(now()->subDays(10));
    $make(now()->subDays(40));
    $make(now()->subYears(1));

    $this->actingAs($this->student)->get('/feed')->assertInertia(fn (Assert $page) => $page
        ->has('posts.data', 1)
        ->where('archive.0.key', '14d')->where('archive.0.count', 1)
        ->where('archive.1.key', now()->subDays(40)->year === now()->year ? 'm:'.now()->subDays(40)->format('Y-m') : 'y:'.now()->subYear()->year)
        ->where('archive.2.key', 'y:'.now()->subYear()->year));

    expect(app(FeedQuery::class)->bucket($this->student, '14d'))->toHaveCount(1)
        ->and(app(FeedQuery::class)->bucket($this->student, 'y:'.now()->subYear()->year))->toHaveCount(1)
        ->and(app(FeedQuery::class)->bucket($this->student, 'nonsense'))->toHaveCount(0);
});
