<?php
// database/migrations/2025_01_15_000004_create_accounts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('code', 20)->index()->comment('Account code (e.g., 1100, 2200)');
            $table->string('name')->index();
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])->index();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->tinyInteger('level')->default(1)->index()->comment('Hierarchy level');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false)->comment('System accounts cannot be deleted');
            $table->text('description')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Ensure unique account codes per cooperative
            $table->unique(['cooperative_id', 'code']);

            // Performance indexes
            $table->index(['cooperative_id', 'type', 'is_active']);
            $table->index(['cooperative_id', 'parent_id']);
            $table->index(['cooperative_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
