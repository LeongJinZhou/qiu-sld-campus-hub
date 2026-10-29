<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The AGM module is open only inside a period set by SLD (CLAUDE.md §6 AGM).
        Schema::create('agm_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('agm_deadline');
            $table->date('report_deadline');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('agms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agm_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('venue')->nullable();
            $table->string('meet_link', 500)->nullable();
            $table->string('google_event_id')->nullable();
            $table->string('attendance_secret', 64);
            $table->foreignId('organiser_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('package_submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'agm_period_id']);
        });

        Schema::create('agm_attendance', function (Blueprint $table) {
            $table->foreignId('agm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->timestamp('attended_at');
            $table->primary(['agm_id', 'user_id']);
        });

        Schema::table('documents', fn (Blueprint $table) => $table->foreignId('agm_id')->nullable()->after('meeting_id')->constrained()->cascadeOnDelete());

        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agm_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('term_session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->boolean('allow_alumni_vote')->default(false);
            $table->dateTime('nominations_open_at');
            $table->dateTime('nominations_close_at');
            $table->dateTime('voting_open_at');
            $table->dateTime('voting_close_at');
            $table->timestamp('results_published_at')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamps();
        });

        Schema::create('election_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('position', 100);
            $table->boolean('is_compulsory')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('winner_candidate_id')->nullable();
            $table->unique(['election_id', 'position']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('statement');
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->unique(['election_position_id', 'user_id']);
        });

        // One vote per position per voter, enforced by the database.
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->unique(['election_position_id', 'voter_id']);
        });

        Schema::create('report_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('title_color', 7)->default('#000000');
            $table->string('body_color', 7)->default('#333333');
            $table->string('font', 60)->default('Poppins');
            $table->string('background_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('generated_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->string('kind', 32);
            $table->json('data')->nullable();
            $table->foreignId('report_theme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pptx_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['club_id', 'session_id', 'kind']);
        });
    }

    public function down(): void
    {
        foreach (['generated_files', 'report_themes', 'votes', 'candidates', 'election_positions', 'elections'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('agm_id'));
        foreach (['agm_attendance', 'agms', 'agm_periods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
