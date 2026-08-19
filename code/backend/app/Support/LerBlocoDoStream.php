<?php

namespace App\Support;

/**
 * fread em stream de rede (objeto) devolve o pacote disponível, não o tamanho pedido.
 */
class LerBlocoDoStream
{
    /**
     * @param  resource  $stream
     */
    public static function handle($stream, int $maximo): string
    {
        $maximo = max(1, $maximo);
        $bloco = '';

        while (strlen($bloco) < $maximo && ! feof($stream)) {
            $lido = fread($stream, $maximo - strlen($bloco));
            if ($lido === false || $lido === '') {
                break;
            }
            $bloco .= $lido;
        }

        return $bloco;
    }
}
