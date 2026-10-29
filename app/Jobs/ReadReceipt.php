<?php

namespace App\Jobs;

use App\Models\MembershipPayment;
use App\Services\Membership\OnlinePayments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** AI receipt reading runs in the queue, never in the request (CLAUDE.md §2 AI). */
class ReadReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $paymentId) {}

    public function handle(OnlinePayments $payments): void
    {
        $payment = MembershipPayment::with('membership.club', 'club', 'user')->find($this->paymentId);
        if ($payment) {
            $payments->process($payment);
        }
    }
}
