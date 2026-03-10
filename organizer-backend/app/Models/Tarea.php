<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'titulo',
        'descripcion',
        'estado',
        'fecha_vencimiento',
        'prioridad_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
        ];
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'prioridad_id');
    }

    public function etiquetas(): BelongsToMany
    {
        return $this->belongsToMany(Etiqueta::class, 'etiqueta_tarea', 'tarea_id', 'etiqueta_id');
    }

    public function scopeBusqueda(Builder $query, ?string $busqueda): Builder
    {
        if (!$busqueda) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($busqueda) {
            $q->where('titulo', 'like', '%' . $busqueda . '%')
              ->orWhere('descripcion', 'like', '%' . $busqueda . '%');
        });
    }

    public function scopePorEstado(Builder $query, ?string $estado): Builder
    {
        if (!$estado) {
            return $query;
        }

        return $query->where('estado', $estado);
    }

    public function scopePorPrioridad(Builder $query, ?int $prioridadId): Builder
    {
        if (!$prioridadId) {
            return $query;
        }

        return $query->where('prioridad_id', $prioridadId);
    }

    public function scopePorFechaVencimiento(Builder $query, ?string $fechaVencimiento): Builder
    {
        if (!$fechaVencimiento) {
            return $query;
        }

        return $query->whereDate('fecha_vencimiento', $fechaVencimiento);
    }
}
