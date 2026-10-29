<?php

namespace App\Enums;

/** Shared by documents and events (an event's status follows its proposal). */
enum DocumentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Approved = 'approved';

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
