<?php

namespace App\Services\Reports\Engine;

use RuntimeException;

final class TextEncoder
{
    public function encode(
        string $content,
        string $targetEncoding,
        string $sourceEncoding = 'UTF-8',
        bool $bom = false,
    ): string {
        $normalizedTarget = strtoupper(str_replace('_', '-', trim($targetEncoding)));
        $normalizedSource = strtoupper(str_replace('_', '-', trim($sourceEncoding)));

        if ($normalizedTarget === '' || $normalizedTarget === $normalizedSource) {
            $encoded = $content;
        } else {
            $encoded = @mb_convert_encoding(
                $content,
                $targetEncoding,
                $sourceEncoding
            );

            if (! is_string($encoded)) {
                throw new RuntimeException(
                    "No fue posible convertir el contenido de {$sourceEncoding} a {$targetEncoding}."
                );
            }
        }

        if ($bom && in_array($normalizedTarget, ['UTF-8', 'UTF8'], true)) {
            return "\xEF\xBB\xBF" . $encoded;
        }

        return $encoded;
    }
}
