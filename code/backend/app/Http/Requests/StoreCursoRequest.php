<?php

namespace App\Http\Requests;

use App\Models\Curso;
use Illuminate\Foundation\Http\FormRequest;

class StoreCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Curso::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255', 'unique:cursos,nome'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('nome')) {
            $this->merge(['nome' => trim((string) $this->input('nome'))]);
        }
    }
}
