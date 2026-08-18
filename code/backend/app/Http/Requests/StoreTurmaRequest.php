<?php

namespace App\Http\Requests;

use App\Models\Curso;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTurmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Turma::class);
    }

    public function rules(): array
    {
        /** @var Curso $curso */
        $curso = $this->route('curso');

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('turmas', 'nome')->where('curso_id', $curso->id),
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
