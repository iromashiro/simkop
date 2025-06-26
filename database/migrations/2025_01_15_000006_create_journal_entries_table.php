<?php
// database/migrations/2025_01_15_000006_create_journal_entries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->foreignId('fiscal_period_id')->constrained()->onDelete('restrict');
            $table->string('entry_number', 50)->index()->comment('Sequential entry number');
            $table->date('transaction_date')->index();
            $table->text('description');
            $table->string('reference', 100)->nullable()->comment('External reference number');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->boolean('is_balanced')->storedAs('(total_debit = total_credit)')->index();
            $table->boolean('is_approved')->default(false)->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Ensure unique entry numbers per cooperative per fiscal year
            $table->unique(['cooperative_id', 'entry_number']);

            // Performance indexes
            $table->index(['cooperative_id', 'transaction_date']);
            $table->index(['cooperative_id', 'fiscal_period_id']);
            $table->index(['cooperative_id', 'is_approved']);
            $table->index(['transaction_date', 'is_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
