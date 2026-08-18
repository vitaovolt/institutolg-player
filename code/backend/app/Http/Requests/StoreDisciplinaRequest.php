<?php

namespace App\Http\Requests;

use App\Models\Disciplina;
use App\Models\Turma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisciplinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Disciplina::class);
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
                Rule::unique('disciplinas', 'nome')->where('turma_id', $turma->id),
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
