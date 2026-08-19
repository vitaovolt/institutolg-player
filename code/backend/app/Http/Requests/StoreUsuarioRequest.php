<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
            'ativo' => 'ativo',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $limite = (int) config('biblioteca.max_logins_painel');
            if (User::query()->count() >= $limite) {
                $validator->errors()->add('email', "O painel admite no máximo {$limite} contas.");
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
        if ($this->exists('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
