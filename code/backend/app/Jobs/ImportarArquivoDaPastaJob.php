<?php

namespace App\Jobs;

use App\Actions\PublicarAulaSeNova;
use App\Actions\SalvarCapaDaAula;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\CaminhoDaBiblioteca;
use App\Support\RelatorioImportacaoPasta;
use App\Support\ValidarExportMp4;
use App\Support\ValidarFotoCapa;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportarArquivoDaPastaJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 43200;

    public int $uniqueFor = 43200;

    public function __construct(
        public int $disciplinaId,
        public string $driveFileId,
        public string $titulo,
        public int $tamanho,
        public ?string $capaFileId = null,
        public int $capaTamanho = 0,
    ) {
        $this->onQueue((string) config('biblioteca.queue_preparo', 'biblioteca'));
    }

    public function uniqueId(): string
    {
        $chave = $this->driveFileId !== '' ? $this->driveFileId : 'capa-'.$this->disciplinaId.'-'.$this->titulo;

        return 'importar-arquivo-'.$chave;
    }

    public function handle(ClientePastaDrive $cliente, SalvarCapaDaAula $salvarCapa, PublicarAulaSeNova $publicarSeNova): void
    {
        $disciplina = Disciplina::query()->with('turma.curso')->find($this->disciplinaId);
        if ($disciplina === null) {
            return;
        }

        $aula = $this->aulaDaDisciplina($disciplina);
        if ($aula === null) {
            return;
        }

        if ($this->driveFileId !== '') {
            $this->importarVideo($aula, $cliente);
            $aula = $aula->fresh() ?? $aula;
            $aula = $publicarSeNova->handle($aula);
        }

        if (filled($this->capaFileId) && $aula->estaProntaParaAssistir() && blank($aula->chave_capa)) {
            $this->importarCapa($aula, $cliente, $salvarCapa);
        }
    }

    private function aulaDaDisciplina(Disciplina $disciplina): ?Aula
    {
        $existente = Aula::query()
            ->where('disciplina_id', $disciplina->id)
            ->where('titulo', $this->titulo)
            ->first();

        if ($existente !== null) {
            if ($this->driveFileId !== '' && blank($existente->drive_file_id)) {
                $existente->forceFill(['drive_file_id' => $this->driveFileId])->save();
            }

            return $existente->fresh() ?? $existente;
        }

        if ($this->driveFileId === '') {
            return null;
        }

        return $disciplina->aulas()->create([
            'titulo' => $this->titulo,
            'status_preparo' => 'enviando',
            'status_drive' => 'ok',
            'publicada' => false,
            'drive_file_id' => $this->driveFileId,
            'chave_arquivo' => CaminhoDaBiblioteca::chaveVideo($disciplina, $this->titulo),
        ]);
    }

    private function importarVideo(Aula $aula, ClientePastaDrive $cliente): void
    {
        if ($aula->estaProntaParaAssistir()) {
            if ((int) $aula->tamanho_bytes > 0 && $this->tamanho > 0 && (int) $aula->tamanho_bytes !== $this->tamanho) {
                $relatorio = RelatorioImportacaoPasta::ler();
                $relatorio['ignorados'][] = [
                    'item' => $this->titulo.'.mp4',
                    'motivo' => 'O arquivo na pasta tem outro tamanho. Não substituí a aula da plataforma.',
                ];
                RelatorioImportacaoPasta::gravar($relatorio);
            }

            return;
        }

        $max = (int) config('biblioteca.upload_max_bytes');
        if ($this->tamanho > $max) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => ValidarExportMp4::mensagemGrandeDemais(),
            ]);

            return;
        }

        try {
            $inicio = $cliente->baixarFaixa($this->driveFileId, 32);
        } catch (Throwable $e) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => 'Não foi possível ler o arquivo na pasta compartilhada. Tente de novo.',
            ]);
            throw $e;
        }

        if (! ValidarExportMp4::pareceMp4($inicio)) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => ValidarExportMp4::mensagemRecusa(),
            ]);

            return;
        }

        $disciplina = $aula->disciplina()->with('turma.curso')->first();
        $chave = CaminhoDaBiblioteca::chaveVideo($disciplina, $this->titulo);
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));
        $stream = null;

        try {
            $stream = $cliente->abrirDownload($this->driveFileId);
            $disk->writeStream($chave, $stream);
        } catch (Throwable $e) {
            $aula->update([
                'status_preparo' => 'erro',
                'mensagem_erro' => 'Não foi possível copiar o vídeo para a biblioteca. Tente de novo.',
            ]);
            throw $e;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $aula->update([
            'chave_arquivo' => $chave,
            'chave_play' => $chave,
            'tamanho_bytes' => $this->tamanho > 0 ? $this->tamanho : null,
            'status_preparo' => 'pronta',
            'status_drive' => 'ok',
            'mensagem_erro' => null,
            'enviado_em' => $aula->enviado_em ?? now(),
            'drive_file_id' => $this->driveFileId,
        ]);
    }

    private function importarCapa(Aula $aula, ClientePastaDrive $cliente, SalvarCapaDaAula $salvarCapa): void
    {
        $maxCapa = (int) config('biblioteca.capa_max_bytes');
        if ($this->capaTamanho > $maxCapa) {
            return;
        }

        $stream = null;
        try {
            $stream = $cliente->abrirDownload((string) $this->capaFileId);
            $binario = stream_get_contents($stream);
            if (! is_string($binario) || $binario === '' || ! ValidarFotoCapa::pareceImagem($binario)) {
                return;
            }
            if (strlen($binario) > $maxCapa) {
                return;
            }
            $salvarCapa->handle($aula, $binario);
            $aula->refresh();
            if (filled($this->capaFileId)) {
                $aula->forceFill([
                    'drive_capa_file_id' => $this->capaFileId,
                    'status_drive' => 'ok',
                ])->save();
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}