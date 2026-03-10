<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'column_id',
        'title',
        'description',
        'due_at',
        'cover_color',
        'cover_attachment_id',
        'cover_size',
        'assigned_user_id',
        'is_done',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'is_done' => 'boolean',
            'position' => 'float',
        ];
    }

    public function coverAttachment(): BelongsTo
    {
        return $this->belongsTo(CardAttachment::class, 'cover_attachment_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'column_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(CardChecklistItem::class, 'card_id')->orderBy('position');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CardAttachment::class, 'card_id')->orderByDesc('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class, 'card_id')->orderByDesc('id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_label', 'card_id', 'label_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_user', 'card_id', 'user_id')->withTimestamps();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

