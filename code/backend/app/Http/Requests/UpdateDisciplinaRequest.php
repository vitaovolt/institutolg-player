<?php

namespace App\Http\Requests;

use App\Models\Disciplina;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('disciplina'));
    }

    public function rules(): array
    {
        /** @var Disciplina $disciplina */
        $disciplina = $this->route('disciplina');

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('disciplinas', 'nome')
                    ->where('turma_id', $disciplina->turma_id)
                    ->ignore($disciplina->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('nome')) {
            $this->merge(['nome' => trim((string) $this->input('nome'))]);
        }
    }
}
