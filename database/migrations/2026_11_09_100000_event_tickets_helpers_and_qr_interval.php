<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A confirmed place gets a ticket: a secret code for its QR, and the transaction/order ID it was paid with.
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('ticket_code', 40)->nullable()->unique()->after('status');
            $table->string('order_id', 80)->nullable()->after('ticket_code');
        });
        // How often the on-screen attendance QR changes, so a photo of it cannot be passed around.
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('qr_rotate_seconds')->default(60)->after('attendance_open');
        });
        // Students the committee lets help scan tickets and attendance for one event.
        Schema::create('event_helpers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_helpers');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('qr_rotate_seconds'));
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropUnique(['ticket_code']);
            $table->dropColumn(['ticket_code', 'order_id']);
        });
    }
};
