<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_statement_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->integer('reporting_year');
            $table->string('account_code', 10);
            $table->string('account_name');
            $table->enum('account_category', ['revenue', 'expense', 'other_income', 'other_expense']);
            $table->enum('account_subcategory', [
                'member_participation',
                'non_member_participation',
                'operating_expense',
                'administrative_expense',
                'financial_expense',
                'other_operating_income',
                'extraordinary_income',
                'other_operating_expense',
                'extraordinary_expense'
            ])->nullable();
            $table->decimal('current_year_amount', 15, 2)->default(0);
            $table->decimal('previous_year_amount', 15, 2)->default(0);
            $table->string('note_reference', 5)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['cooperative_id', 'reporting_year', 'account_code']);
            $table->index(['cooperative_id', 'reporting_year', 'account_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_statement_accounts');
    }
};
