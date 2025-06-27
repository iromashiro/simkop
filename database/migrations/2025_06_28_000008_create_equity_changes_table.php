<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->decimal('jumlah_ekuitas', 15, 2)->storedAs('simpanan_pokok + simpanan_wajib + cadangan_umum + cadangan_risiko + sisa_hasil_usaha + ekuitas_lain');
            $table->enum('transaction_type', ['opening_balance', 'transaction', 'closing_balance']);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['cooperative_id', 'reporting_year', 'transaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equity_changes');
    }
};
