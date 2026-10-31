<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Events can charge a fee, paid like membership: online (link, receipt check, payment QR codes) or cash.
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('fee_sen')->default(0)->after('expected_participants');
            $table->string('payment_url', 500)->nullable()->after('fee_sen');
            $table->string('payment_account_name', 150)->nullable()->after('payment_url');
            $table->json('payment_qr_paths')->nullable()->after('payment_account_name');
        });
        Schema::table('club_fees', fn (Blueprint $table) => $table->json('payment_qr_paths')->nullable()->after('account_name'));

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('status', 16)->default('registered')->after('user_id')->index();
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        // One payment table for membership fees and event fees, each with a traceable order ID.
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->foreignId('membership_id')->nullable()->change();
            $table->foreignId('event_id')->nullable()->after('membership_id')->constrained()->nullOnDelete();
            $table->foreignId('event_registration_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            $table->string('order_id', 120)->nullable()->unique()->after('id');
        });
        Schema::table('ledger_entries', fn (Blueprint $table) => $table->string('order_id', 120)->nullable()->index()->after('id'));

        // Running numbers per prefix (CLUB_EVENT, CLUB_MEMBERSHIP, ...), so IDs read 0001, 0002, ...
        Schema::create('order_counters', function (Blueprint $table) {
            $table->string('prefix', 100)->primary();
            $table->unsignedInteger('last')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_counters');
        Schema::table('ledger_entries', fn (Blueprint $table) => $table->dropColumn('order_id'));
        Schema::table('membership_payments', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropColumn('order_id');
            $table->dropConstrainedForeignId('event_registration_id');
            $table->dropConstrainedForeignId('event_id');
        });
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn(['status', 'paid_at']));
        Schema::table('club_fees', fn (Blueprint $table) => $table->dropColumn('payment_qr_paths'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn(['fee_sen', 'payment_url', 'payment_account_name', 'payment_qr_paths']));
    }
};
