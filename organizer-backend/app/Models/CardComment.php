<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardComment extends Model
{
    protected $fillable = [
        'card_id',
        'user_id',
        'parent_id',
        'body',
    ];

    protected $casts = [
        'card_id' => 'integer',
        'user_id' => 'integer',
        'parent_id' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }
}

