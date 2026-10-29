<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'kind', 'due_on', 'file_path', 'submitted_by', 'submitted_at', 'reminded_at'])]
class EventDocumentItem extends Model
{
    /** Post-event deliverables and how many days after the event each is due. */
    public const KINDS = [
        'finance_record' => ['Finance record', 7],
        'receipts' => ['Receipts', 7],
        'proposal' => ['Proposal', 14],
        'poster' => ['Poster', 14],
        'participant_list' => ['Participant list', 14],
        'post_mortem' => ['Post mortem', 14],
        'survey' => ['Survey', 14],
        'reel' => ['Reel', 14],
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'submitted_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function label(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }
}
