<?php

namespace App\Actions;

use App\Jobs\ImportarArquivoDaPastaJob;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Services\Integrations\ClientePastaDrive;
use App\Support\CaminhoDaBiblioteca;
use App\Support\RelatorioImportacaoPasta;
use App\Support\ValidarExportMp4;
use Throwable;

class VarrerPastaCompartilhada
{
    public function handle(ClientePastaDrive $cliente): array
    {
        $relatorio = RelatorioImportacaoPasta::ler();
        if (($relatorio['status'] ?? '') !== 'importando') {
            $relatorio = RelatorioImportacaoPasta::iniciar();
        }

        try {
            if (! $cliente->podeListarPasta()) {
                $relatorio['erros'][] = 'A pasta compartilhada ainda não está configurada para importar.';
                RelatorioImportacaoPasta::concluir($relatorio, 'erro');

                return $relatorio;
            }

            $raiz = (string) config('biblioteca.drive.folder_id');
            if (! config('biblioteca.drive.fake') && $raiz === '') {
                $relatorio['erros'][] = 'A pasta compartilhada ainda não está configurada para importar.';
                RelatorioImportacaoPasta::concluir($relatorio, 'erro');

                return $relatorio;
            }

            $this->varrerCursos($cliente, $raiz, $relatorio);
            RelatorioImportacaoPasta::concluir($relatorio);

            return $relatorio;
        } catch (Throwable $e) {
            $relatorio['erros'][] = 'Não foi possível ler a pasta compartilhada. Tente de novo.';
            RelatorioImportacaoPasta::concluir($relatorio, 'erro');
            report($e);

            return $relatorio;
        }
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function varrerCursos(ClientePastaDrive $cliente, string $raiz, array &$relatorio): void
    {
        $filhos = $cliente->listarFilhos($raiz);
        $nomes = [];

        foreach ($filhos as $item) {
            $caminho = $this->caminho($item['name']);
            if ($this->ehPasta($item)) {
                if ($this->nomeDuplicado($nomes, $item['name'], $caminho, 'Já existe outra pasta de curso com este nome.', $relatorio)) {
                    continue;
                }
                $curso = $this->ligarOuCriarCurso($item['id'], $item['name'], $relatorio);
                if ($curso === null) {
                    continue;
                }
                $this->varrerTurmas($cliente, $curso, $item['id'], $caminho, $relatorio);

                continue;
            }

            $this->ignorar($relatorio, $caminho, 'Arquivo fora da pasta de disciplina.');
        }
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function varrerTurmas(ClientePastaDrive $cliente, Curso $curso, string $pastaId, string $prefixo, array &$relatorio): void
    {
        $filhos = $cliente->listarFilhos($pastaId);
        $nomes = [];

        foreach ($filhos as $item) {
            $caminho = $this->caminho($item['name'], $prefixo);
            if ($this->ehPasta($item)) {
                if ($this->nomeDuplicado($nomes, $item['name'], $caminho, 'Já existe outra pasta de turma com este nome.', $relatorio)) {
                    continue;
                }
                $turma = $this->ligarOuCriarTurma($curso, $item['id'], $item['name'], $relatorio);
                if ($turma === null) {
                    continue;
                }
                $this->varrerDisciplinas($cliente, $turma, $item['id'], $caminho, $relatorio);

                continue;
            }

            $this->ignorar($relatorio, $caminho, 'Arquivo fora da pasta de disciplina.');
        }
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function varrerDisciplinas(ClientePastaDrive $cliente, Turma $turma, string $pastaId, string $prefixo, array &$relatorio): void
    {
        $filhos = $cliente->listarFilhos($pastaId);
        $nomes = [];

        foreach ($filhos as $item) {
            $caminho = $this->caminho($item['name'], $prefixo);
            if ($this->ehPasta($item)) {
                if ($this->nomeDuplicado($nomes, $item['name'], $caminho, 'Já existe outra pasta de disciplina com este nome.', $relatorio)) {
                    continue;
                }
                $disciplina = $this->ligarOuCriarDisciplina($turma, $item['id'], $item['name'], $relatorio);
                if ($disciplina === null) {
                    continue;
                }
                $this->varrerArquivos($cliente, $disciplina, $item['id'], $caminho, $relatorio);

                continue;
            }

            $this->ignorar($relatorio, $caminho, 'Arquivo fora da pasta de disciplina.');
        }
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function varrerArquivos(ClientePastaDrive $cliente, Disciplina $disciplina, string $pastaId, string $prefixo, array &$relatorio): void
    {
        $filhos = $cliente->listarFilhos($pastaId);
        $videos = [];
        $capas = [];

        foreach ($filhos as $item) {
            $caminho = $this->caminho($item['name'], $prefixo);
            if ($this->ehPasta($item)) {
                $this->ignorar($relatorio, $caminho, 'Pasta extra dentro da disciplina.');

                continue;
            }

            if ($this->ehAtalhoOuDoc($item)) {
                $this->ignorar($relatorio, $caminho, 'Tipo de arquivo ignorado.');

                continue;
            }

            $interpretado = CaminhoDaBiblioteca::interpretarArquivoDaPasta($item['name']);
            if ($interpretado === null) {
                $this->ignorar($relatorio, $caminho, 'Tipo de arquivo ignorado.');

                continue;
            }

            if ($interpretado['tipo'] === 'capa') {
                $capas[$interpretado['titulo']] = $item;

                continue;
            }

            $videos[] = ['item' => $item, 'titulo' => $interpretado['titulo'], 'caminho' => $caminho];
        }

        foreach ($videos as $video) {
            $this->enfileirarVideo($disciplina, $video['item'], $video['titulo'], $video['caminho'], $capas[$video['titulo']] ?? null, $relatorio);
        }

        foreach ($capas as $titulo => $capa) {
            $jaTemVideo = false;
            foreach ($videos as $video) {
                if ($video['titulo'] === $titulo) {
                    $jaTemVideo = true;

                    break;
                }
            }
            if ($jaTemVideo) {
                continue;
            }
            ImportarArquivoDaPastaJob::dispatch(
                $disciplina->id,
                '',
                $titulo,
                0,
                (string) $capa['id'],
                (int) ($capa['size'] ?? 0),
            );
            $relatorio['enfileirados'] = (int) $relatorio['enfileirados'] + 1;
            RelatorioImportacaoPasta::gravar($relatorio);
        }
    }

    /**
     * @param  array{id: string, name: string, mimeType: string, size: int}  $item
     * @param  array{id: string, name: string, mimeType: string, size: int}|null  $capa
     * @param  array<string, mixed>  $relatorio
     */
    private function enfileirarVideo(Disciplina $disciplina, array $item, string $titulo, string $caminho, ?array $capa, array &$relatorio): void
    {
        $tamanho = (int) ($item['size'] ?? 0);
        $max = (int) config('biblioteca.upload_max_bytes');
        if ($tamanho > $max) {
            $this->ignorar($relatorio, $caminho, ValidarExportMp4::mensagemGrandeDemais());

            return;
        }

        ImportarArquivoDaPastaJob::dispatch(
            $disciplina->id,
            (string) $item['id'],
            $titulo,
            $tamanho,
            $capa !== null ? (string) $capa['id'] : null,
            (int) ($capa['size'] ?? 0),
        );
        $relatorio['enfileirados'] = (int) $relatorio['enfileirados'] + 1;
        RelatorioImportacaoPasta::gravar($relatorio);
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function ligarOuCriarCurso(string $id, string $nome, array &$relatorio): ?Curso
    {
        $nome = $this->nomeLimpo($nome);
        $porId = Curso::query()->where('drive_folder_id', $id)->first();
        if ($porId !== null) {
            return $porId;
        }

        $porNome = Curso::query()->where('nome', $nome)->first();
        if ($porNome !== null) {
            if (filled($porNome->drive_folder_id) && $porNome->drive_folder_id !== $id) {
                $this->ignorar($relatorio, $nome, 'Já existe um curso com este nome ligado a outra pasta.');

                return null;
            }
            $porNome->forceFill(['drive_folder_id' => $id])->save();
            $relatorio['ligados'][] = ['tipo' => 'curso', 'nome' => $nome];
            RelatorioImportacaoPasta::gravar($relatorio);

            return $porNome;
        }

        $curso = Curso::query()->create(['nome' => $nome, 'drive_folder_id' => $id]);
        $relatorio['criados'][] = ['tipo' => 'curso', 'nome' => $nome];
        RelatorioImportacaoPasta::gravar($relatorio);

        return $curso;
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function ligarOuCriarTurma(Curso $curso, string $id, string $nome, array &$relatorio): ?Turma
    {
        $nome = $this->nomeLimpo($nome);
        $porId = Turma::query()->where('drive_folder_id', $id)->first();
        if ($porId !== null) {
            return $porId;
        }

        $porNome = Turma::query()->where('curso_id', $curso->id)->where('nome', $nome)->first();
        if ($porNome !== null) {
            if (filled($porNome->drive_folder_id) && $porNome->drive_folder_id !== $id) {
                $this->ignorar($relatorio, $nome, 'Já existe uma turma com este nome ligada a outra pasta.');

                return null;
            }
            $porNome->forceFill(['drive_folder_id' => $id])->save();
            $relatorio['ligados'][] = ['tipo' => 'turma', 'nome' => $nome];
            RelatorioImportacaoPasta::gravar($relatorio);

            return $porNome;
        }

        $turma = Turma::query()->create([
            'curso_id' => $curso->id,
            'nome' => $nome,
            'drive_folder_id' => $id,
        ]);
        $relatorio['criados'][] = ['tipo' => 'turma', 'nome' => $nome];
        RelatorioImportacaoPasta::gravar($relatorio);

        return $turma;
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function ligarOuCriarDisciplina(Turma $turma, string $id, string $nome, array &$relatorio): ?Disciplina
    {
        $nome = $this->nomeLimpo($nome);
        $porId = Disciplina::query()->where('drive_folder_id', $id)->first();
        if ($porId !== null) {
            return $porId;
        }

        $porNome = Disciplina::query()->where('turma_id', $turma->id)->where('nome', $nome)->first();
        if ($porNome !== null) {
            if (filled($porNome->drive_folder_id) && $porNome->drive_folder_id !== $id) {
                $this->ignorar($relatorio, $nome, 'Já existe uma disciplina com este nome ligada a outra pasta.');

                return null;
            }
            $porNome->forceFill(['drive_folder_id' => $id])->save();
            $relatorio['ligados'][] = ['tipo' => 'disciplina', 'nome' => $nome];
            RelatorioImportacaoPasta::gravar($relatorio);

            return $porNome;
        }

        $disciplina = Disciplina::query()->create([
            'turma_id' => $turma->id,
            'nome' => $nome,
            'drive_folder_id' => $id,
        ]);
        $relatorio['criados'][] = ['tipo' => 'disciplina', 'nome' => $nome];
        RelatorioImportacaoPasta::gravar($relatorio);

        return $disciplina;
    }

    /**
     * @param  array<string, true>  $nomes
     * @param  array<string, mixed>  $relatorio
     */
    private function nomeDuplicado(array &$nomes, string $nome, string $caminho, string $motivo, array &$relatorio): bool
    {
        $chave = mb_strtolower($this->nomeLimpo($nome));
        if (isset($nomes[$chave])) {
            $this->ignorar($relatorio, $caminho, $motivo);

            return true;
        }
        $nomes[$chave] = true;

        return false;
    }

    /**
     * @param  array{mimeType?: string}  $item
     */
    private function ehPasta(array $item): bool
    {
        return ($item['mimeType'] ?? '') === 'application/vnd.google-apps.folder';
    }

    /**
     * @param  array{mimeType?: string}  $item
     */
    private function ehAtalhoOuDoc(array $item): bool
    {
        $mime = (string) ($item['mimeType'] ?? '');

        return str_starts_with($mime, 'application/vnd.google-apps.')
            && $mime !== 'application/vnd.google-apps.folder';
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function ignorar(array &$relatorio, string $item, string $motivo): void
    {
        $relatorio['ignorados'][] = ['item' => $item, 'motivo' => $motivo];
        RelatorioImportacaoPasta::gravar($relatorio);
    }

    private function caminho(string $nome, string $prefixo = ''): string
    {
        $nome = $this->nomeLimpo($nome);

        return $prefixo === '' ? $nome : $prefixo.'/'.$nome;
    }

    private function nomeLimpo(string $nome): string
    {
        $nome = trim($nome);

        return $nome !== '' ? $nome : 'Sem nome';
    }
}