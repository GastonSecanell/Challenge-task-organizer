<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardChecklistItem extends Model
{
    protected $fillable = [
        'card_id',
        'text',
        'is_done',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'position' => 'float',
            'card_id' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}

