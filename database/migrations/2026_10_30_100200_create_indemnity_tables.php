<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phone numbers and addresses are encrypted at rest (text columns hold ciphertext). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('mobile_phone');
            $table->text('work_phone')->nullable();
            $table->string('relationship', 50);
            $table->string('alternate_name')->nullable();
            $table->text('alternate_phone')->nullable();
            $table->timestamps();
        });

        Schema::create('indemnities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emergency_name');
            $table->text('emergency_mobile_phone');
            $table->text('emergency_work_phone')->nullable();
            $table->string('emergency_relationship', 50);
            $table->string('alternate_name')->nullable();
            $table->text('alternate_phone')->nullable();
            $table->text('contact_number');
            $table->text('address');
            $table->foreignId('signature_id')->constrained()->restrictOnDelete();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indemnities');
        Schema::dropIfExists('emergency_contacts');
    }
};
