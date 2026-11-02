<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\GeneratedFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** A club's annual archive: the club's committee and advisor (current access) and SLD only. */
class ArchiveController extends Controller
{
    public function __invoke(Club $club, AcademicSession $session): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);
        $archive = GeneratedFile::where(['club_id' => $club->id, 'session_id' => $session->id, 'kind' => GeneratedFile::ANNUAL_ARCHIVE])->whereNotNull('zip_path')->firstOrFail();
        abort_unless(is_file((string) $archive->zip_path), 404);

        return response()->download($archive->zip_path, "{$club->name} ".str_replace('/', '-', $session->name).' Archive.zip');
    }
}
