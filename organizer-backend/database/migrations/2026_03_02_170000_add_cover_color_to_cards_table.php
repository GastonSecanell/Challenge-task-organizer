<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('cover_color', 32)->nullable()->after('due_at');
            $table->index('cover_color');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['cover_color']);
            $table->dropColumn('cover_color');
        });
    }
};

