<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Raw pen strokes (x, y, time, pressure) captured from mouse, touch or stylus, kept as evidence. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->json('strokes')->nullable()->after('image_path');
            $table->string('input_device', 10)->nullable()->after('strokes');
        });
    }

    public function down(): void
    {
        Schema::table('signatures', fn (Blueprint $table) => $table->dropColumn(['strokes', 'input_device']));
    }
};
