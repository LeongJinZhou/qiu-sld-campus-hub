<?php

namespace App\Services\Events;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Documents\Drafts;

/** 09 Event Post Mortem: header from records, A–G review rows, recommendations for future committees. */
class PostMortemForm
{
    public const AREAS = [
        'A' => 'Pre-Event - Planning & Preparation (Proposal, Timeline, Manpower Planning, Approvals, Communication)',
        'B' => 'Pre-Event - Promotion & Publicity (Posters, social media, announcement, registration promotion)',
        'C' => 'Actual Event - Registration & Attendance management (Registration desk, QR scan, participant flow, punctuality)',
        'D' => 'Actual Event - Grand Opening / Opening Session (Opening speech, VIP arrival, time control, flow of opening)',
        'E' => 'Actual Event - Agenda, speakers, activities, engagement, time management',
        'F' => 'Actual Event - Food, Logistics & Venue Management (food distribution, hygiene, food amount, seating, sound system, venue setup)',
        'G' => 'Committee Coordination & Teamwork (internal communication, role clarity, problem handling)',
    ];

    public function __construct(private readonly Drafts $drafts) {}

    public function document(Event $event, User $user): Document
    {
        return Document::firstOrCreate(
            ['event_id' => $event->id, 'type' => DocumentType::PostMortem],
            ['club_id' => $event->club_id, 'session_id' => $event->session_id, 'created_by' => $user->id],
        );
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'rows' => array_fill_keys(array_keys(self::AREAS), ['well' => '', 'challenges' => '', 'improvement' => '']),
            'recommendations' => ['', '', ''],
        ];
    }

    /** @return array<string, mixed> */
    public function current(Document $document): array
    {
        return $this->drafts->current($document, $this->defaults());
    }

    /** @param array<string, mixed> $data */
    public function save(Document $document, array $data, int $userId): void
    {
        $this->drafts->save($document, $data, $userId);
    }

    /**
     * Auto-filled, locked header (CLAUDE.md §6 Phase 3 F).
     *
     * @return array{event: string, club: string, date: string, time: string, venue: string, participants: int, committee: list<string>}
     */
    public function header(Event $event): array
    {
        return [
            'event' => $event->name,
            'club' => $event->club->name,
            'date' => $event->starts_at->format('j F Y'),
            'time' => $event->starts_at->format('g:i A').' – '.$event->ends_at->format('g:i A'),
            'venue' => (string) $event->venue,
            'participants' => $event->attendance()->whereNotNull('clock_in_at')->where('role', 'participant')->count(),
            'committee' => $event->committee()->with('user')->get()->map(fn (RoleAssignment $a) => "{$a->user->name} – {$a->position}")->all(),
        ];
    }
}
