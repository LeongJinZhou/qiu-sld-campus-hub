<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personal (alumni) sign-in emails are stored lower-case and may belong to one account only.
        DB::table('users')->whereNotNull('personal_email')->update(['personal_email' => DB::raw('LOWER(TRIM(personal_email))')]);
        $duplicates = DB::table('users')->whereNotNull('personal_email')->select('personal_email')->groupBy('personal_email')->havingRaw('COUNT(*) > 1')->pluck('personal_email');
        if ($duplicates->isNotEmpty()) {
            // Ambiguous links cannot sign anyone in safely: clear them so the owners re-link.
            DB::table('users')->whereIn('personal_email', $duplicates)->update(['personal_email' => null]);
            Log::warning('Cleared '.$duplicates->count().' personal emails linked to more than one account.');
        }
        Schema::table('users', fn (Blueprint $table) => $table->unique('personal_email'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['personal_email']));
    }
};
