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

            // Explicitly name the unique constraint to avoid conflicts
            $table->unique(
                ['cooperative_id', 'reporting_year', 'account_code'],
                'isa_coop_year_code_unique'
            );

            // Explicitly name the index to avoid conflicts
            $table->index(
                ['cooperative_id', 'reporting_year', 'account_category'],
                'isa_coop_year_category_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_statement_accounts');
    }
};
