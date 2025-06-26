<?php
// database/migrations/2025_01_15_000008_create_members_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cooperative_id')->constrained()->onDelete('cascade');
            $table->string('member_number', 50)->index()->comment('Unique member identifier');
            $table->string('name')->index();
            $table->string('id_number', 50)->nullable()->comment('KTP/ID card number');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('join_date')->index();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->json('additional_info')->nullable()->comment('Additional member information');
            $table->timestamps();
            $table->softDeletes();

            // Ensure unique member numbers per cooperative
            $table->unique(['cooperative_id', 'member_number']);

            // Performance indexes
            $table->index(['cooperative_id', 'status']);
            $table->index(['cooperative_id', 'join_date']);
            $table->index(['cooperative_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
