<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirror of the allowed camsys columns only; sensitive columns never land here.
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 50)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->index();
            $table->string('gender', 20)->nullable();
            $table->text('mobile_no')->nullable(); // encrypted at rest
            $table->string('programme');
            $table->string('programme_code', 50)->index();
            $table->string('type_of_programme', 50)->nullable();
            $table->string('programme_status', 50);
            $table->string('profile_status', 50)->nullable();
            $table->unsignedSmallInteger('year_entry');
            $table->unsignedTinyInteger('intake_number')->nullable();
            $table->unsignedTinyInteger('intake_month')->nullable();
            $table->string('course_duration', 30)->nullable();
            $table->date('expected_end_date')->nullable();
            $table->string('status', 10)->index();
            $table->string('year_group', 12)->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('approver_role', 30)->nullable()->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
        Schema::dropIfExists('students');
    }
};
