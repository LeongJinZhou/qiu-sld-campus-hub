<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requests from SLD that a club must answer within 7 working days.
        Schema::create('sld_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('subject', 200);
            $table->text('body');
            $table->date('due_on')->index();
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable()->index();
            $table->timestamps();
        });

        // One row per reminder sent, so the daily job never sends the same reminder twice.
        Schema::create('reminder_log', function (Blueprint $table) {
            $table->string('key', 191)->primary();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_log');
        Schema::dropIfExists('sld_requests');
    }
};
