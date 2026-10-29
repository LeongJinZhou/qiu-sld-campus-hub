<?php

namespace App\Http\Controllers;

use App\Enums\ScopeType;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Services\QrCodes;
use App\Services\Roles\CommitteeBoard;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Time\SessionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Clubs/Index', [
            'search' => $search,
            'clubs' => Club::with('category')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->get()
                ->map(fn (Club $club) => [
                    'slug' => $club->slug,
                    'name' => $club->name,
                    'logo_url' => $club->logoUrl(),
                    'category' => $club->category?->name,
                ]),
        ]);
    }

    public function show(Request $request, Club $club, SessionResolver $sessions, CommitteeBoard $board, CompulsoryPositionsCheck $compulsory, QrCodes $qr): Response
    {
        $current = $sessions->current();
        $session = AcademicSession::where('name', $request->query('session'))->first() ?? $current;
        $user = $request->user();

        return Inertia::render('Clubs/Show', [
            'club' => [
                'id' => $club->id,
                'slug' => $club->slug,
                'name' => $club->name,
                'logo_url' => $club->logoUrl(),
                'category' => $club->category?->name,
                'established_year' => $club->established_year,
                'bio' => $club->bio,
                'instagram_handle' => $club->instagram_handle,
                'qr_svg' => $qr->svg(route('clubs.qr', $club->qr_token)),
            ],
            'session' => $session->name,
            'isCurrentSession' => $session->is($current),
            'sessions' => $board->sessions($club, $current),
            ...$board->for($club, $session),
            'missingCompulsory' => $user?->can('viewInternal', $club)
                ? $compulsory->missing(ScopeType::Club, $club->id, $session->id)
                : [],
            'can' => ['manage' => (bool) $user?->can('manage', Club::class)],
        ]);
    }
}
