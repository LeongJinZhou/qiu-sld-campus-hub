<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When a committee member was first told to pay their own club fee: they have 7 days from then.
        Schema::create('committee_fee_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions_academic')->cascadeOnDelete();
            $table->timestamp('first_shown_at');
            $table->unique(['user_id', 'club_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_fee_reminders');
    }
};
