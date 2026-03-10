<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('cover_color')->constrained('users')->nullOnDelete();
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['assigned_user_id']);
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};

