<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One screenshot, one payment: the file fingerprint and the bank reference (letters and digits only)
        // stop a second student reusing someone else's receipt.
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->char('receipt_hash', 64)->nullable()->index()->after('receipt_path');
            $table->string('reference_key', 60)->nullable()->index()->after('reference');
            $table->string('ai_payer', 150)->nullable()->after('ai_payee');
            $table->string('order_id', 255)->nullable()->change();
        });
        Schema::table('event_registrations', fn (Blueprint $table) => $table->string('order_id', 255)->nullable()->change());
        Schema::table('ledger_entries', fn (Blueprint $table) => $table->string('order_id', 255)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropIndex(['reference_key']);
            $table->dropIndex(['receipt_hash']);
            $table->dropColumn(['receipt_hash', 'reference_key', 'ai_payer']);
        });
    }
};
