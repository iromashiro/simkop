<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('cooperative_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type', 50);
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->json('data')->nullable();
            $table->timestamps();

            // Performance indexes with explicit names
            $table->index(
                ['user_id', 'is_read', 'created_at'],
                'notif_user_read_created_idx'
            );
            $table->index(
                ['user_id', 'type'],
                'notif_user_type_idx'
            );
            $table->index(
                ['cooperative_id', 'created_at'],
                'notif_coop_created_idx'
            );
            $table->index(
                'created_at',
                'notif_created_idx'
            ); // For cleanup operations
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
