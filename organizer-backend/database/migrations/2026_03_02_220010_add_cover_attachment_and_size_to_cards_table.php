<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('cover_attachment_id')
                ->nullable()
                ->after('cover_color')
                ->constrained('card_attachments')
                ->nullOnDelete();

            $table->string('cover_size', 16)->default('small')->after('cover_attachment_id');

            $table->index(['cover_attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['cover_attachment_id']);
            $table->dropIndex(['cover_attachment_id']);
            $table->dropColumn(['cover_attachment_id', 'cover_size']);
        });
    }
};

