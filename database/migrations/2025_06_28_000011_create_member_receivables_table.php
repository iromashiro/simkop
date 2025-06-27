<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('member_name');
            $table->string('member_number', 20)->nullable();
            $table->integer('reporting_year');
            $table->decimal('receivable_amount', 15, 2)->default(0);
            $table->date('loan_date')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['current', 'overdue', 'restructured'])->default('current');
            $table->timestamps();

            $table->unique(['cooperative_id', 'member_name', 'reporting_year']);
            $table->index(['cooperative_id', 'reporting_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_receivables');
    }
};
