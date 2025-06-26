<?php
// database/migrations/2025_01_15_000011_create_savings_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('minimum_balance', 15, 2)->default(0);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->enum('interest_calculation', ['daily', 'monthly', 'yearly'])->default('monthly');
            $table->decimal('withdrawal_fee', 15, 2)->default(0);
            $table->integer('max_withdrawals_per_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['cooperative_id', 'is_active']);
        });

        // Add foreign key to existing savings_accounts table
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->foreignId('savings_product_id')->after('member_id')->constrained()->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropForeign(['savings_product_id']);
            $table->dropColumn('savings_product_id');
        });

        Schema::dropIfExists('savings_products');
    }
};
