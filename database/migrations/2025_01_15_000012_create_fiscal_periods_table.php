<?php
// database/migrations/2025_01_15_000012_create_fiscal_periods_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed', 'locked'])->default('open');
            $table->boolean('is_current')->default(false);
            $table->date('closed_date')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['cooperative_id', 'status']);
            $table->index(['cooperative_id', 'is_current']);
            $table->unique(['cooperative_id', 'start_date']);
        });

        // Add fiscal_period_id to journal_entries
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('fiscal_period_id')->after('cooperative_id')->nullable()->constrained()->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['fiscal_period_id']);
            $table->dropColumn('fiscal_period_id');
        });

        Schema::dropIfExists('fiscal_periods');
    }
};
