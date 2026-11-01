<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every purchase for an event, snapped at the till: items and prices read off the receipt, checked against
        // the budget, and (when someone paid with their own money) verified by the event committee and paid back
        // in cash or by transfer before it lands in the ledger.
        Schema::create('event_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bought_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->index(); // reading, draft, pending, owed, paying, recorded, rejected
            $table->string('paid_with', 8)->default('own'); // club money or own money (claim back)
            $table->string('budget_line')->nullable();
            $table->string('shop', 150)->nullable();
            $table->date('purchased_on')->nullable();
            $table->json('items')->nullable();
            $table->unsignedInteger('total_sen')->default(0);
            $table->string('receipt_path');
            $table->char('receipt_hash', 64)->index();
            $table->float('ai_confidence')->nullable();
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->string('settle_when', 12)->nullable(); // now, after_event
            $table->string('settle_method', 8)->nullable(); // cash, online
            $table->foreignId('paid_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payout_path')->nullable(); // transfer receipt or cash photo
            $table->string('payout_payee', 150)->nullable();
            $table->string('payout_reference', 100)->nullable();
            $table->string('payout_check', 12)->nullable(); // reading, ok, mismatch, unread
            $table->timestamp('received_at')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_purchases');
    }
};
