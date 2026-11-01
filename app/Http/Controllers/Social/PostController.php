<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StorePostRequest;
use App\Models\Club;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\Social\PostingRules;
use App\Services\Social\PostPresenter;
use App\Services\Social\SocialNotifier;
use App\Services\Uploads\ImageUpload;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Post $post, PostPresenter $presenter): Response
    {
        abort_if($post->hidden_at !== null && ! $request->user()->isSldAdmin(), 404);
        $post = $presenter->withCardData(Post::whereKey($post->id), $request->user())->firstOrFail();

        return Inertia::render('Feed/Post', [
            'post' => $presenter->card($post),
            'comments' => $post->comments()->whereNull('hidden_at')->with('user')->oldest()->get()->map(fn (Comment $c) => $presenter->comment($c)),
            // Who liked it, newest first: everyone sees names and photos only (as on the public profile).
            'likers' => $post->likers()->orderByPivot('created_at', 'desc')->limit(100)->get()
                ->map(fn (User $u) => ['name' => $u->name, 'photo_url' => $u->photoUrl(), 'url' => route('profile.public', $u->qr_token)]),
        ]);
    }

    /** Club posts and post-event recaps with a photo carousel. */
    public function store(StorePostRequest $request, Club $club, SocialNotifier $notifier): RedirectResponse
    {
        $post = $club->posts()->create([
            ...$request->safe()->only('type', 'caption', 'event_id'),
            'author_id' => $request->user()->id,
            'published_at' => now(),
        ]);
        foreach ($request->file('photos') as $i => $photo) {
            $post->media()->create(['path' => app(ImageUpload::class)->store($photo, "posts/{$club->id}"), 'sort' => $i]);
        }
        $notifier->tellFollowers($post, $request->user());

        return to_route('feed')->with('success', 'Posted.');
    }

    public function like(Request $request, Post $post, SocialNotifier $notifier): RedirectResponse
    {
        $this->assertInteractive($post);
        if ($post->likers()->toggle($request->user()->id)['attached'] !== [] && $post->club) {
            $notifier->notify($post->club, $request->user(), 'likes', 'your post', route('posts.show', $post), "likes:post:{$post->id}");
        }

        return back();
    }

    public function save(Request $request, Post $post, SocialNotifier $notifier): RedirectResponse
    {
        $this->assertInteractive($post);
        if ($post->savers()->toggle($request->user()->id)['attached'] !== [] && $post->club) {
            $notifier->notify($post->club, $request->user(), 'saves', 'your post', route('posts.show', $post), "saves:post:{$post->id}");
        }

        return back();
    }

    public function comment(Request $request, Post $post, SocialNotifier $notifier, PostingRules $rules): RedirectResponse
    {
        $this->assertInteractive($post);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, Closure $fail) use ($request, $post, $rules): void {
            if (is_string($value) && ($problem = $rules->commentProblem($request->user(), $post, $value))) {
                $fail($problem);
            }
        }]]);
        $post->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        if ($post->club) {
            $notifier->notify($post->club, $request->user(), 'comments', 'your post', route('posts.show', $post), "comments:post:{$post->id}");
        }

        return back();
    }

    public function report(Request $request, Post $post): RedirectResponse
    {
        $this->assertInteractive($post);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            // Only a comment on this same post can be reported with it.
            'comment_id' => ['nullable', 'integer', Rule::exists('comments', 'id')->where('post_id', $post->id)],
        ]);
        $post->reports()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Thanks — SLD will review it.');
    }

    /** One rule for every action on a post: hidden by SLD or not yet published means not found, for everyone. */
    private function assertInteractive(Post $post): void
    {
        abort_unless(Post::query()->visible()->whereKey($post->id)->exists(), 404);
    }
}
