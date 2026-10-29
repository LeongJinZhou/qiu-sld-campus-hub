<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Club membership, fees, drives, payments and the cash handover. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->restrictOnDelete();
            $table->unsignedInteger('amount_sen');
            $table->string('payment_url', 1024)->nullable();
            // Name on the club's bank account; receipts must show it as the payee.
            $table->string('account_name')->nullable();
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'session_id']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->index();
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->date('grace_ends_on')->nullable();
            $table->date('alumni_since')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
        });

        Schema::create('membership_drives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 8);
            $table->unsignedInteger('amount_sen');
            $table->string('reference', 100)->nullable()->index();
            $table->string('receipt_path')->nullable();
            $table->unsignedInteger('ai_amount_sen')->nullable();
            $table->date('ai_date')->nullable();
            $table->string('ai_reference', 100)->nullable();
            $table->string('ai_payee')->nullable();
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->json('flags')->nullable();
            $table->string('status', 16)->index();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('membership_payments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('committee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->index();
            $table->string('qr_token_hash', 64)->nullable();
            $table->string('photo_path')->nullable();
            foreach (['accepted', 'declined', 'student_ready', 'committee_ready', 'photo_taken', 'student_done', 'committee_done', 'completed'] as $step) {
                $table->timestamp("{$step}_at")->nullable();
            }
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page', 30);
            $table->string('name', 60);
            $table->json('filters');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['saved_views', 'cash_handovers', 'membership_payments', 'membership_drives', 'memberships', 'club_fees'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
