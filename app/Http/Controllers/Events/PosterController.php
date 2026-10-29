<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StorePostRequest;
use App\Models\Event;
use App\Services\Events\PosterRules;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PosterController extends Controller
{
    public function edit(Event $event): Response
    {
        Gate::authorize('update', $event);

        return Inertia::render('Events/Poster', [
            'event' => [
                ...$event->only('slug', 'name', 'summary', 'venue', 'cca_participant'),
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'poster_url' => $event->posterUrl(),
                'club' => ['name' => $event->club->name, 'logo_url' => $event->club->logoUrl(), 'instagram' => $event->club->instagram_handle],
            ],
            'checklist' => PosterRules::CHECKLIST,
            'ticked' => $event->poster_checklist ?? [],
        ]);
    }

    public function update(Request $request, Event $event, PosterRules $rules): RedirectResponse
    {
        Gate::authorize('update', $event);
        $data = $request->validate([
            'poster' => ['required', ...StorePostRequest::IMAGE_RULES],
            'checklist' => ['required', 'array'],
            'checklist.*' => ['boolean'],
        ]);
        $ticked = array_intersect_key(array_map('boolval', $data['checklist']), PosterRules::CHECKLIST);
        if ($missing = $rules->missing($ticked)) {
            return back()->with('error', 'Confirm the poster has: '.implode('; ', $missing).'.');
        }

        if ($event->poster_path) {
            Storage::disk('public')->delete($event->poster_path);
        }
        $event->update(['poster_path' => app(ImageUpload::class)->store($data['poster'], "posters/{$event->club_id}"), 'poster_checklist' => $ticked]);

        return to_route('events.show', $event)->with('success', 'Poster saved. It appears on the feed post once the proposal is approved.');
    }
}
