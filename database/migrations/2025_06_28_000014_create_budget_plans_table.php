<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->integer('budget_year');
            $table->enum('budget_category', ['modal_tersedia', 'rencana_pendapatan', 'rencana_biaya']);
            $table->string('budget_item');
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->decimal('comparison_amount', 15, 2)->default(0);
            $table->decimal('variance_percentage', 5, 2)->nullable(); // ✅ FIXED: Remove storedAs
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['cooperative_id', 'budget_year', 'budget_category', 'budget_item']);
            $table->index(['cooperative_id', 'budget_year']);
        });

        // ✅ FIXED: Add computed column using PostgreSQL trigger
        DB::statement('
            CREATE OR REPLACE FUNCTION update_variance_percentage()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.variance_percentage = CASE
                    WHEN NEW.comparison_amount > 0 THEN
                        ((NEW.planned_amount - NEW.comparison_amount) / NEW.comparison_amount) * 100
                    ELSE 0
                END;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER budget_plans_compute_variance
            BEFORE INSERT OR UPDATE ON budget_plans
            FOR EACH ROW EXECUTE FUNCTION update_variance_percentage();
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS budget_plans_compute_variance ON budget_plans');
        DB::statement('DROP FUNCTION IF EXISTS update_variance_percentage()');
        Schema::dropIfExists('budget_plans');
    }
};
