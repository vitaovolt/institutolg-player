<?php

namespace Database\Factories;

use App\Models\Disciplina;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disciplina>
 */
class DisciplinaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'nome' => fake()->unique()->words(2, true),
        ];
    }
}
