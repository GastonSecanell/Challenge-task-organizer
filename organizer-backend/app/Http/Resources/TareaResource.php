<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TareaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'etiqueta' => $this->etiqueta,
            'fecha_vencimiento' => optional($this->fecha_vencimiento)?->format('d/m/Y'),
            'prioridad_id' => $this->prioridad_id,
            'prioridad' => new PrioridadResource($this->whenLoaded('prioridad')),
            'etiquetas' => EtiquetaResource::collection($this->whenLoaded('etiquetas')),
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
