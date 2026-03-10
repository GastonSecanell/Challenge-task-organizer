<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAttachment extends Model
{
    protected $fillable = [
        'card_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'thumbnail_path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'card_id' => 'integer',
            'uploaded_by' => 'integer',
            'size' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
