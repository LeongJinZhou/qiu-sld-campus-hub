<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventDocumentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Post-event document checklist: finance + receipts within 1 week, everything else within 2 weeks. */
class EventDocumentController extends Controller
{
    public function index(Event $event): Response
    {
        Gate::authorize('organises', $event);

        return Inertia::render('Events/Documents', [
            'event' => $event->only('slug', 'name'),
            'items' => $event->documentItems()->orderBy('due_on')->orderBy('id')->get()->map(fn (EventDocumentItem $i) => [
                'id' => $i->id,
                'label' => $i->label(),
                'due_on' => $i->due_on->toFormattedDateString(),
                'overdue' => ! $i->submitted_at && $i->due_on->endOfDay()->isPast(),
                'submitted_at' => $i->submitted_at?->toFormattedDateString(),
                'file_name' => $i->file_path ? basename($i->file_path) : null,
            ]),
        ]);
    }

    public function upload(Request $request, Event $event, EventDocumentItem $item): RedirectResponse
    {
        Gate::authorize('organises', $event);
        abort_unless($item->event_id === $event->id, 404);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png,mp4,mov', 'max:51200']]);

        if ($item->file_path) {
            Storage::disk('local')->delete($item->file_path);
        }
        $item->update([
            'file_path' => $data['file']->store("event-documents/{$event->id}", 'local'),
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        return back()->with('success', "{$item->label()} uploaded.");
    }

    /** Files live on the private disk; only organisers can download. */
    public function download(Event $event, EventDocumentItem $item): StreamedResponse
    {
        Gate::authorize('organises', $event);
        abort_unless($item->event_id === $event->id && $item->file_path, 404);

        return Storage::disk('local')->download($item->file_path);
    }
}
