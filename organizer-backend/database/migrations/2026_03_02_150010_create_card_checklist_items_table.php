<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('text');
            $table->boolean('is_done')->default(false);
            $table->decimal('position', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['card_id', 'position']);
            $table->index(['card_id', 'is_done']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_checklist_items');
    }
};

