<?php

namespace App\Services\Reports\Text;

use DateTimeInterface;
use RuntimeException;

class PipeDelimitedTxtGenerator
{
    public function generate(
        array $data,
        array $sheetOrder,
        string $outputPath
    ): string {
        $lines = [];

        foreach ($sheetOrder as $sheetKey) {
            foreach (($data[$sheetKey]['records'] ?? []) as $record) {
                $values = $record['values'] ?? [];

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $lines[] = implode('|', array_map(
                    fn (mixed $value): string => $this->normalizeValue($value),
                    $values
                ));
            }
        }

        if ($lines === []) {
            throw new RuntimeException(
                'No existen registros para generar el archivo TXT.'
            );
        }

        $directory = dirname($outputPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                "No fue posible crear el directorio {$directory}."
            );
        }

        $utf8 = implode("\r\n", $lines);
        $ansi = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        if (file_put_contents($outputPath, $ansi) === false) {
            throw new RuntimeException(
                'No fue posible guardar el archivo TXT.'
            );
        }

        return $outputPath;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            $value = number_format($value, 10, '.', '');
            $value = rtrim(rtrim($value, '0'), '.');
        }

        $value = trim((string) $value);
        $value = str_replace('|', '', $value);
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        $value = trim($value, "\"'");
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
