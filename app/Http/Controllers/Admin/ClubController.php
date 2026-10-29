<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClubRequest;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\Staff;
use App\Services\AuditLogger;
use App\Services\ClubStatusService;
use App\Services\Roles\CommitteeBoard;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Time\SessionResolver;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(SessionResolver $sessions, CompulsoryPositionsCheck $compulsory): Response
    {
        $session = $sessions->current();

        return Inertia::render('Admin/Clubs/Index', [
            'session' => $session->name,
            'clubs' => Club::with('category')->orderBy('name')->get()->map(fn (Club $club) => [
                'id' => $club->id,
                'slug' => $club->slug,
                'name' => $club->name,
                'category' => $club->category?->name,
                'managed_by' => $club->managed_by,
                'status' => $club->status,
                'last_activity' => app(ClubStatusService::class)->lastActivity($club)?->toFormattedDateString(),
                'missing' => $compulsory->missing(ScopeType::Club, $club->id, $session->id),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Clubs/Edit', ['club' => null, 'categories' => $this->categories()]);
    }

    public function store(ClubRequest $request): RedirectResponse
    {
        $club = Club::create($this->attributes($request));
        $this->audit->log('club.created', $club);

        return to_route('admin.clubs.edit', $club)->with('success', 'Club created. Now assign its committee.');
    }

    public function edit(Request $request, Club $club, SessionResolver $sessions, CommitteeBoard $board, CompulsoryPositionsCheck $compulsory): Response
    {
        $current = $sessions->current();
        $session = AcademicSession::where('name', $request->query('session'))->first() ?? $current;

        return Inertia::render('Admin/Clubs/Edit', [
            'club' => [
                ...$club->only('id', 'slug', 'name', 'category_id', 'established_year', 'bio', 'instagram_handle', 'managed_by'),
                'logo_url' => $club->logoUrl(),
                'transferred_at' => $club->transferred_at?->toDayDateTimeString(),
            ],
            'categories' => $this->categories(),
            'session' => $session->name,
            'isCurrentSession' => $session->is($current),
            'sessions' => AcademicSession::orderByDesc('starts_on')->pluck('name'),
            ...$board->for($club, $session),
            'compulsoryPositions' => CompulsoryPositionsCheck::POSITIONS,
            'missingCompulsory' => $compulsory->missing(ScopeType::Club, $club->id, $session->id),
            'staff' => Staff::where('active', true)->whereNotNull('user_id')->orderBy('name')->get()
                ->map(fn (Staff $s) => ['user_id' => $s->user_id, 'label' => trim("{$s->name} · {$s->title}", ' ·')]),
        ]);
    }

    public function update(ClubRequest $request, Club $club): RedirectResponse
    {
        $club->update($this->attributes($request, $club));
        $this->audit->log('club.updated', $club, $club->getChanges());

        return back()->with('success', 'Club saved.');
    }

    /** @return array<string, mixed> */
    private function attributes(ClubRequest $request, ?Club $club = null): array
    {
        $attributes = $request->safe()->except('logo');
        if ($request->hasFile('logo')) {
            if ($club?->logo_path) {
                Storage::disk('public')->delete($club->logo_path);
            }
            $attributes['logo_path'] = app(ImageUpload::class)->store($request->file('logo'), 'club-logos');
        }

        return $attributes;
    }

    /** @return list<array{value: int, label: string}> */
    private function categories(): array
    {
        return ClubCategory::orderBy('name')->get()->map(fn (ClubCategory $c) => ['value' => $c->id, 'label' => $c->name])->all();
    }
}
