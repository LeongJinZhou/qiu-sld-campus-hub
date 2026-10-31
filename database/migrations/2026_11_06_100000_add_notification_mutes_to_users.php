<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Social notification types a person chose to mute (likes, comments, saves, follows). Actions are never muted.
        Schema::table('users', fn (Blueprint $table) => $table->json('notification_mutes')->nullable()->after('hidden_sections'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_mutes'));
    }
};
