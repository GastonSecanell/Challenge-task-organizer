<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prioridad extends Model
{
    use HasFactory;

    protected $table = 'prioridades';

    protected $fillable = [
        'prioridad',
    ];

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'prioridad_id');
    }
}
