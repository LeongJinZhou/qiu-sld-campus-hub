<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who may register: anyone at QIU, or only the club's own members (committee always can).
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('members_only')->default(false)->after('registration_open');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('members_only'));
    }
};
