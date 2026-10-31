<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Club;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One search box for clubs, approved events and people. People results carry only what the public profile
 * shows (name, photo, programme short form, year group or staff title), never email, phone or student ID.
 */
class SearchController extends Controller
{
    public const TYPES = ['all', 'clubs', 'events', 'people'];

    private const LIMIT = 24;

    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'all';
        $like = '%'.addcslashes($q, '%_\\').'%';
        $wants = fn (string $t): bool => $type === 'all' || $type === $t;

        return Inertia::render('Search/Index', [
            'q' => $q,
            'type' => $type,
            // With no query, Clubs and Events browse everything; people are only listed when searched for.
            'clubs' => $wants('clubs') ? Club::with('category')
                ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'like', $like)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))))
                ->orderBy('name')->limit($q === '' && $type === 'all' ? 12 : 60)->get()
                ->map(fn (Club $c) => ['slug' => $c->slug, 'name' => $c->name, 'logo_url' => $c->logoUrl(), 'category' => $c->category?->name]) : [],
            'events' => $wants('events') ? Event::with('club')->where('status', DocumentStatus::Approved)
                ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'like', $like)->orWhere('venue', 'like', $like)
                    ->orWhereHas('club', fn (Builder $c) => $c->where('name', 'like', $like))))
                ->when($q === '', fn (Builder $b) => $b->where('ends_at', '>', now()))
                ->orderByRaw('ends_at < ?', [now()])->orderBy('starts_at')->limit(self::LIMIT)->get()
                ->map(fn (Event $e) => [
                    'slug' => $e->slug, 'name' => $e->name, 'club' => $e->club->name, 'starts_at' => $e->starts_at->toIso8601String(),
                    'venue' => $e->venue, 'poster_url' => $e->posterUrl(), 'past' => $e->ends_at->isPast(), 'fee_sen' => $e->fee_sen, 'members_only' => $e->members_only,
                ]) : [],
            'people' => $wants('people') && mb_strlen($q) >= 2 ? User::with(['student', 'staff'])
                ->where('name', 'like', $like)->where(fn (Builder $b) => $b->whereHas('student')->orWhereHas('staff'))
                ->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (User $u) => [
                    'name' => $u->name, 'photo_url' => $u->photoUrl(), 'url' => route('profile.public', $u->qr_token),
                    'subtitle' => $u->student
                        ? implode(' · ', array_filter([$u->student->programmeShortForm(), $u->student->status->value === 'alumni' ? 'Alumni' : $u->student->year_group]))
                        : ($u->staff?->title ?? 'Staff'),
                ]) : [],
        ]);
    }
}
