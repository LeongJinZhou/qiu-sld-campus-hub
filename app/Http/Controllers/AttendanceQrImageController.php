<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Meeting;
use App\Services\Events\CustomAttendanceQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a committee-uploaded attendance QR image to the people who run that event or meeting. */
class AttendanceQrImageController extends Controller
{
    public function __invoke(Request $request, string $kind, int $id, string $action, CustomAttendanceQr $custom): StreamedResponse
    {
        $owner = match ($kind) {
            'events' => Event::findOrFail($id),
            'meetings' => Meeting::findOrFail($id),
            default => abort(404),
        };
        $owner instanceof Event ? Gate::authorize('checkIn', $owner) : Gate::authorize('viewInternal', $owner->club);
        $path = $custom->path($owner, $action);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }
}
