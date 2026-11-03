<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who a meeting is for (committee + advisors, or every member) and, for a weekly activity, the expected pax. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('audience', 20)->default('committee')->after('kind');
            $table->unsignedInteger('pax')->nullable()->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn(['audience', 'pax']));
    }
};
