<?php

use App\Models\Club;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Services\Finance\OrderIds;
use Illuminate\Database\Migrations\Migration;

/**
 * Payments and ledger lines recorded before transaction IDs existed get one now (from when they happened),
 * so SLD's transaction finder can reach every record. Existing IDs are never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = app(OrderIds::class);
        MembershipPayment::whereNull('order_id')->orderBy('id')->each(function (MembershipPayment $p) use ($ids): void {
            $club = Club::find($p->club_id);
            $user = User::find($p->user_id);
            if (! $club || ! $user) {
                return;
            }
            $id = $ids->forStudent($club, $p->event_id ? Event::find($p->event_id) : null, $user, $p->created_at ?? now(), $p->reference);
            $p->forceFill(['order_id' => $id])->saveQuietly();
        });
        LedgerEntry::whereNull('order_id')->orderBy('id')->each(function (LedgerEntry $e) use ($ids): void {
            $club = Club::find($e->club_id);
            if (! $club) {
                return;
            }
            $paid = $e->source_type === (new MembershipPayment)->getMorphClass() ? MembershipPayment::whereKey($e->source_id)->value('order_id') : null;
            $event = $e->event_id ? Event::with('club')->find($e->event_id) : null;
            $e->forceFill(['order_id' => $paid ?? ($event ? $ids->forEvent($event) : $ids->forClub($club, $e->category === 'withdrawal' ? 'withdrawal' : 'general'))])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Backfilled IDs are kept: they are now how these transactions are referenced.
    }
};
