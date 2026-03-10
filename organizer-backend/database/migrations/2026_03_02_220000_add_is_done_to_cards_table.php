<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->boolean('is_done')->default(false)->after('assigned_user_id');
            $table->index(['column_id', 'is_done']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['column_id', 'is_done']);
            $table->dropColumn('is_done');
        });
    }
};

