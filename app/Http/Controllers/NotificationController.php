<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Notifications\ActionItems;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public const GROUPS = ['social' => 'Club activity', 'money' => 'Payments & finance', 'documents' => 'Approvals & documents', 'events' => 'Events & tickets', 'other' => 'Other'];

    private const SOCIAL = ['likes', 'comments', 'saves', 'follows', 'club_posts'];

    /** "Action needed" first (stays until done), then notifications by group (filter, load more), marked read. */
    public function __invoke(Request $request, ActionItems $actions): Response
    {
        $user = $request->user();
        $group = QueryInput::oneOf($request, 'group', array_keys(self::GROUPS));
        $limit = QueryInput::int($request, 'limit', 30, 30, 500);
        $all = $user->notifications()->latest()->limit(500)->get();
        $filtered = $group ? $all->filter(fn (DatabaseNotification $n) => self::groupOf($n) === $group)->values() : $all;
        $items = $filtered->take($limit);
        // Only what is on screen counts as read; older unseen ones stay unread until shown ("Show older").
        $user->unreadNotifications()->whereKey($items->modelKeys())->update(['read_at' => now()]);

        return Inertia::render('Notifications/Index', [
            'actions' => $actions->for($user),
            'items' => $items->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'category' => $n->data['category'] ?? 'update',
                'group' => self::groupOf($n),
                'at' => $n->created_at?->diffForHumans(),
                'unread' => $n->read_at === null,
            ]),
            'hasMore' => $filtered->count() > $limit,
            'limit' => $limit,
            'group' => $group,
            'groups' => collect(self::GROUPS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label, 'count' => $all->filter(fn ($n) => self::groupOf($n) === $key)->count()])->values(),
            'total' => $all->count(),
            'mutable' => User::MUTABLE_NOTIFICATIONS,
            'muted' => $user->notification_mutes ?? [],
        ]);
    }

    /** Clear one notification. */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return back();
    }

    /** Clear all notifications, or one group (e.g. all club activity). Action needed items are not affected. */
    public function clear(Request $request): RedirectResponse
    {
        $group = $request->validate(['group' => ['nullable', Rule::in(array_keys(self::GROUPS))]])['group'] ?? null;
        $ids = $request->user()->notifications()->get()->filter(fn (DatabaseNotification $n) => $group === null || self::groupOf($n) === $group)->modelKeys();
        $request->user()->notifications()->whereKey($ids)->delete();

        return back()->with('success', count($ids).' notification'.(count($ids) === 1 ? '' : 's').' cleared.');
    }

    /** Sorts a notification into a group by its type, or by what its title is about. */
    public static function groupOf(DatabaseNotification $n): string
    {
        $category = $n->data['category'] ?? null;
        if (in_array($category, self::SOCIAL, true)) {
            return 'social';
        }
        $text = mb_strtolower(($n->data['title'] ?? '').' '.($category ?? ''));

        return match (true) {
            (bool) preg_match('/pay|paid|fee|cash|receipt|purchase|claim|transfer|withdraw|refund|finance|money|bank/', $text) => 'money',
            (bool) preg_match('/approv|proposal|return|reject|submit|sign|document|minutes|report|agm|election|vote/', $text) => 'documents',
            (bool) preg_match('/event|ticket|indemnity|registered|attendance|helper/', $text) => 'events',
            default => 'other',
        };
    }

    /** Mute or unmute likes, comments, saves or follows. */
    public function mutes(Request $request): RedirectResponse
    {
        $data = $request->validate(['muted' => ['present', 'array'], 'muted.*' => [Rule::in(array_keys(User::MUTABLE_NOTIFICATIONS))]]);
        $request->user()->forceFill(['notification_mutes' => array_values(array_unique($data['muted']))])->save();

        return back()->with('success', 'Notification settings saved.');
    }

    /**
     * Sent by the page when someone leaves a payment or form half-way (home button, app switch): an in-app
     * reminder that links straight back, at most once every 30 minutes per page.
     */
    public function leftHalfway(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'path' => ['required', 'string', 'max:300', 'starts_with:/']]);
        $key = "left:{$request->user()->id}:".sha1($data['path']);
        if (cache()->add($key, true, now()->addMinutes(30))) {
            $request->user()->notify(new DocumentNotification(
                'It seems you have half-way done this',
                "{$data['title']} is not finished yet. Tap to continue where you left off.",
                url($data['path']),
            ));
        }

        return response()->noContent();
    }
}
