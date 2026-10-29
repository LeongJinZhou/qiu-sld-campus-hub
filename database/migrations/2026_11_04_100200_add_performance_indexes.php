<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for foreign keys and the columns lists filter or sort on. MySQL already indexes
 * foreign keys and SQLite does not, so each index is only added where no index on those columns exists yet.
 */
return new class extends Migration
{
    /** @var array<string, list<list<string>>> */
    private const INDEXES = [
        'clubs' => [['category_id'], ['status']],
        'role_assignments' => [['user_id'], ['scope_type', 'scope_id', 'session_id'], ['session_id']],
        'audit_log' => [['actor_id'], ['subject_type', 'subject_id']],
        'events' => [['session_id'], ['starts_at'], ['status']],
        'event_registrations' => [['user_id']],
        'attendance' => [['user_id']],
        'event_document_items' => [['due_on']],
        'approvals' => [['approver_user_id'], ['decision', 'due_at']],
        'signatures' => [['document_id'], ['approval_id'], ['user_id']],
        'indemnities' => [['user_id']],
        'posts' => [['club_id', 'published_at'], ['event_id'], ['author_id']],
        'post_media' => [['post_id']],
        'post_likes' => [['user_id']],
        'saves' => [['user_id']],
        'comments' => [['post_id'], ['user_id']],
        'post_reports' => [['post_id']],
        'stories' => [['club_id', 'created_at']],
        'highlights' => [['club_id']],
        'highlight_items' => [['highlight_id']],
        'albums' => [['club_id'], ['event_id']],
        'album_photos' => [['album_id']],
        'club_follows' => [['user_id']],
        'club_faqs' => [['club_id']],
        'memberships' => [['user_id'], ['status', 'expires_on']],
        'membership_drives' => [['starts_at']],
        'membership_payments' => [['membership_id'], ['club_id', 'status'], ['user_id']],
        'cash_handovers' => [['student_id'], ['committee_user_id']],
        'saved_views' => [['user_id']],
        'receipts' => [['club_id']],
        'ledger_entries' => [['club_id', 'entry_date']],
        'payees' => [['club_id']],
        'meetings' => [['club_id', 'starts_at'], ['organiser_id']],
        'meeting_attendance' => [['user_id']],
        'venue_bookings' => [['club_id'], ['user_id']],
        'cca_records' => [['user_id'], ['event_id'], ['meeting_id']],
        'agms' => [['agm_period_id'], ['organiser_id']],
        'agm_attendance' => [['user_id']],
        'documents' => [['club_id', 'type'], ['event_id'], ['meeting_id'], ['agm_id'], ['session_id']],
        'candidates' => [['user_id']],
        'votes' => [['candidate_id']],
        'sld_requests' => [['club_id']],
        'certificates' => [['user_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (Schema::hasTable($table) && ! Schema::hasIndex($table, $columns)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $this->name($table, $columns)));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $this->name($table, $columns))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->name($table, $columns)));
                }
            }
        }
    }

    /** @param list<string> $columns */
    private function name(string $table, array $columns): string
    {
        return 'perf_'.$table.'_'.implode('_', $columns);
    }
};
