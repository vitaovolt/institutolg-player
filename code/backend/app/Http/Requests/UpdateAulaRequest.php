<?php

namespace App\Http\Requests;

use App\Models\Aula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('aula'));
    }

    public function rules(): array
    {
        /** @var Aula $aula */
        $aula = $this->route('aula');

        return [
            'titulo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('aulas', 'titulo')
                    ->where('disciplina_id', $aula->disciplina_id)
                    ->ignore($aula->id),
            ],
            'ordem' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('titulo')) {
            $this->merge(['titulo' => trim((string) $this->input('titulo'))]);
        }
    }
}
