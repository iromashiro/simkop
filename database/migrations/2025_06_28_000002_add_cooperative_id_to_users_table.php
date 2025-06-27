<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cooperative_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('last_login')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['cooperative_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cooperative_id']);
            $table->dropColumn(['cooperative_id', 'last_login', 'last_login_ip', 'user_agent']);
        });
    }
};
