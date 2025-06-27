<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_distribution', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('member_name');
            $table->string('member_number', 20)->nullable();
            $table->integer('reporting_year');
            $table->decimal('jasa_simpanan', 15, 2)->default(0);
            $table->decimal('jasa_pinjaman', 15, 2)->default(0);
            $table->decimal('total_shu', 15, 2)->nullable(); // ✅ FIXED: Remove storedAs
            $table->decimal('simpanan_participation', 15, 2)->default(0);
            $table->decimal('pinjaman_participation', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['cooperative_id', 'member_name', 'reporting_year']);
            $table->index(['cooperative_id', 'reporting_year']);
        });

        // ✅ FIXED: Add computed column using PostgreSQL trigger
        DB::statement('
            CREATE OR REPLACE FUNCTION update_total_shu()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.total_shu = NEW.jasa_simpanan + NEW.jasa_pinjaman;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER shu_distribution_compute_total
            BEFORE INSERT OR UPDATE ON shu_distribution
            FOR EACH ROW EXECUTE FUNCTION update_total_shu();
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS shu_distribution_compute_total ON shu_distribution');
        DB::statement('DROP FUNCTION IF EXISTS update_total_shu()');
        Schema::dropIfExists('shu_distribution');
    }
};
