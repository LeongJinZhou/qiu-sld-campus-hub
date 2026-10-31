<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Event;
use App\Services\Documents\PaymentBreakdownExport;
use App\Services\Documents\PdfConverter;
use App\Services\Finance\PaymentBreakdown;
use App\Services\Time\SessionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Who registered and paid, per event and for membership fees, with exports. Committee, advisor and SLD only. */
class PaymentBreakdownController extends Controller
{
    public function __construct(private readonly PaymentBreakdown $breakdowns) {}

    public function event(Club $club, Event $event): Response
    {
        $this->authorizeFor($club, $event);

        return $this->page($club, $this->breakdowns->event($event), "/clubs/{$club->slug}/finance/events/{$event->slug}");
    }

    public function membership(Request $request, Club $club, SessionResolver $sessions): Response
    {
        $this->authorizeFor($club);
        $session = $this->session($request, $sessions);

        return $this->page($club, $this->breakdowns->membership($club, $session), "/clubs/{$club->slug}/finance/membership", $session->name);
    }

    public function exportEvent(Club $club, Event $event, string $format, PaymentBreakdownExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        $this->authorizeFor($club, $event);

        return $this->download($club, $this->breakdowns->event($event), $format, $export, $pdf);
    }

    public function exportMembership(Request $request, Club $club, string $format, SessionResolver $sessions, PaymentBreakdownExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        $this->authorizeFor($club);

        return $this->download($club, $this->breakdowns->membership($club, $this->session($request, $sessions)), $format, $export, $pdf);
    }

    private function page(Club $club, array $breakdown, string $url, ?string $session = null): Response
    {
        return Inertia::render('Finance/Breakdown', [
            'club' => $club->only('slug', 'name'),
            'breakdown' => $breakdown,
            'url' => $url,
            'session' => $session,
            'sessions' => $session ? AcademicSession::orderByDesc('starts_on')->where('starts_on', '<=', now())->pluck('name') : [],
        ]);
    }

    /** @param array<string, mixed> $breakdown */
    private function download(Club $club, array $breakdown, string $format, PaymentBreakdownExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $file = $export->render($club, $breakdown);
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, Str::limit("{$club->name} - {$breakdown['title']}", 80, '')." payments.{$format}")->deleteFileAfterSend();
    }

    private function authorizeFor(Club $club, ?Event $event = null): void
    {
        Gate::authorize('viewInternal', $club);
        abort_if($event && $event->club_id !== $club->id, 404);
    }

    private function session(Request $request, SessionResolver $sessions): AcademicSession
    {
        return AcademicSession::where('name', $request->string('session'))->first() ?? $sessions->current();
    }
}
