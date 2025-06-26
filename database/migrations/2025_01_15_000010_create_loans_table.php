<?php
// database/migrations/2025_01_15_000010_create_loans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create loan_products table
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('min_term_months')->default(1);
            $table->integer('max_term_months');
            $table->json('requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['cooperative_id', 'is_active']);
        });

        // Create loan_accounts table
        Schema::create('loan_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_product_id')->constrained()->onDelete('restrict');
            $table->string('account_number')->unique();
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('term_months');
            $table->decimal('monthly_payment', 15, 2);
            $table->decimal('outstanding_balance', 15, 2);
            $table->enum('status', ['pending', 'approved', 'disbursed', 'active', 'completed', 'defaulted', 'closed'])->default('pending');
            $table->date('application_date');
            $table->date('approved_date')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->text('purpose')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('disbursed_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['cooperative_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['account_number']);
        });

        // Create loan_payments table
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamps();

            $table->index(['loan_account_id', 'payment_date']);
        });

        // Create loan_payment_schedules table
        Schema::create('loan_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->onDelete('cascade');
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->boolean('is_paid')->default(false);
            $table->date('paid_date')->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['loan_account_id', 'due_date']);
            $table->index(['due_date', 'is_paid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payment_schedules');
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loan_accounts');
        Schema::dropIfExists('loan_products');
    }
};
