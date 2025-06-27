<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->integer('reporting_year');
            $table->enum('activity_category', ['operating', 'investing', 'financing']);
            $table->string('activity_description');
            $table->decimal('current_year_amount', 15, 2)->default(0);
            $table->decimal('previous_year_amount', 15, 2)->default(0);
            $table->boolean('is_subtotal')->default(false);
            $table->boolean('is_total')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['cooperative_id', 'reporting_year', 'activity_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_activities');
    }
};
