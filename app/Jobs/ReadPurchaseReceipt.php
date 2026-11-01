<?php

namespace App\Jobs;

use App\Models\EventPurchase;
use App\Services\Ai\PurchaseReceiptReader;
use App\Services\Ai\ReceiptReader;
use App\Services\Events\EventPurchases;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** AI reading of a purchase: the shop receipt (items and prices), or the pay-back transfer receipt. */
class ReadPurchaseReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $purchaseId, public readonly bool $payout = false) {}

    public function handle(EventPurchases $purchases, PurchaseReceiptReader $items, ReceiptReader $transfers): void
    {
        $purchase = EventPurchase::with('event.club', 'buyer', 'payer')->find($this->purchaseId);
        if (! $purchase) {
            return;
        }
        $this->payout ? $purchases->readPayout($purchase, $transfers) : $purchases->read($purchase, $items);
    }
}
