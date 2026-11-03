<?php

namespace App\Services\Clubs;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Document;
use App\Services\Documents\DocumentPresenter;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Everything a club has asked for approval this session, as a board: Draft (started, not submitted), Submitted
 * (with its approval progress; returned ones are flagged), Approved (last 30 days) and Rejected. Rejected requests
 * are cleared after 30 days, or earlier by the committee.
 */
class ClubRequests
{
    public const KEEP_REJECTED_DAYS = 30;

    /** Documents that are requests (attendance lists and finance records are working files, not requests). */
    private const TYPES = [DocumentType::Proposal, DocumentType::FundWithdrawal, DocumentType::Minutes, DocumentType::PostMortem, DocumentType::AgmReport];

    public function __construct(private readonly DocumentPresenter $presenter) {}

    /** @return list<array<string, mixed>> */
    public function board(Club $club, AcademicSession $session): array
    {
        return Document::with(['event', 'approvals', 'latestVersion'])
            ->where('club_id', $club->id)->where('session_id', $session->id)->whereIn('type', self::TYPES)
            ->where(fn ($q) => $q->where('status', '!=', DocumentStatus::Approved)->orWhere('decided_at', '>=', now()->subDays(self::KEEP_REJECTED_DAYS))->orWhereNull('decided_at'))
            ->orderByDesc('updated_at')->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'type' => $d->type->label(),
                'title' => $d->event?->name ?? $d->type->label(),
                'event' => $d->event?->only('slug', 'name'),
                'status' => $d->status->value,
                'column' => match ($d->status) {
                    DocumentStatus::Draft => 'draft',
                    DocumentStatus::InReview, DocumentStatus::Returned => 'submitted',
                    DocumentStatus::Approved => 'approved',
                    DocumentStatus::Rejected => 'rejected',
                },
                'progress' => $d->approvals->isNotEmpty() ? $this->presenter->progress($d) : null,
                'url' => $d->type === DocumentType::Proposal && $d->event && $d->status->isEditable() ? route('proposals.edit', $d->event) : route('documents.show', $d),
                'updated_at' => $d->updated_at?->toIso8601String(),
                'removed_on' => $d->status === DocumentStatus::Rejected ? ($d->decided_at ?? $d->updated_at)?->addDays(self::KEEP_REJECTED_DAYS)->format('j M Y') : null,
            ])->values()->all();
    }

    /** Takes a rejected request off the board (a rejected proposal takes its never-approved event with it). */
    public function remove(Document $document): void
    {
        if ($document->status !== DocumentStatus::Rejected) {
            throw new DomainException('Only a rejected request can be deleted.');
        }
        DB::transaction(function () use ($document): void {
            $event = $document->type === DocumentType::Proposal ? $document->event : null;
            $event && $event->status !== DocumentStatus::Approved ? $event->delete() : $document->delete();
        });
    }

    /** Daily: rejected requests older than 30 days are cleared. */
    public function prune(): int
    {
        $old = Document::with('event')->where('status', DocumentStatus::Rejected)->whereIn('type', self::TYPES)
            ->where(fn ($q) => $q->where('decided_at', '<', now()->subDays(self::KEEP_REJECTED_DAYS))
                ->orWhere(fn ($w) => $w->whereNull('decided_at')->where('updated_at', '<', now()->subDays(self::KEEP_REJECTED_DAYS))))->get();
        $old->each(fn (Document $d) => $this->remove($d));

        return $old->count();
    }
}
