<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->decimal('total_shu', 15, 2)->storedAs('jasa_simpanan + jasa_pinjaman');
            $table->decimal('simpanan_participation', 15, 2)->default(0);
            $table->decimal('pinjaman_participation', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['cooperative_id', 'member_name', 'reporting_year']);
            $table->index(['cooperative_id', 'reporting_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_distribution');
    }
};
