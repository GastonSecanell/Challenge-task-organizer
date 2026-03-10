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
            'fecha_vencimiento' => optional($this->fecha_vencimiento)?->format('Y-m-d'),
            'prioridad_id' => $this->prioridad_id,
            'prioridad' => new PrioridadResource($this->whenLoaded('prioridad')),
            'etiquetas' => EtiquetaResource::collection($this->whenLoaded('etiquetas')),
            'created_at' => optional($this->created_at)?->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)?->format('Y-m-d H:i:s'),
        ];
    }
}
