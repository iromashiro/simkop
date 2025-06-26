<?php
// database/migrations/2025_01_15_000018_create_budget_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->year('budget_year');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'active', 'closed'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['cooperative_id', 'budget_year']);
            $table->index(['cooperative_id', 'status']);
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->decimal('budget_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'account_id']);
        });

        Schema::create('budget_monthly_breakdown', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_item_id')->constrained()->onDelete('cascade');
            $table->integer('month'); // 1-12
            $table->decimal('budget_amount', 15, 2);
            $table->timestamps();

            $table->unique(['budget_item_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_monthly_breakdown');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budgets');
    }
};
