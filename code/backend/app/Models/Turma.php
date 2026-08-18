<?php

namespace App\Models;

use Database\Factories\TurmaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    /** @use HasFactory<TurmaFactory> */
    use HasFactory;

    protected $fillable = [
        'curso_id',
        'nome',
    ];

    public function scopeOrdenadasPorNome($query)
    {
        return $query->orderBy('nome');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function disciplinas(): HasMany
    {
        return $this->hasMany(Disciplina::class);
    }
}
