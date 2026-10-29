<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case AwaitingReceipt = 'awaiting_receipt';
    case Reading = 'reading';
    case AutoConfirmed = 'auto_confirmed';
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case CashPending = 'cash_pending';

    public function isPaid(): bool
    {
        return $this === self::AutoConfirmed || $this === self::Confirmed;
    }
}
