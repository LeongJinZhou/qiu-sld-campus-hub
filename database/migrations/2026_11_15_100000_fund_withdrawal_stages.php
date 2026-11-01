<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // After the Treasurer, Advisor and SLD sign a fund withdrawal: SLD sends it to Finance and uploads their
        // signed copy, Finance banks the money in, the Treasurer uploads proof it arrived, SLD confirms; only then
        // does the club balance change. Every file stays as evidence.
        Schema::table('documents', function (Blueprint $table) {
            $table->string('finance_stage', 20)->nullable()->index()->after('status');
            $table->string('finance_file_path')->nullable()->after('finance_stage');
            $table->string('bankin_path')->nullable()->after('finance_file_path');
            $table->string('bankin_reference', 100)->nullable()->after('bankin_path');
            $table->string('finance_note', 500)->nullable()->after('bankin_reference');
            $table->timestamp('completed_at')->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn(['finance_stage', 'finance_file_path', 'bankin_path', 'bankin_reference', 'finance_note', 'completed_at']));
    }
};
