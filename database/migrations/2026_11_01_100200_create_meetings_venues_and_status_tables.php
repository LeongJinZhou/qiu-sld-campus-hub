<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->string('kind', 12);
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('venue')->nullable();
            $table->string('meet_link', 500)->nullable();
            $table->string('google_event_id')->nullable();
            $table->foreignId('organiser_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_attendance', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('present')->default(false);
            $table->primary(['meeting_id', 'user_id']);
        });

        Schema::create('venue_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('venue');
            $table->date('date')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('semester', 20)->nullable();
            $table->string('weekday', 10)->nullable();
            $table->string('purpose', 500);
            $table->string('items', 500)->nullable();
            $table->text('activity');
            $table->string('status', 10)->default('pending')->index();
            $table->string('sld_note', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::table('clubs', function (Blueprint $table) {
            $table->string('status', 10)->default('active')->after('managed_by');
            $table->timestamp('last_activity_at')->nullable()->after('status');
        });

        // Google Calendar access for creating Meet links from the organiser's own QIU account.
        Schema::table('users', function (Blueprint $table) {
            $table->text('google_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['google_token', 'google_refresh_token', 'google_token_expires_at']));
        Schema::table('clubs', fn (Blueprint $table) => $table->dropColumn(['status', 'last_activity_at']));
        foreach (['venue_bookings', 'meeting_attendance', 'meetings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
