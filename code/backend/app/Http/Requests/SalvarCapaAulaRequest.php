<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalvarCapaAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('aula'));
    }

    public function rules(): array
    {
        $maxKb = max(1, (int) ceil(((int) config('biblioteca.capa_max_bytes')) / 1024));

        return [
            'capa' => ['required', 'file', 'max:'.$maxKb, 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'capa.required' => 'Escolha uma foto JPG ou PNG para a capa.',
            'capa.mimes' => 'Tipo de arquivo não permitido. Envie uma foto JPG ou PNG para a capa da aula.',
            'capa.max' => 'A foto é grande demais. Envie um JPG ou PNG de até 2 MB.',
        ];
    }
}
