<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every transaction is filed automatically: what it is for (membership / an event / a fund withdrawal /
        // other), how the money moved, and who handled it, so the year's finance report builds itself.
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('category', 16)->default('other')->after('expense_sen')->index();
            $table->foreignId('event_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->string('method', 16)->nullable()->after('event_id');
            $table->foreignId('handled_by')->nullable()->after('method')->constrained('users')->nullOnDelete();
        });

        // Cash handovers: how the cash in the photo was confirmed (AI check or the committee member).
        Schema::table('cash_handovers', function (Blueprint $table) {
            $table->string('cash_check', 24)->nullable()->after('photo_path');
        });

        // Existing membership fees become "membership", with method and collector from the payment.
        foreach (DB::table('ledger_entries')->where('source_type', 'membership_payment')->orWhere('source_type', 'App\\Models\\MembershipPayment')->get() as $entry) {
            $payment = DB::table('membership_payments')->find($entry->source_id);
            DB::table('ledger_entries')->where('id', $entry->id)->update([
                'category' => 'membership', 'method' => $payment->method ?? null,
                'handled_by' => ($payment->method ?? null) === 'cash' ? $payment->collected_by : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('cash_handovers', fn (Blueprint $table) => $table->dropColumn('cash_check'));
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by');
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['category', 'method']);
        });
    }
};
