<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evidence photos for a weekly activity report (meeting), an AGM or an event; SLD sets the minimum.
        Schema::create('report_photos', function (Blueprint $table) {
            $table->id();
            $table->morphs('photoable');
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        // The annual archive (one ZIP per club and session), made when SLD acknowledges the AGM.
        Schema::table('generated_files', fn (Blueprint $table) => $table->string('zip_path')->nullable()->after('pdf_path'));
    }

    public function down(): void
    {
        Schema::table('generated_files', fn (Blueprint $table) => $table->dropColumn('zip_path'));
        Schema::dropIfExists('report_photos');
    }
};
