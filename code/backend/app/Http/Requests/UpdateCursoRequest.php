<?php

namespace App\Http\Requests;

use App\Models\Curso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('curso'));
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
                Rule::unique('cursos', 'nome')->ignore($curso->id),
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
