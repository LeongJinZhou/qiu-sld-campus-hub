<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Weekly activities earn CCA too (minutes + attendance), so a CCA record belongs to an event or a meeting. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cca_records', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->change();
            $table->foreignId('meeting_id')->nullable()->after('event_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'meeting_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cca_records', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'meeting_id']);
            $table->dropConstrainedForeignId('meeting_id');
        });
    }
};
