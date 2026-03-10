<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Board extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'archived_at',
        'owner_id',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'is_favorite' => 'boolean',
        'owner_id' => 'integer',
    ];

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class, 'board_id')->orderBy('position');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class, 'board_id')->orderBy('position');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_user', 'board_id', 'user_id')
            ->withPivot(['is_favorite'])
            ->withTimestamps();
    }
}

