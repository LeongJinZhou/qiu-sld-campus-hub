<?php

namespace App\Services\Reports;

use App\Models\Agm;
use App\Models\AlbumPhoto;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\PostMedia;
use App\Models\ReportPhoto;
use App\Models\User;
use App\Services\SldRules;
use App\Services\Uploads\ImageUpload;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Photo evidence, with one minimum for all three (SLD setting, default 3): a weekly activity report, an AGM and a
 * completed event. Event photos also count album photos and posts about the event. Photos download as a ZIP named
 * Club_Event_YYYYMMDD_01.jpg, …
 */
class EvidencePhotos
{
    public function __construct(private readonly ImageUpload $images, private readonly SldRules $rules) {}

    public function minimum(): int
    {
        return $this->rules->get('min_photos');
    }

    public function add(Meeting|Agm|Event $subject, UploadedFile $photo, User $by): ReportPhoto
    {
        return ReportPhoto::create([
            'photoable_type' => $subject->getMorphClass(), 'photoable_id' => $subject->getKey(), 'uploaded_by' => $by->id,
            'path' => $this->images->store($photo, 'report-photos/'.Str::snake(class_basename($subject)).'/'.$subject->getKey()),
        ]);
    }

    /** @return Collection<int, string> public-disk paths, oldest first */
    public function paths(Meeting|Agm|Event $subject): Collection
    {
        $own = ReportPhoto::where('photoable_type', $subject->getMorphClass())->where('photoable_id', $subject->getKey())->oldest('id')->pluck('path');
        if (! $subject instanceof Event) {
            return $own->values();
        }
        $albums = AlbumPhoto::whereIn('album_id', $subject->club->albums()->where('event_id', $subject->id)->select('id'))->oldest('id')->pluck('path');
        $posts = PostMedia::whereIn('post_id', $subject->posts()->whereNull('hidden_at')->select('id'))->orderBy('sort')->pluck('path');

        return $own->concat($albums)->concat($posts)->unique()->values();
    }

    /** @return array{type: string, id: int, minimum: int, photos: list<array{id: ?int, url: string}>} for the upload panel */
    public function panel(Meeting|Agm|Event $subject): array
    {
        $own = ReportPhoto::where('photoable_type', $subject->getMorphClass())->where('photoable_id', $subject->getKey())->pluck('id', 'path');

        return [
            'type' => Str::snake(class_basename($subject)), 'id' => (int) $subject->getKey(), 'minimum' => $this->minimum(),
            'photos' => $this->paths($subject)->map(fn (string $p) => ['id' => $own[$p] ?? null, 'url' => Storage::disk('public')->url($p)])->all(),
        ];
    }

    public function enough(Meeting|Agm|Event $subject): bool
    {
        return $this->paths($subject)->count() >= $this->minimum();
    }

    public function assertEnough(Meeting|Agm|Event $subject, string $what): void
    {
        $have = $this->paths($subject)->count();
        if ($have < $this->minimum()) {
            throw new DomainException("Add at least {$this->minimum()} photos for {$what} ({$have} so far).");
        }
    }

    /** ZIP of an event's photos, named Club_Event_YYYYMMDD_01.ext. Returns the file path (caller deletes it). */
    public function zip(Event $event): string
    {
        $paths = $this->paths($event);
        if ($paths->isEmpty()) {
            throw new DomainException('This event has no photos yet.');
        }
        $file = storage_path('app/private/generated/photos-'.$event->id.'-'.Str::random(6).'.zip');
        @mkdir(dirname($file), 0775, true);
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addTo($zip, $event, '');
        $zip->close();

        return $file;
    }

    /** Adds an event's photos into an open ZIP under $folder. */
    public function addTo(ZipArchive $zip, Event $event, string $folder): void
    {
        $base = $this->name($event);
        foreach ($this->paths($event) as $i => $path) {
            if (Storage::disk('public')->exists($path)) {
                $zip->addFile(Storage::disk('public')->path($path), $folder.sprintf('%s_%02d.%s', $base, $i + 1, strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg')));
            }
        }
    }

    public function name(Event $event): string
    {
        $part = fn (string $s) => Str::of(Str::ascii($s))->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->limit(40, '')->toString() ?: 'NA';

        return $part($event->club->name).'_'.$part($event->name).'_'.$event->starts_at->format('Ymd');
    }

    /** For Model morph lookups in the controller. */
    public static function subject(string $type, int $id): Model
    {
        return match ($type) {
            'meeting' => Meeting::findOrFail($id),
            'agm' => Agm::findOrFail($id),
            'event' => Event::findOrFail($id),
            default => abort(404),
        };
    }
}
