<?php
// database/migrations/2025_01_15_000007_create_journal_lines_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('restrict');
            $table->string('description')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->timestamps();

            // Ensure either debit or credit, not both
            $table->check('(debit_amount > 0 AND credit_amount = 0) OR (debit_amount = 0 AND credit_amount > 0)');

            // Performance indexes
            $table->index(['journal_entry_id', 'account_id']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
