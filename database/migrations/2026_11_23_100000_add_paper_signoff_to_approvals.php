<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            // SLD's step only: higher authorities who sign the printed document, and the uploaded signed scan.
            $table->json('paper')->nullable()->after('sections');
            $table->string('paper_scan_path')->nullable()->after('paper');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropColumn(['paper', 'paper_scan_path']);
        });
    }
};
