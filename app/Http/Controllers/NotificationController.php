<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Notifications\ActionItems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /** "Action needed" first (stays until done), then the latest notifications, which are marked read. */
    public function __invoke(Request $request, ActionItems $actions): Response
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(50)->get();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return Inertia::render('Notifications/Index', [
            'actions' => $actions->for($user),
            'items' => $items->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'category' => $n->data['category'] ?? 'update',
                'at' => $n->created_at?->diffForHumans(),
                'unread' => $n->read_at === null,
            ]),
            'mutable' => User::MUTABLE_NOTIFICATIONS,
            'muted' => $user->notification_mutes ?? [],
        ]);
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
