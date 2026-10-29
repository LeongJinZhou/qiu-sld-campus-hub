<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ScopeType;
use App\Models\AcademicSession;
use App\Models\Album;
use App\Models\AlbumPhoto;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\Highlight;
use App\Models\HighlightItem;
use App\Models\MembershipDrive;
use App\Models\Post;
use App\Models\Story;
use App\Services\Agm\AgmPeriods;
use App\Services\Documents\DocumentPresenter;
use App\Services\Membership\MembershipService;
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

    public function show(Request $request, Club $club, SessionResolver $sessions, CommitteeBoard $board, CompulsoryPositionsCheck $compulsory, QrCodes $qr, DocumentPresenter $presenter): Response
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
                'status' => $club->status,
            ],
            'session' => $session->name,
            'isCurrentSession' => $session->is($current),
            'sessions' => $board->sessions($club, $current),
            ...$board->for($club, $session),
            'missingCompulsory' => $user?->can('viewInternal', $club)
                ? $compulsory->missing(ScopeType::Club, $club->id, $session->id)
                : [],
            'stories' => $club->stories()->live()->oldest()->get()->map(fn (Story $s) => ['id' => $s->id, 'url' => $s->url(), 'at' => $s->created_at?->diffForHumans()]),
            'highlights' => $club->highlights()->with('items')->get()->map(fn (Highlight $h) => [
                'id' => $h->id, 'title' => $h->title, 'items' => $h->items->map(fn (HighlightItem $i) => $i->url())->all(),
            ]),
            'membership' => [
                'status' => $club->memberships()->where('user_id', $user->id)->first()?->status->value,
                'fee_sen' => app(MembershipService::class)->fee($club)?->amount_sen,
                'drive' => MembershipDrive::running()->where(fn ($q) => $q->whereNull('club_id')->orWhere('club_id', $club->id))->value('title'),
            ],
            'counts' => [
                'members' => $club->memberships()->whereIn('status', ['active', 'grace'])->count(),
                'followers' => $club->followers()->count(),
                'events' => $club->events()->where('status', DocumentStatus::Approved)->count(),
            ],
            'following' => $club->followers()->whereKey($user->id)->exists(),
            'tab' => $request->query('tab', 'committee'),
            'posts' => Inertia::defer(fn () => $club->posts()->visible()->with('media', 'event')->latest('published_at')->limit(30)->get()
                ->map(fn (Post $p) => ['id' => $p->id, 'url' => route('posts.show', $p), 'image' => $p->media->first()?->url() ?? $p->event?->posterUrl(), 'caption' => $p->caption, 'count' => $p->media->count()])),
            'albums' => Inertia::defer(fn () => $club->albums()->with(['photos', 'session'])->latest()->get()->map(fn (Album $a) => [
                'id' => $a->id, 'title' => $a->title, 'session' => $a->session->name, 'photos' => $a->photos->map(fn (AlbumPhoto $p) => $p->url())->all(),
            ])),
            'events' => Inertia::defer(fn () => $club->events()
                ->when(! $user->can('viewInternal', $club), fn ($q) => $q->where('status', DocumentStatus::Approved))
                ->orderByDesc('starts_at')->get()
                ->map(fn (Event $e) => ['id' => $e->id, 'slug' => $e->slug, 'name' => $e->name, 'starts_at' => $e->starts_at->toIso8601String(), 'venue' => $e->venue, 'status' => $e->status->value, 'poster_url' => $e->posterUrl()])),
            'faqs' => $club->faqs()->get(['id', 'question', 'answer']),
            // AGM countdown for the club's own people while the period is open.
            'agm' => $user?->can('viewInternal', $club) && ($period = app(AgmPeriods::class)->current())
                ? ['days_left' => max(0, (int) ceil(now()->diffInDays($period->report_deadline->endOfDay()))), 'deadline' => $period->report_deadline->toDateString()]
                : null,
            'documents' => $user->can('viewInternal', $club)
                ? Inertia::defer(fn () => $club->documents()->with('event', 'approvals')->latest()->get()->map(fn (Document $d) => [
                    'id' => $d->id, 'type' => $d->type->label(), 'event' => $d->event?->name, 'status' => $d->status->value, 'updated' => $d->updated_at?->toFormattedDateString(),
                    'progress' => $presenter->progress($d),
                ]))
                : null,
            'can' => [
                'manage' => (bool) $user?->can('manage', Club::class),
                'publish' => $user->can('publish', $club),
                'createEvent' => $user->can('create', [Event::class, $club]),
                'internal' => $user->can('viewInternal', $club),
                'treasure' => $user->can('treasure', $club),
            ],
        ]);
    }
}
