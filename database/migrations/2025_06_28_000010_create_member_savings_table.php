<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('member_name');
            $table->string('member_number', 20)->nullable();
            $table->integer('reporting_year');
            $table->decimal('simpanan_pokok', 15, 2)->default(0);
            $table->decimal('simpanan_wajib', 15, 2)->default(0);
            $table->decimal('simpanan_khusus', 15, 2)->default(0);
            $table->decimal('simpanan_sukarela', 15, 2)->default(0);
            $table->decimal('total_simpanan', 15, 2)->nullable(); // ✅ FIXED: Remove storedAs
            $table->timestamps();

            $table->unique(['cooperative_id', 'member_name', 'reporting_year']);
            $table->index(['cooperative_id', 'reporting_year']);
        });

        // ✅ FIXED: Add computed column using PostgreSQL trigger
        DB::statement('
            CREATE OR REPLACE FUNCTION update_total_simpanan()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.total_simpanan = NEW.simpanan_pokok + NEW.simpanan_wajib + NEW.simpanan_khusus + NEW.simpanan_sukarela;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER member_savings_compute_total
            BEFORE INSERT OR UPDATE ON member_savings
            FOR EACH ROW EXECUTE FUNCTION update_total_simpanan();
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS member_savings_compute_total ON member_savings');
        DB::statement('DROP FUNCTION IF EXISTS update_total_simpanan()');
        Schema::dropIfExists('member_savings');
    }
};
