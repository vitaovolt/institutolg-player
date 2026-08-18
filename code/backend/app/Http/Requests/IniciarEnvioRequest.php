<?php

namespace App\Http\Requests;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IniciarEnvioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Aula::class);
    }

    public function rules(): array
    {
        /** @var Disciplina $disciplina */
        $disciplina = $this->route('disciplina');
        $chave = (string) $this->input('chave_idempotencia', '');
        $existente = $chave !== ''
            ? Aula::query()->where('chave_idempotencia', $chave)->first()
            : null;

        $titulo = ['required', 'string', 'max:255'];

        if ($existente === null) {
            $titulo[] = Rule::unique('aulas', 'titulo')->where('disciplina_id', $disciplina->id);
        }

        return [
            'titulo' => $titulo,
            'chave_idempotencia' => ['required', 'uuid'],
            'tamanho_bytes' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.(int) config('biblioteca.upload_max_bytes'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.unique' => 'Já existe uma aula com este título nesta disciplina.',
            'tamanho_bytes.max' => ValidarExportMp4::mensagemGrandeDemais(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $fromHeader = $this->header('Idempotency-Key');

        if (is_string($fromHeader) && $fromHeader !== '' && ! $this->filled('chave_idempotencia')) {
            $this->merge(['chave_idempotencia' => $fromHeader]);
        }

        if ($this->exists('titulo')) {
            $this->merge(['titulo' => trim((string) $this->input('titulo'))]);
        }
    }
}
