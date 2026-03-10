<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTareaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string'],
            'estado' => ['required', 'in:pendiente,en_progreso,completada'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'prioridad_id' => ['required', 'integer', 'exists:prioridades,id'],
            'etiquetas' => ['nullable', 'array'],
            'etiquetas.*' => ['integer', 'exists:etiquetas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'titulo.max' => 'El título no puede superar los 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
            'prioridad_id.required' => 'La prioridad es obligatoria.',
            'prioridad_id.exists' => 'La prioridad seleccionada no existe.',
            'etiquetas.array' => 'Las etiquetas deben enviarse en formato lista.',
            'etiquetas.*.exists' => 'Una o más etiquetas seleccionadas no existen.',
        ];
    }
}
