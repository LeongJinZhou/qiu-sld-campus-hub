<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /** Lists the latest notifications and marks them read. */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(50)->get();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return Inertia::render('Notifications/Index', [
            'items' => $items->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? '',
                'body' => $n->data['body'] ?? '',
                'url' => $n->data['url'] ?? null,
                'at' => $n->created_at?->diffForHumans(),
                'unread' => $n->read_at === null,
            ]),
        ]);
    }
}
