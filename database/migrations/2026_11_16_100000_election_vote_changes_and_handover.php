<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A voter may change their mind up to 3 times per position; who nominated a candidate is kept.
        Schema::table('votes', function (Blueprint $table) {
            $table->unsignedTinyInteger('changes')->default(0);
            $table->timestamp('changed_at')->nullable();
        });
        Schema::table('candidates', fn (Blueprint $table) => $table->foreignId('nominated_by')->nullable()->constrained('users')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('votes', fn (Blueprint $table) => $table->dropColumn(['changes', 'changed_at']));
        Schema::table('candidates', fn (Blueprint $table) => $table->dropConstrainedForeignId('nominated_by'));
    }
};
