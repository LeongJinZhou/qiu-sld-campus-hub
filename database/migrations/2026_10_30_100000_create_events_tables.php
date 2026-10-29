<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('venue')->nullable();
            $table->string('mode', 10)->default('physical');
            $table->unsignedInteger('expected_participants')->default(0);
            // Outdoor / off-campus: indemnity required from every participant and committee member.
            $table->boolean('is_outdoor')->default(false);
            // Faculty events add the Dean step to the proposal chain.
            $table->boolean('is_faculty_event')->default(false);
            $table->decimal('cca_participant', 5, 1)->default(0);
            $table->decimal('cca_committee', 5, 1)->default(0);
            $table->string('status', 12)->default('draft')->index();
            $table->string('poster_path')->nullable();
            $table->json('poster_checklist')->nullable();
            $table->boolean('registration_open')->default(false);
            $table->boolean('attendance_open')->default(false);
            $table->string('attendance_secret', 64);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'starts_at']);
        });

        // Event committee members (scope_type = event) need a responsibility line on the proposal.
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->string('responsibility', 255)->nullable()->after('position');
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12);
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('method', 10);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('cca_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->string('role', 12);
            $table->decimal('hours', 5, 1);
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });

        // Post-event document checklist with deadlines (CLAUDE.md §6 Events).
        Schema::create('event_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->date('due_on');
            $table->string('file_path')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_document_items');
        Schema::dropIfExists('cca_records');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('event_registrations');
        Schema::table('role_assignments', fn (Blueprint $table) => $table->dropColumn('responsibility'));
        Schema::dropIfExists('events');
    }
};
