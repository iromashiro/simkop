<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equity_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->integer('reporting_year');
            $table->string('transaction_description');
            $table->decimal('simpanan_pokok', 15, 2)->default(0);
            $table->decimal('simpanan_wajib', 15, 2)->default(0);
            $table->decimal('cadangan_umum', 15, 2)->default(0);
            $table->decimal('cadangan_risiko', 15, 2)->default(0);
            $table->decimal('sisa_hasil_usaha', 15, 2)->default(0);
            $table->decimal('ekuitas_lain', 15, 2)->default(0);
            $table->decimal('jumlah_ekuitas', 15, 2)->nullable();
            $table->enum('transaction_type', ['opening_balance', 'transaction', 'closing_balance']);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['cooperative_id', 'reporting_year', 'transaction_type']);
        });

        // ✅ CRITICAL FIX: Optimized trigger - only fire when relevant fields change
        DB::statement('
            CREATE OR REPLACE FUNCTION update_jumlah_ekuitas()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.jumlah_ekuitas = NEW.simpanan_pokok + NEW.simpanan_wajib + NEW.cadangan_umum +
                                   NEW.cadangan_risiko + NEW.sisa_hasil_usaha + NEW.ekuitas_lain;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER equity_changes_compute_total
            BEFORE INSERT OR UPDATE OF simpanan_pokok, simpanan_wajib, cadangan_umum, cadangan_risiko, sisa_hasil_usaha, ekuitas_lain ON equity_changes
            FOR EACH ROW EXECUTE FUNCTION update_jumlah_ekuitas();
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS equity_changes_compute_total ON equity_changes');
        DB::statement('DROP FUNCTION IF EXISTS update_jumlah_ekuitas()');
        Schema::dropIfExists('equity_changes');
    }
};
