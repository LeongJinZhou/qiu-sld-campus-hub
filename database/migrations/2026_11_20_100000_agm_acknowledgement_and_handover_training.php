<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SLD acknowledges the AGM; only then can SLD start the committee handover. During the overlap the incoming
        // committee has training (view-only) access; full access when the overlap ends.
        Schema::table('agms', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('package_submitted_at');
            $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('elections', function (Blueprint $table) {
            $table->timestamp('handover_started_at')->nullable()->after('results_published_at');
            $table->date('handover_ends_on')->nullable()->after('handover_started_at');
        });
        Schema::table('role_assignments', fn (Blueprint $table) => $table->string('handover_state', 10)->nullable()->after('ends_on'));
    }

    public function down(): void
    {
        Schema::table('role_assignments', fn (Blueprint $table) => $table->dropColumn('handover_state'));
        Schema::table('elections', fn (Blueprint $table) => $table->dropColumn(['handover_started_at', 'handover_ends_on']));
        Schema::table('agms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn('acknowledged_at');
        });
    }
};
