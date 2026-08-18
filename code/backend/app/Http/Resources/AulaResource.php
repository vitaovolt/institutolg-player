<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Aula */
class AulaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disciplina_id' => $this->disciplina_id,
            'titulo' => $this->titulo,
            'ordem' => $this->ordem,
            'token_publico' => $this->token_publico,
            'status_preparo' => $this->status_preparo,
            'status_drive' => $this->status_drive,
            'publicada' => $this->publicada,
            'publicada_em' => $this->publicada_em?->toIso8601String(),
            'enviado_em' => $this->enviado_em?->toIso8601String(),
            'mensagem_erro' => $this->mensagem_erro,
            'tamanho_bytes' => $this->tamanho_bytes,
            'tem_arquivo' => filled($this->chave_arquivo),
            'pronta_para_assistir' => $this->estaProntaParaAssistir(),
            'tem_capa' => filled($this->chave_capa),
            'url_capa' => $this->urlCapa(),
            'html_iframe' => $this->htmlIframe(),
            'url_player' => $this->urlPlayer(),
            'url_demonstracao_eduq' => $this->urlPlayer()
                ? url('/eduq/'.$this->token_publico)
                : null,
            'disciplina' => new DisciplinaResource($this->whenLoaded('disciplina')),
        ];
    }
}
