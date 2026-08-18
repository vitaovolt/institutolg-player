<?php

namespace App\Http\Requests;

use App\Models\Aula;
use App\Models\Disciplina;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Aula::class);
    }

    public function rules(): array
    {
        /** @var Disciplina $disciplina */
        $disciplina = $this->route('disciplina');

        return [
            'titulo' => [
                'required',
                'string',
                'max:255',
                Rule::unique('aulas', 'titulo')->where('disciplina_id', $disciplina->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('titulo')) {
            $this->merge(['titulo' => trim((string) $this->input('titulo'))]);
        }
    }
}
