<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certificates for committee roles and event participation, issued from records with a public check code.
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('source_id');
            $table->string('title');
            $table->string('statement', 500);
            $table->string('code', 16)->unique();
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['user_id', 'kind', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
