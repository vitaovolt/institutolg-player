<?php

namespace Database\Seeders;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Support\ValidarExportMp4;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BibliotecaPilotoSeeder extends Seeder
{
    public function run(): void
    {
        $curso = Curso::query()->firstOrCreate(
            ['nome' => 'Pós-graduação em Saúde'],
        );

        $turma = Turma::query()->firstOrCreate(
            ['curso_id' => $curso->id, 'nome' => 'Turma 2026-A'],
        );

        $disciplina = Disciplina::query()->firstOrCreate(
            ['turma_id' => $turma->id, 'nome' => 'Cardiologia'],
        );

        $agora = now();
        $mp4 = ValidarExportMp4::amostraValida();
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        $introducao = Aula::query()->firstOrCreate(
            ['disciplina_id' => $disciplina->id, 'titulo' => 'Introdução'],
            [
                'ordem' => 1,
                'status_preparo' => 'pronta',
                'status_drive' => 'ok',
                'publicada' => true,
                'publicada_em' => $agora,
                'enviado_em' => $agora,
            ],
        );

        $casos = Aula::query()->firstOrCreate(
            ['disciplina_id' => $disciplina->id, 'titulo' => 'Casos clínicos'],
            [
                'ordem' => 2,
                'status_preparo' => 'pronta',
                'status_drive' => 'enviando',
                'publicada' => true,
                'publicada_em' => $agora,
                'enviado_em' => $agora,
            ],
        );

        Aula::query()->firstOrCreate(
            ['disciplina_id' => $disciplina->id, 'titulo' => 'Revisão'],
            [
                'ordem' => 3,
                'status_preparo' => 'preparando',
                'status_drive' => 'pendente',
                'publicada' => false,
                'publicada_em' => null,
                'enviado_em' => $agora,
            ],
        );

        foreach ([$introducao, $casos] as $aula) {
            $play = 'play/'.$aula->id.'/seed.mp4';
            $disk->put($play, $mp4);
            $aula->update(['chave_play' => $play]);
        }

        Storage::disk((string) config('biblioteca.disk_drive'))->put(
            'copias/'.$introducao->id.'/'.$introducao->token_publico.'.mp4',
            $mp4,
        );
    }
}
