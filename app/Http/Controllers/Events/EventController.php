<?php

namespace App\Http\Controllers\Events;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Services\Documents\DocumentPresenter;
use App\Services\Events\ProposalDrafts;
use App\Services\Events\ProposalRules;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function create(Club $club, ProposalRules $rules): Response
    {
        Gate::authorize('create', [Event::class, $club]);

        return Inertia::render('Events/Create', [
            'club' => $club->only('slug', 'name'),
            'earliest' => [
                'small' => $rules->earliestStart(1, now())->toDateString(),
                'large' => $rules->earliestStart(ProposalRules::LARGE_EVENT_PARTICIPANTS, now())->toDateString(),
            ],
            'largeThreshold' => ProposalRules::LARGE_EVENT_PARTICIPANTS,
        ]);
    }

    public function store(StoreEventRequest $request, Club $club, SessionResolver $sessions, ProposalRules $rules, ProposalDrafts $drafts): RedirectResponse
    {
        try {
            $drafts->assertCanStart($club);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        $event = DB::transaction(function () use ($request, $club, $sessions, $rules): Event {
            $event = Event::create([
                ...$request->validated(),
                'club_id' => $club->id,
                'session_id' => $sessions->current()->id,
            ]);
            $event->update(['cca_participant' => $rules->defaultCca($event->starts_at, $event->ends_at), 'cca_committee' => $rules->defaultCca($event->starts_at, $event->ends_at)]);
            Document::create([
                'type' => DocumentType::Proposal,
                'club_id' => $club->id,
                'event_id' => $event->id,
                'session_id' => $event->session_id,
                'created_by' => $request->user()->id,
            ]);

            return $event;
        });

        $drafts->started($event, $request->user());

        return to_route('proposals.edit', $event)->with('success', 'Proposal started. It saves automatically, and the committee has been told.');
    }

    public function show(Request $request, Event $event, DocumentPresenter $presenter): Response
    {
        Gate::authorize('view', $event);
        $user = $request->user();

        return Inertia::render('Events/Show', [
            'event' => [
                ...$event->only('slug', 'name', 'summary', 'venue', 'mode', 'is_outdoor', 'cca_participant', 'cca_committee', 'registration_open', 'attendance_open'),
                'status' => $event->status->value,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'poster_url' => $event->posterUrl(),
                'club' => $event->club->only('slug', 'name'),
                'proposal_id' => $event->proposal?->id,
            ],
            'committee' => $event->committee()->with('user.student')->get()->map(fn (RoleAssignment $a) => [
                'position' => $a->position,
                'name' => $a->user->name,
                'photo_url' => $a->user->photoUrl(),
                'responsibility' => $a->responsibility,
            ]),
            'registered' => $event->registrations()->where('user_id', $user->id)->exists(),
            'registrationStatus' => $event->registrations()->where('user_id', $user->id)->value('status'),
            'payment' => [
                'fee_sen' => $event->fee_sen, 'payment_url' => $event->payment_url, 'payment_account_name' => $event->payment_account_name,
                'qr' => collect($event->payment_qr_paths ?? [])->map(fn (string $p) => ['path' => $p, 'url' => Storage::disk('public')->url($p)])->values(),
            ],
            'indemnity' => $event->is_outdoor ? [
                'signed' => $event->indemnities()->where('user_id', $user->id)->exists(),
                'closes_at' => $event->indemnityClosesAt()->format('D, j M Y, g:i A'),
                'closed' => now()->gte($event->indemnityClosesAt()),
            ] : null,
            'registrations' => $event->registrations()->count(),
            'approval' => $event->proposal && $event->proposal->status !== DocumentStatus::Draft && $user->can('organises', $event)
                ? ['progress' => $presenter->progress($event->proposal), 'steps' => $presenter->present($event->proposal)['steps']]
                : null,
            'can' => ['organise' => $user->can('organises', $event), 'update' => $user->can('update', $event)],
        ]);
    }
}
