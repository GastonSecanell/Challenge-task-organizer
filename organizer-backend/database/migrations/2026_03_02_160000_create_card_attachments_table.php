<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_attachments');
    }
};

