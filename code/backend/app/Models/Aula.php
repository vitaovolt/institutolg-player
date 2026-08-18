<?php

namespace App\Models;

use Database\Factories\AulaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Aula extends Model
{
    /** @use HasFactory<AulaFactory> */
    use HasFactory;

    public const STATUS_PREPARO = ['rascunho', 'enviando', 'preparando', 'pronta', 'erro'];

    public const STATUS_DRIVE = ['pendente', 'enviando', 'ok', 'erro'];

    protected $fillable = [
        'disciplina_id',
        'titulo',
        'ordem',
        'token_publico',
        'status_preparo',
        'status_drive',
        'publicada',
        'publicada_em',
        'enviado_em',
        'mensagem_erro',
        'chave_idempotencia',
        'token_upload',
        'chave_arquivo',
        'chave_play',
        'chave_capa',
        'tamanho_bytes',
    ];

    protected $hidden = [
        'token_upload',
        'chave_arquivo',
        'chave_play',
        'chave_capa',
        'chave_idempotencia',
    ];

    protected $attributes = [
        'status_preparo' => 'rascunho',
        'status_drive' => 'pendente',
        'publicada' => false,
    ];

    protected function casts(): array
    {
        return [
            'publicada' => 'boolean',
            'publicada_em' => 'datetime',
            'enviado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Aula $aula): void {
            $aula->token_publico ??= (string) Str::uuid();

            if ($aula->ordem === null) {
                $aula->ordem = (int) static::query()
                    ->where('disciplina_id', $aula->disciplina_id)
                    ->max('ordem') + 1;
            }
        });
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('ordem')->orderBy('id');
    }

    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('publicada', true);
    }

    public function scopeEnviadasNoMes(Builder $query, Carbon $inicio, Carbon $fim): Builder
    {
        return $query
            ->whereNotNull('enviado_em')
            ->whereBetween('enviado_em', [$inicio, $fim]);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function estaProntaParaAssistir(): bool
    {
        return $this->status_preparo === 'pronta' && filled($this->chave_play);
    }

    public function estaDisponivelParaAluno(): bool
    {
        return $this->estaProntaParaAssistir() && $this->publicada === true;
    }

    public function urlPlayer(): ?string
    {
        if (! filled($this->token_publico) || $this->status_preparo !== 'pronta') {
            return null;
        }

        return url('/assistir/'.$this->token_publico);
    }

    public function htmlIframe(): ?string
    {
        $src = $this->urlPlayer();

        if ($src === null) {
            return null;
        }

        return '<iframe src="'.$src.'" width="100%" height="480" frameborder="0" allowfullscreen></iframe>';
    }

    public function urlCapa(): ?string
    {
        if (! filled($this->chave_capa) || ! filled($this->token_publico)) {
            return null;
        }

        return url('/capa/'.$this->token_publico);
    }
}
