<?php

namespace App\Models;

use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use HasFactory;

    protected $fillable = [
        'nome',
        'drive_folder_id',
    ];

    protected $hidden = [
        'drive_folder_id',
    ];

    public function scopeOrdenadosPorNome($query)
    {
        return $query->orderBy('nome');
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
