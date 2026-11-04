<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Meeting;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The committee's own check-in and check-out QR images for an event or a meeting (for example a Google Form).
 * Images are re-encoded on upload, kept on the private disk and shown through short-lived signed links.
 */
class CustomAttendanceQr
{
    private const COLUMNS = ['in' => 'checkin_qr_path', 'out' => 'checkout_qr_path'];

    public function __construct(private readonly ImageUpload $images) {}

    /** @param array{in?: ?UploadedFile, out?: ?UploadedFile, remove_in?: bool, remove_out?: bool} $changes */
    public function update(Event|Meeting $owner, array $changes): void
    {
        foreach (self::COLUMNS as $action => $column) {
            $upload = $changes[$action] ?? null;
            if (! $upload && empty($changes["remove_{$action}"])) {
                continue;
            }
            if ($owner->{$column}) {
                Storage::disk('local')->delete($owner->{$column});
            }
            $owner->{$column} = $upload ? $this->images->store($upload, 'attendance-qr/'.$this->kind($owner)."/{$owner->id}", 'local') : null;
        }
        $owner->save();
    }

    /** @return array{in: ?string, out: ?string} signed image links, valid for two hours */
    public function urls(Event|Meeting $owner): array
    {
        $link = fn (string $action) => $owner->{self::COLUMNS[$action]}
            ? URL::temporarySignedRoute('attendance-qr.image', now()->addHours(2), ['kind' => $this->kind($owner), 'id' => $owner->id, 'action' => $action, 'v' => substr(md5((string) $owner->{self::COLUMNS[$action]}), 0, 8)])
            : null;

        return ['in' => $link('in'), 'out' => $link('out')];
    }

    public function path(Event|Meeting $owner, string $action): ?string
    {
        return isset(self::COLUMNS[$action]) ? $owner->{self::COLUMNS[$action]} : null;
    }

    private function kind(Event|Meeting $owner): string
    {
        return $owner instanceof Event ? 'events' : 'meetings';
    }
}
