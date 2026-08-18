<?php

namespace Database\Factories;

use App\Models\Aula;
use App\Models\Disciplina;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Aula>
 */
class AulaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'disciplina_id' => Disciplina::factory(),
            'titulo' => fake()->unique()->sentence(3),
            'ordem' => fake()->numberBetween(1, 20),
            'token_publico' => (string) Str::uuid(),
            'status_preparo' => 'rascunho',
            'status_drive' => 'pendente',
            'publicada' => false,
            'publicada_em' => null,
            'enviado_em' => null,
            'mensagem_erro' => null,
        ];
    }

    public function enviada(): static
    {
        return $this->state(fn () => [
            'status_preparo' => 'pronta',
            'enviado_em' => now(),
        ]);
    }

    public function publicada(): static
    {
        return $this->enviada()->state(fn () => [
            'publicada' => true,
            'publicada_em' => now(),
            'status_drive' => 'ok',
        ]);
    }

    public function preparando(): static
    {
        return $this->state(fn () => [
            'status_preparo' => 'preparando',
            'enviado_em' => now(),
            'publicada' => false,
        ]);
    }
}
