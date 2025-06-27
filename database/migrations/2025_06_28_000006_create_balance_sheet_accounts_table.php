<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_sheet_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->integer('reporting_year');
            $table->string('account_code', 10);
            $table->string('account_name');
            $table->enum('account_category', ['asset', 'liability', 'equity']);
            $table->enum('account_subcategory', [
                'current_asset',
                'fixed_asset',
                'other_asset',
                'current_liability',
                'long_term_liability',
                'member_equity',
                'retained_earnings',
                'other_equity'
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
        Schema::dropIfExists('balance_sheet_accounts');
    }
};
