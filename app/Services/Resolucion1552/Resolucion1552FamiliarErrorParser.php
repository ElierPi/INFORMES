<?php

namespace App\Services\Resolucion1552;

final class Resolucion1552FamiliarErrorParser
{
    /** @return array<int, array{line:int,message:string,raw:string}> */
    public function parse(string $content): array
    {
        $errors = [];
        $lines = preg_split('/\R/u', $content) ?: [];

        foreach ($lines as $rawLine) {
            $rawLine = trim($rawLine);

            if ($rawLine === '') {
                continue;
            }

            if (! preg_match('/Error\s+linea\s+(\d+)\s*-->\s*(.+)$/iu', $rawLine, $matches)) {
                continue;
            }

            $errors[] = [
                'line' => (int) $matches[1],
                'message' => trim($matches[2]),
                'raw' => $rawLine,
            ];
        }

        return $errors;
    }
}
