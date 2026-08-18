<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CoordenacaoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'carolina@institutolg.local'],
            [
                'name' => 'Carolina',
                'password' => 'password',
            ],
        );
    }
}
