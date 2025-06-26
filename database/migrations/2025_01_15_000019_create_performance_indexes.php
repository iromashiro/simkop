<?php
// database/migrations/2025_01_15_000019_create_performance_indexes.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Composite indexes for common query patterns
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_journal_entries_query_optimization
                      ON journal_entries (cooperative_id, transaction_date, is_approved)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_savings_member_type_date
                      ON savings (member_id, type, transaction_date)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_members_coop_status_join
                      ON members (cooperative_id, status, join_date)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_journal_lines_account_entry
                      ON journal_lines (account_id, journal_entry_id)');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_users_cooperative_active
                      ON users (cooperative_id, is_active) WHERE deleted_at IS NULL');

        // Partial indexes for better performance
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_journal_entries_approved
                      ON journal_entries (cooperative_id, transaction_date) WHERE is_approved = true');

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_members_active
                      ON members (cooperative_id, join_date) WHERE status = \'active\'');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_journal_entries_query_optimization');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_savings_member_type_date');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_members_coop_status_join');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_journal_lines_account_entry');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_users_cooperative_active');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_journal_entries_approved');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_members_active');
    }
};
