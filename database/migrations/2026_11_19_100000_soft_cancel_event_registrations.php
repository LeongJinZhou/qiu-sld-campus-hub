<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cancelled registration is kept (with its payments and order ID) instead of deleted, so money stays traceable.
        Schema::table('event_registrations', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
