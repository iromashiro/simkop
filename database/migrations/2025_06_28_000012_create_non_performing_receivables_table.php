<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_performing_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('member_name');
            $table->string('member_number', 20)->nullable();
            $table->integer('reporting_year');
            $table->decimal('dana_sp_internal', 15, 2)->default(0);
            $table->decimal('dana_ptba', 15, 2)->default(0);
            $table->decimal('dana_map', 15, 2)->default(0);
            $table->decimal('total_receivable', 15, 2)->nullable(); // ✅ FIXED: Remove storedAs
            $table->integer('overdue_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['cooperative_id', 'member_name', 'reporting_year']);
            $table->index(['cooperative_id', 'reporting_year']);
        });

        // ✅ FIXED: Add computed column using PostgreSQL trigger
        DB::statement('
            CREATE OR REPLACE FUNCTION update_total_receivable()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.total_receivable = NEW.dana_sp_internal + NEW.dana_ptba + NEW.dana_map;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER npl_receivables_compute_total
            BEFORE INSERT OR UPDATE ON non_performing_receivables
            FOR EACH ROW EXECUTE FUNCTION update_total_receivable();
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS npl_receivables_compute_total ON non_performing_receivables');
        DB::statement('DROP FUNCTION IF EXISTS update_total_receivable()');
        Schema::dropIfExists('non_performing_receivables');
    }
};
