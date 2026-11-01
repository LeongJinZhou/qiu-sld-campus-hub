<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-account settings that follow the person across devices: light/dark/system, and guided tours seen.
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 8)->default('system');
            $table->json('tours_seen')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['theme', 'tours_seen']));
    }
};
