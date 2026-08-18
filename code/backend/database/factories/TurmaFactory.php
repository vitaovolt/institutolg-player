<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turma>
 */
class TurmaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'nome' => 'Turma '.fake()->unique()->bothify('20##-?'),
        ];
    }
}
