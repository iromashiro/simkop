<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->decimal('variance_percentage', 5, 2)->storedAs('CASE WHEN comparison_amount > 0 THEN ((planned_amount - comparison_amount) / comparison_amount) * 100 ELSE 0 END');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['cooperative_id', 'budget_year', 'budget_category', 'budget_item']);
            $table->index(['cooperative_id', 'budget_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plans');
    }
};
