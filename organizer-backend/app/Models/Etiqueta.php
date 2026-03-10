<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Etiqueta extends Model
{
    protected $table = 'etiquetas';

    protected $fillable = [
        'etiqueta',
    ];

    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'etiqueta_tarea', 'etiqueta_id', 'tarea_id');
    }
}
