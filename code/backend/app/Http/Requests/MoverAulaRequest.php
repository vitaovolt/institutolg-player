<?php

namespace App\Http\Requests;

use App\Models\Aula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoverAulaRequest extends FormRequest
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
            'disciplina_id' => [
                'required',
                'integer',
                'exists:disciplinas,id',
                Rule::notIn([(int) $aula->disciplina_id, (string) $aula->disciplina_id]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'disciplina_id.required' => 'Escolha a disciplina de destino.',
            'disciplina_id.exists' => 'Disciplina não encontrada.',
            'disciplina_id.not_in' => 'A aula já está nesta disciplina.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Aula $aula */
            $aula = $this->route('aula');
            $destinoId = (int) $this->input('disciplina_id');

            $existe = Aula::query()
                ->where('disciplina_id', $destinoId)
                ->where('titulo', $aula->titulo)
                ->whereKeyNot($aula->id)
                ->exists();

            if ($existe) {
                $validator->errors()->add(
                    'disciplina_id',
                    'Já existe uma aula com este título nesta disciplina.',
                );
            }
        });
    }
}
