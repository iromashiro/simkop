<?php
// database/migrations/2025_01_15_000017_create_shu_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shu_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->year('calculation_year');
            $table->decimal('total_shu', 15, 2);
            $table->decimal('member_portion_percentage', 5, 2)->default(60);
            $table->decimal('member_portion_amount', 15, 2);
            $table->decimal('cooperative_portion_percentage', 5, 2)->default(40);
            $table->decimal('cooperative_portion_amount', 15, 2);
            $table->decimal('savings_weight', 5, 2)->default(25);
            $table->decimal('loan_weight', 5, 2)->default(25);
            $table->decimal('transaction_weight', 5, 2)->default(25);
            $table->decimal('membership_weight', 5, 2)->default(25);
            $table->enum('status', ['draft', 'approved', 'distributed'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['cooperative_id', 'calculation_year']);
            $table->index(['cooperative_id', 'status']);
        });

        Schema::create('shu_member_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shu_calculation_id')->constrained()->onDelete('cascade');
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->decimal('savings_contribution', 15, 2)->default(0);
            $table->decimal('loan_contribution', 15, 2)->default(0);
            $table->decimal('transaction_contribution', 15, 2)->default(0);
            $table->decimal('membership_contribution', 15, 2)->default(0);
            $table->decimal('total_contribution', 15, 2);
            $table->decimal('shu_amount', 15, 2);
            $table->enum('status', ['pending', 'distributed'])->default('pending');
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();

            $table->index(['shu_calculation_id', 'status']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_member_distributions');
        Schema::dropIfExists('shu_calculations');
    }
};
