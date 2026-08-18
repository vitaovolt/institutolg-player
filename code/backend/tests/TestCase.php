<?php

namespace Tests;

use App\Models\Aula;
use App\Models\User;
use App\Support\ValidarExportMp4;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function comoCoordenacao(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function comoInativo(): User
    {
        $user = User::factory()->inativo()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function fakeDiscosDaBiblioteca(): void
    {
        Storage::fake((string) config('biblioteca.disk_aulas'));
        Storage::fake((string) config('biblioteca.disk_drive'));
    }

    protected function gravarPlay(Aula $aula): Aula
    {
        $play = 'play/'.$aula->id.'/teste.mp4';
        Storage::disk((string) config('biblioteca.disk_aulas'))->put($play, ValidarExportMp4::amostraValida());
        $aula->update([
            'chave_play' => $play,
            'status_preparo' => 'pronta',
        ]);

        return $aula->fresh();
    }
}
