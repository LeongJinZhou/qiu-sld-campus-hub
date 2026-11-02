<?php

namespace App\Http\Controllers;

use App\Http\Requests\Social\StorePostRequest;
use App\Models\Agm;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\ReportPhoto;
use App\Models\User;
use App\Services\Reports\EvidencePhotos;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Evidence photos for weekly activity reports, AGMs and events, and the event photo ZIP. */
class EvidencePhotoController extends Controller
{
    public function __construct(private readonly EvidencePhotos $photos) {}

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $subject = EvidencePhotos::subject($type, $id);
        abort_unless($this->canEdit($request->user(), $subject), 403);
        $request->validate(['photos' => ['required', 'array', 'min:1', 'max:20'], 'photos.*' => StorePostRequest::IMAGE_RULES]);
        foreach ($request->file('photos') as $photo) {
            $this->photos->add($subject, $photo, $request->user());
        }
        $count = $this->photos->paths($subject)->count();

        return back()->with('success', "{$count} photo".($count === 1 ? '' : 's').' on record'.($count < $this->photos->minimum() ? " (at least {$this->photos->minimum()} needed)." : '.'));
    }

    public function destroy(Request $request, ReportPhoto $photo): RedirectResponse
    {
        abort_unless($photo->photoable && $this->canEdit($request->user(), $photo->photoable), 403);
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back();
    }

    /** Club_Event_YYYYMMDD_01.jpg … for the event's organisers and SLD. */
    public function eventZip(Request $request, Event $event): BinaryFileResponse|RedirectResponse
    {
        abort_unless($request->user()->can('organises', $event) || $request->user()->can('viewInternal', $event->club), 403);
        try {
            $file = $this->photos->zip($event);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response()->download($file, $this->photos->name($event).'_photos.zip')->deleteFileAfterSend();
    }

    private function canEdit(User $user, mixed $subject): bool
    {
        return match (true) {
            $subject instanceof Event => $user->can('organises', $subject),
            $subject instanceof Meeting, $subject instanceof Agm => $user->can('publish', $subject->club),
            default => false,
        };
    }
}
