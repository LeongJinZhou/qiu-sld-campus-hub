<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Post;
use App\Services\Social\FeedQuery;
use App\Services\Social\PostPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request, FeedQuery $feed, PostPresenter $presenter): Response
    {
        $user = $request->user();
        $page = $feed->feed($user);

        return Inertia::render('Feed/Index', [
            'stories' => $feed->storyBar($user),
            'posts' => Inertia::scroll(fn () => $page->through(fn (Post $p) => $presenter->card($p))),
            'openCreate' => $request->boolean('create'),
            'publishable' => Club::orderBy('name')->get()->filter(fn (Club $c) => $user->can('publish', $c))
                ->map(fn (Club $c) => ['slug' => $c->slug, 'name' => $c->name])->values(),
        ]);
    }
}
