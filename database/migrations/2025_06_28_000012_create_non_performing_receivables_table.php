<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->decimal('total_receivable', 15, 2)->storedAs('dana_sp_internal + dana_ptba + dana_map');
            $table->integer('overdue_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['cooperative_id', 'member_name', 'reporting_year']);
            $table->index(['cooperative_id', 'reporting_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_performing_receivables');
    }
};
