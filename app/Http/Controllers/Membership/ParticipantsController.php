<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Services\Documents\PdfConverter;
use App\Services\Documents\SimpleTableExport;
use App\Services\Membership\ClubParticipants;
use App\Services\Time\SessionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Who takes part in the club's events — members or not — and each person's event history. Committee, advisor, SLD. */
class ParticipantsController extends Controller
{
    public function index(Request $request, Club $club, SessionResolver $sessions, ClubParticipants $participants): Response
    {
        Gate::authorize('viewInternal', $club);
        $session = $this->session($request, $sessions);

        return Inertia::render('Clubs/Participants', [
            'club' => $club->only('slug', 'name'),
            'session' => $session->name,
            'sessions' => AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name'),
            ...$participants->build($club, $session),
            'labels' => ClubParticipants::LABELS,
        ]);
    }

    public function export(Request $request, Club $club, string $format, SessionResolver $sessions, ClubParticipants $participants, SimpleTableExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $session = $this->session($request, $sessions);
        $data = $participants->build($club, $session);
        $file = $export->render("{$club->name} · Event participants", "Session {$session->name} · {$data['events']} events",
            ['Name', 'Student ID', 'Programme', 'Year', 'Membership', 'Registered', 'Attended', 'CCA hours', 'Last event', 'Events'],
            $participants->exportRows($data['rows']));
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, "{$club->name} - Participants ".str_replace('/', '-', $session->name).".{$format}")->deleteFileAfterSend();
    }

    private function session(Request $request, SessionResolver $sessions): AcademicSession
    {
        return AcademicSession::where('name', (string) $request->query('session'))->first() ?? $sessions->current();
    }
}
