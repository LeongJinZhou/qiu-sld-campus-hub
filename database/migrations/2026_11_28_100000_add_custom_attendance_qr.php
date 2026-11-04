<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The committee's own check-in / check-out QR images (e.g. a form link), shown instead of the system QR. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['events', 'meetings'] as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->string('checkin_qr_path')->nullable();
                $t->string('checkout_qr_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['events', 'meetings'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['checkin_qr_path', 'checkout_qr_path']));
        }
    }
};
