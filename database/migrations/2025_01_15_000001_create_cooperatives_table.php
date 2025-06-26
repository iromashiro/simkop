<?php
// database/migrations/2025_01_15_000001_create_cooperatives_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperatives', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('kementerian_id', 100)->unique()->comment('Ministry registration ID');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('registration_date');
            $table->tinyInteger('fiscal_year_start')->default(1)->comment('1=January, 7=July');
            $table->json('settings')->nullable()->comment('Cooperative-specific settings');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index('registration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperatives');
    }
};
