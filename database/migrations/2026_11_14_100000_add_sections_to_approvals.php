<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A returned proposal can point at the exact parts to fix: [{key, note}], highlighted for the committee.
        Schema::table('approvals', fn (Blueprint $table) => $table->json('sections')->nullable()->after('comment'));
    }

    public function down(): void
    {
        Schema::table('approvals', fn (Blueprint $table) => $table->dropColumn('sections'));
    }
};
