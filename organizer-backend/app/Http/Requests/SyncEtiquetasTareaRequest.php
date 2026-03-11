<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncEtiquetasTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'etiquetas' => ['nullable', 'array'],
            'etiquetas.*' => ['integer', 'exists:etiquetas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'etiquetas.array' => 'Las etiquetas deben enviarse en formato lista.',
            'etiquetas.*.exists' => 'Una o más etiquetas seleccionadas no existen.',
        ];
    }
}
