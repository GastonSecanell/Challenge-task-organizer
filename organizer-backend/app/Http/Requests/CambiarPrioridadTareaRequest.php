<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarPrioridadTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prioridad_id' => ['required', 'integer', 'exists:prioridades,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'prioridad_id.required' => 'La prioridad es obligatoria.',
            'prioridad_id.integer' => 'La prioridad debe ser un número válido.',
            'prioridad_id.exists' => 'La prioridad seleccionada no existe.',
        ];
    }
}
