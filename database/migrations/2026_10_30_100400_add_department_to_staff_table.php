<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Printed under approver signatures on official templates (e.g. "Department: Registry"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', fn (Blueprint $table) => $table->string('department')->nullable()->after('title'));
    }

    public function down(): void
    {
        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn('department'));
    }
};
