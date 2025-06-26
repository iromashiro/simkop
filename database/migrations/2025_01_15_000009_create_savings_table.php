<?php
// database/migrations/2025_01_15_000009_create_savings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['pokok', 'wajib', 'khusus', 'sukarela'])->index()
                ->comment('pokok=Share Capital, wajib=Mandatory, khusus=Special, sukarela=Voluntary');
            $table->date('transaction_date')->index();
            $table->decimal('amount', 15, 2)->comment('Transaction amount (positive for deposits, negative for withdrawals)');
            $table->decimal('balance_after', 15, 2)->comment('Balance after this transaction');
            $table->string('description')->nullable();
            $table->string('reference', 100)->nullable()->comment('External reference number');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            // Performance indexes
            $table->index(['member_id', 'type']);
            $table->index(['member_id', 'transaction_date']);
            $table->index(['transaction_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings');
    }
};
