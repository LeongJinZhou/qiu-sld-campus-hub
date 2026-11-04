<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\Post;
use App\Services\Meetings\MeetingVisibility;
use App\Services\Social\FeedQuery;
use App\Services\Social\PostPresenter;
use App\Support\QueryInput;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request, FeedQuery $feed, PostPresenter $presenter, MeetingVisibility $meetings): Response
    {
        $user = $request->user();
        $page = $feed->feed($user);

        return Inertia::render('Feed/Index', [
            'stories' => $feed->storyBar($user),
            'posts' => Inertia::scroll(fn () => $page->through(fn (Post $p) => $presenter->card($p))),
            'archive' => fn () => $feed->archive(),
            // Upcoming meetings of the clubs the viewer runs, belongs to or follows (committee meetings for the committee only).
            'meetings' => fn () => $meetings->query($user)->where('ends_at', '>=', now())->where('starts_at', '<=', now()->addDays(14))
                ->orderBy('starts_at')->limit(6)->get()->map(fn (Meeting $m) => $meetings->card($m, $user)),
            // One folded group, opened on request (?group=m:2026-10).
            'group' => Inertia::optional(fn () => [
                'key' => QueryInput::text($request, 'group', 12),
                'posts' => $feed->bucket($user, QueryInput::text($request, 'group', 12))->map(fn (Post $p) => $presenter->card($p))->all(),
            ]),
            'openCreate' => $request->boolean('create'),
            'publishable' => Club::orderBy('name')->get()->filter(fn (Club $c) => $user->can('publish', $c))
                ->map(fn (Club $c) => ['slug' => $c->slug, 'name' => $c->name])->values(),
        ]);
    }
}
