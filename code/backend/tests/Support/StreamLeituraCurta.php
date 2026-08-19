<?php

namespace Tests\Support;

/**
 * Simula stream de rede: cada fread devolve no máximo N bytes.
 */
class StreamLeituraCurta
{
    public $context;

    private string $data = '';

    private int $pos = 0;

    private int $max = 1024;

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $opts = stream_context_get_options($this->context)['leituracurta'] ?? [];
        $this->data = (string) ($opts['data'] ?? '');
        $this->max = max(1, (int) ($opts['max'] ?? 1024));
        $this->pos = 0;

        return true;
    }

    public function stream_read($count): string
    {
        if ($this->pos >= strlen($this->data)) {
            return '';
        }

        $n = min((int) $count, $this->max, strlen($this->data) - $this->pos);
        $out = substr($this->data, $this->pos, $n);
        $this->pos += $n;

        return $out;
    }

    public function stream_eof(): bool
    {
        return $this->pos >= strlen($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function stream_stat(): array
    {
        return [
            'size' => strlen($this->data),
        ];
    }
}
