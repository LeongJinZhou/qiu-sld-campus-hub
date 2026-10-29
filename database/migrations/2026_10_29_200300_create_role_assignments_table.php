<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scoped roles (club committee, event committee, advisor). Global roles live in spatie tables. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('scope_type', 10);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('session_id')->nullable()->constrained('sessions_academic')->restrictOnDelete();
            $table->string('position', 100)->nullable();
            $table->boolean('is_compulsory')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'session_id']);
            $table->unique(['scope_type', 'scope_id', 'session_id', 'role', 'position'], 'role_assignments_position_unique');
            $table->unique(['scope_type', 'scope_id', 'session_id', 'role', 'user_id'], 'role_assignments_person_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
