<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Employee ID, printed beside advisors on the proposal's committee chart. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', fn (Blueprint $table) => $table->string('staff_number', 50)->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn('staff_number'));
    }
};
