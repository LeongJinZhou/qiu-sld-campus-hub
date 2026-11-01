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
        // A payment, purchase or withdrawal is posted to the ledger once. Manual lines (no source) are unaffected.
        $duplicates = DB::table('ledger_entries')->whereNotNull('source_id')->select('source_type', 'source_id')
            ->groupBy('source_type', 'source_id')->havingRaw('COUNT(*) > 1')->count();
        if ($duplicates > 0) {
            Log::warning("ledger_entries: {$duplicates} sources are posted more than once; fix them, then re-run this migration to add the unique index.");

            return;
        }
        Schema::table('ledger_entries', fn (Blueprint $table) => $table->unique(['source_type', 'source_id'], 'ledger_entries_one_per_source'));
    }

    public function down(): void
    {
        if (Schema::hasIndex('ledger_entries', 'ledger_entries_one_per_source')) {
            Schema::table('ledger_entries', fn (Blueprint $table) => $table->dropUnique('ledger_entries_one_per_source'));
        }
    }
};
