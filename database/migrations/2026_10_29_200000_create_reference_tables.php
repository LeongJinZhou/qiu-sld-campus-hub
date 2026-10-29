<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Admin-editable lookups that fill gaps in camsys (CLAUDE.md §3) and the academic calendar (§4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_abbrev', function (Blueprint $table) {
            $table->string('programme_code', 50)->primary();
            $table->string('abbreviation', 20);
            $table->timestamps();
        });

        Schema::create('intake_map', function (Blueprint $table) {
            $table->unsignedTinyInteger('intake_number')->primary();
            $table->unsignedTinyInteger('month');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('sessions_academic', function (Blueprint $table) {
            $table->id();
            $table->string('name', 9)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        Schema::create('club_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_categories');
        Schema::dropIfExists('sessions_academic');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('intake_map');
        Schema::dropIfExists('programme_abbrev');
    }
};
