<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostReport;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** SLD pins and moderates feed content. */
class ModerationController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Moderation/Index', [
            'reports' => PostReport::with(['post.club', 'comment', 'user'])->whereNull('resolved_at')->latest()->get()->map(fn (PostReport $r) => [
                'id' => $r->id,
                'reason' => $r->reason,
                'reporter' => $r->user->name,
                'at' => $r->created_at?->diffForHumans(),
                'post' => ['id' => $r->post->id, 'caption' => $r->post->caption, 'club' => $r->post->club?->name, 'hidden' => $r->post->hidden_at !== null],
                'comment' => $r->comment ? ['id' => $r->comment->id, 'body' => $r->comment->body, 'hidden' => $r->comment->hidden_at !== null] : null,
            ]),
            'recent' => Post::with('club')->latest('published_at')->limit(30)->get()->map(fn (Post $p) => [
                'id' => $p->id,
                'caption' => $p->caption,
                'club' => $p->club?->name,
                'type' => $p->type,
                'pinned' => $p->pinned_at !== null,
                'hidden' => $p->hidden_at !== null,
            ]),
        ]);
    }

    public function togglePin(Post $post): RedirectResponse
    {
        $post->update(['pinned_at' => $post->pinned_at ? null : now()]);
        $this->audit->log($post->pinned_at ? 'post.pinned' : 'post.unpinned', $post);

        return back();
    }

    public function toggleHidePost(Post $post): RedirectResponse
    {
        $post->update(['hidden_at' => $post->hidden_at ? null : now()]);
        $this->audit->log($post->hidden_at ? 'post.hidden' : 'post.restored', $post);

        return back();
    }

    public function toggleHideComment(Comment $comment): RedirectResponse
    {
        $comment->update(['hidden_at' => $comment->hidden_at ? null : now()]);
        $this->audit->log($comment->hidden_at ? 'comment.hidden' : 'comment.restored', $comment);

        return back();
    }

    public function resolve(PostReport $report): RedirectResponse
    {
        $report->update(['resolved_at' => now()]);

        return back();
    }
}
