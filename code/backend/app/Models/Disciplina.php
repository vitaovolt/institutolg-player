<?php

namespace App\Models;

use Database\Factories\DisciplinaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Disciplina extends Model
{
    /** @use HasFactory<DisciplinaFactory> */
    use HasFactory;

    protected $fillable = [
        'turma_id',
        'nome',
    ];

    public function scopeOrdenadasPorNome($query)
    {
        return $query->orderBy('nome');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function aulas(): HasMany
    {
        return $this->hasMany(Aula::class);
    }
}
