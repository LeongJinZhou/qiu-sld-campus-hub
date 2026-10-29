<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('club_categories')->nullOnDelete();
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->text('bio')->nullable();
            $table->string('instagram_handle', 30)->nullable();
            $table->string('qr_token', 32)->unique();
            // 'sld' while SLD sets the club up; 'committee' after transfer. SLD keeps oversight either way.
            $table->string('managed_by', 10)->default('sld');
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
