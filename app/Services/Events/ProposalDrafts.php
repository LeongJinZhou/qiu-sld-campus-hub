<?php

namespace App\Services\Events;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Meetings\Meetings;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Proposals are shared drafts: any committee member can start one, the others are told, anyone can continue it
 * (autosaved), and whoever submits signs as the preparer. A club may hold at most 5 unsubmitted proposals.
 */
class ProposalDrafts
{
    public const OPEN_LIMIT = 5;

    public function __construct(private readonly Meetings $meetings, private readonly SessionResolver $sessions) {}

    /** @return Collection<int, Document> drafts and returned proposals not yet (re)submitted */
    public function open(Club $club): Collection
    {
        return Document::with('event')->where('club_id', $club->id)->where('type', DocumentType::Proposal)
            ->where('session_id', $this->sessions->current()->id)
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Returned])->get();
    }

    public function assertCanStart(Club $club): void
    {
        if ($this->open($club)->count() >= self::OPEN_LIMIT) {
            throw new DomainException('Your club already has '.self::OPEN_LIMIT.' proposals in draft or on hold. Submit or discard one before starting another.');
        }
    }

    public function started(Event $event, User $by): void
    {
        $others = $this->meetings->committee($event->club)->reject(fn (User $u) => $u->is($by));
        Notification::send($others, new DocumentNotification(
            "{$by->name} started a proposal: {$event->name}",
            'It saves automatically. Anyone on the committee can continue it and submit.',
            route('proposals.edit', $event),
        ));
    }

    /** @param list<int> $userIds committee members asked to review and submit */
    public function askToSubmit(Event $event, User $by, array $userIds, ?string $note): int
    {
        $people = $this->meetings->committee($event->club)->whereIn('id', $userIds)->reject(fn (User $u) => $u->is($by));
        Notification::send($people, new DocumentNotification(
            "{$by->name} asks you to submit the proposal for {$event->name}",
            ($note ? "\"{$note}\" " : '').'Check it, sign and submit. Your name and position go on the form.',
            route('proposals.edit', $event),
        ));

        return $people->count();
    }

    /** A draft that was never submitted can be thrown away (it frees one of the 5 places). */
    public function discard(Event $event): void
    {
        $document = $event->proposal()->firstOrFail();
        if ($document->status !== DocumentStatus::Draft || $document->versions()->where('number', '>', 0)->exists()) {
            throw new DomainException('Only a draft that was never submitted can be discarded.');
        }
        $event->delete();
    }
}
