<?php

namespace App\Services\Resolucion1604\FamiliarColombia;

use RuntimeException;

final class Resolucion1604FamiliarCorrectionService
{
    public function correct(string $txtPath, string $errorsPath, string $originalName, string $outputDirectory): array
    {
        if (! is_file($txtPath)) {
            throw new RuntimeException('No se encontró el TXT original.');
        }
        if (! is_file($errorsPath)) {
            throw new RuntimeException('No se encontró el TXT de errores.');
        }

        $raw = file_get_contents($txtPath);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('El TXT original está vacío o no pudo leerse.');
        }

        [$content, $encoding] = $this->decode($raw);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $lines = array_values(array_filter($lines, static fn ($line) => trim((string) $line) !== ''));
        if ($lines === []) {
            throw new RuntimeException('El TXT original no contiene registros procesables.');
        }

        foreach ($lines as $index => $line) {
            if (count(explode(';', $line)) !== 39) {
                throw new RuntimeException('La línea '.($index + 1).' no contiene los 39 campos esperados de la Resolución 1604.');
            }
        }

        $errorsText = file_get_contents($errorsPath);
        if ($errorsText === false) {
            throw new RuntimeException('No fue posible leer el archivo de errores.');
        }

        $errorsText = $this->toUtf8($errorsText);
        $remove = [];
        $audit = [];
        $manual = [];

        foreach (preg_split('/\r\n|\n|\r/', $errorsText) ?: [] as $errorLine) {
            $errorLine = trim($errorLine);
            if ($errorLine === '') {
                continue;
            }

            if (! preg_match('/Error\s+linea\s+(\d+)\s*-->\s*(.+)$/iu', $errorLine, $match)) {
                continue;
            }

            $lineNumber = (int) $match[1];
            $message = trim($match[2]);
            if ($lineNumber < 1 || $lineNumber > count($lines)) {
                $manual[] = [
                    'line' => $lineNumber,
                    'message' => $message,
                    'reason' => 'La línea indicada no existe en el TXT original.',
                ];
                continue;
            }

            if ($this->isInvalidCumError($message)) {
                $fields = explode(';', $lines[$lineNumber - 1]);
                $remove[$lineNumber] = true;
                $audit[] = [
                    'line' => $lineNumber,
                    'action' => 'EXCLUIDA',
                    'field' => 'CUM',
                    'cum' => trim($fields[14] ?? ''),
                    'document_type' => trim($fields[4] ?? ''),
                    'document' => trim($fields[5] ?? ''),
                    'patient' => trim($fields[6] ?? ''),
                    'technology' => trim($fields[13] ?? ''),
                    'formula' => trim($fields[11] ?? ''),
                    'message' => $message,
                ];
                continue;
            }

            $manual[] = [
                'line' => $lineNumber,
                'message' => $message,
                'reason' => 'Este patrón todavía no tiene una regla automática configurada.',
            ];
        }

        if ($audit === [] && $manual === []) {
            throw new RuntimeException('No se encontraron errores reconocibles en el archivo de errores.');
        }

        $corrected = [];
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            if (! isset($remove[$lineNumber])) {
                $corrected[] = $line;
            }
        }

        if ($corrected === []) {
            throw new RuntimeException('La corrección eliminaría todos los registros. Revisa el archivo de errores.');
        }

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException('No fue posible crear la carpeta de salida.');
        }

        $safeName = basename($originalName);
        if (strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) !== 'txt') {
            $safeName .= '.txt';
        }
        $outputPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$safeName;
        $outputContent = implode("\r\n", $corrected);
        file_put_contents($outputPath, $this->encode($outputContent, $encoding));

        return [
            'success' => $manual === [],
            'txt_path' => $outputPath,
            'txt_name' => $safeName,
            'original_records' => count($lines),
            'final_records' => count($corrected),
            'excluded_records' => count($remove),
            'automatic_corrections' => count($audit),
            'manual_count' => count($manual),
            'audit' => $audit,
            'manual' => $manual,
            'encoding' => $encoding,
        ];
    }

    private function isInvalidCumError(string $message): bool
    {
        $normalized = $this->normalize($message);
        return str_contains($normalized, 'campo cum')
            && str_contains($normalized, 'no existe en la base de datos');
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return trim(preg_replace('/\s+/', ' ', $converted !== false ? $converted : $value) ?? $value);
    }

    private function decode(string $raw): array
    {
        if (mb_check_encoding($raw, 'UTF-8')) {
            return [$raw, 'UTF-8'];
        }
        return [mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'), 'Windows-1252'];
    }

    private function toUtf8(string $raw): string
    {
        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    private function encode(string $content, string $encoding): string
    {
        return $encoding === 'UTF-8' ? $content : mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
    }
}
