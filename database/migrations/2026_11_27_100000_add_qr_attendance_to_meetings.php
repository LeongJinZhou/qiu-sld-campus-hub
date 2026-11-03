<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Meetings get their own rotating check-in / check-out QR, and attendance keeps when (and how) each person came and left. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('attendance_secret', 64)->nullable()->after('pax');
        });
        Schema::table('meeting_attendance', function (Blueprint $table): void {
            $table->timestamp('checked_in_at')->nullable()->after('present');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            $table->string('method', 10)->nullable()->after('checked_out_at');
        });
        DB::table('meetings')->whereNull('attendance_secret')->orderBy('id')->each(
            fn (object $m) => DB::table('meetings')->where('id', $m->id)->update(['attendance_secret' => Str::random(40)])
        );
    }

    public function down(): void
    {
        Schema::table('meeting_attendance', fn (Blueprint $table) => $table->dropColumn(['checked_in_at', 'checked_out_at', 'method']));
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn('attendance_secret'));
    }
};
