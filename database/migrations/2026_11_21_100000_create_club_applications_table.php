<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A new club: the proposed President applies and signs, the proposed advisor signs, SLD approves; the club
        // only exists (and opens) once approved.
        Schema::create('club_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->json('data');
            $table->string('status', 20)->index(); // advisor, sld, approved, returned, rejected
            $table->foreignId('applicant_signature_id')->nullable()->constrained('signatures')->nullOnDelete();
            $table->foreignId('advisor_signature_id')->nullable()->constrained('signatures')->nullOnDelete();
            $table->timestamp('advisor_signed_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_applications');
    }
};
