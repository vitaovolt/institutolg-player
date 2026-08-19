<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alvo = $this->route('user');

        return $alvo instanceof User && (bool) $this->user()?->can('update', $alvo);
    }

    public function rules(): array
    {
        /** @var User $alvo */
        $alvo = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($alvo->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
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
            /** @var User|null $alvo */
            $alvo = $this->route('user');
            if (! $alvo instanceof User) {
                return;
            }

            if ($this->exists('ativo') && $this->boolean('ativo') === false && $alvo->is($this->user())) {
                $validator->errors()->add('ativo', 'Você não pode desativar a própria conta.');
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
        if ($this->exists('password') && trim((string) $this->input('password')) === '') {
            $this->merge(['password' => null]);
        }
    }
}
