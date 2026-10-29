<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StorePostRequest;
use App\Models\Club;
use App\Models\Comment;
use App\Models\Post;
use App\Services\Social\PostPresenter;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    /** Club posts and post-event recaps with a photo carousel. */
    public function store(StorePostRequest $request, Club $club): RedirectResponse
    {
        $post = $club->posts()->create([
            ...$request->safe()->only('type', 'caption', 'event_id'),
            'author_id' => $request->user()->id,
            'published_at' => now(),
        ]);
        foreach ($request->file('photos') as $i => $photo) {
            $post->media()->create(['path' => app(ImageUpload::class)->store($photo, "posts/{$club->id}"), 'sort' => $i]);
        }

        return to_route('feed')->with('success', 'Posted.');
    }

    public function like(Request $request, Post $post): RedirectResponse
    {
        $post->likers()->toggle($request->user()->id);

        return back();
    }

    public function save(Request $request, Post $post): RedirectResponse
    {
        $post->savers()->toggle($request->user()->id);

        return back();
    }

    public function comment(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $post->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return back();
    }

    public function report(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'comment_id' => ['nullable', 'exists:comments,id'],
        ]);
        $post->reports()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('success', 'Thanks — SLD will review it.');
    }
}
