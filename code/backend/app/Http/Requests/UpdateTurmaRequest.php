<?php

namespace App\Http\Requests;

use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('turma'));
    }

    public function rules(): array
    {
        /** @var Turma $turma */
        $turma = $this->route('turma');

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('turmas', 'nome')
                    ->where('curso_id', $turma->curso_id)
                    ->ignore($turma->id),
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
