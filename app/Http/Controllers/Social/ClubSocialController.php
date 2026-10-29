<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\StorePostRequest;
use App\Models\Album;
use App\Models\Club;
use App\Models\ClubFaq;
use App\Models\Highlight;
use App\Models\Story;
use App\Services\Time\SessionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Follow, stories, highlights, gallery and FAQ for a club. Publishing is limited to the club's people and SLD. */
class ClubSocialController extends Controller
{
    public function follow(Request $request, Club $club): RedirectResponse
    {
        $club->followers()->toggle($request->user()->id);

        return back();
    }

    public function storeStory(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('publish', $club);
        $data = $request->validate(['photo' => ['required', ...StorePostRequest::IMAGE_RULES]]);
        $club->stories()->create([
            'author_id' => $request->user()->id,
            'media_path' => $data['photo']->store("stories/{$club->id}", 'public'),
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ]);

        return back()->with('success', 'Story shared for 24 hours.');
    }

    /** Keeps a copy of a story in a highlight so it outlives the 24 hours. */
    public function highlightStory(Request $request, Club $club, Story $story): RedirectResponse
    {
        Gate::authorize('publish', $club);
        abort_unless($story->club_id === $club->id, 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:40']]);

        $highlight = $club->highlights()->firstOrCreate(['title' => $data['title']], ['sort' => $club->highlights()->count()]);
        $copy = "highlights/{$club->id}/".basename($story->media_path);
        Storage::disk('public')->copy($story->media_path, $copy);
        $highlight->items()->create(['media_path' => $copy, 'sort' => $highlight->items()->count()]);

        return back()->with('success', "Added to “{$highlight->title}”.");
    }

    public function destroyHighlight(Club $club, Highlight $highlight): RedirectResponse
    {
        Gate::authorize('publish', $club);
        abort_unless($highlight->club_id === $club->id, 404);
        $highlight->delete();

        return back();
    }

    public function storeAlbum(Request $request, Club $club, SessionResolver $sessions): RedirectResponse
    {
        Gate::authorize('publish', $club);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('club_id', $club->id)],
            'photos' => ['required', 'array', 'min:1', 'max:30'],
            'photos.*' => StorePostRequest::IMAGE_RULES,
        ]);
        $album = $club->albums()->create([
            'title' => $data['title'],
            'event_id' => $data['event_id'] ?? null,
            'session_id' => $sessions->current()->id,
        ]);
        $this->addPhotos($request, $album);

        return back()->with('success', 'Album created.');
    }

    public function addAlbumPhotos(Request $request, Club $club, Album $album): RedirectResponse
    {
        Gate::authorize('publish', $club);
        abort_unless($album->club_id === $club->id, 404);
        $request->validate(['photos' => ['required', 'array', 'min:1', 'max:30'], 'photos.*' => StorePostRequest::IMAGE_RULES]);
        $this->addPhotos($request, $album);

        return back()->with('success', 'Photos added.');
    }

    public function storeFaq(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('publish', $club);
        $club->faqs()->create([
            ...$request->validate(['question' => ['required', 'string', 'max:255'], 'answer' => ['required', 'string', 'max:2000']]),
            'sort' => $club->faqs()->count(),
        ]);

        return back();
    }

    public function destroyFaq(Club $club, ClubFaq $faq): RedirectResponse
    {
        Gate::authorize('publish', $club);
        abort_unless($faq->club_id === $club->id, 404);
        $faq->delete();

        return back();
    }

    private function addPhotos(Request $request, Album $album): void
    {
        foreach ($request->file('photos') as $photo) {
            $album->photos()->create(['path' => $photo->store("albums/{$album->id}", 'public'), 'uploaded_by' => $request->user()->id]);
        }
    }
}
