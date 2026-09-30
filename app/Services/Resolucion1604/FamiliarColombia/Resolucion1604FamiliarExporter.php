<?php

namespace App\Services\Resolucion1604\FamiliarColombia;

use DateTimeImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1604FamiliarExporter
{
    public function __construct(
        private readonly Resolucion1604FamiliarExcelReader $reader,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(string $path, string $period): array
    {
        $periodDate = DateTimeImmutable::createFromFormat('!Y-m', $period);
        if (! $periodDate instanceof DateTimeImmutable) {
            throw new RuntimeException('Selecciona un período válido.');
        }

        $dataset = $this->reader->read($path, $period);
        $errors = $dataset['errors'];
        $warnings = $dataset['warnings'];
        $rows = $dataset['records'];

        $invalidRows = array_unique(array_filter(array_map(
            static fn (array $item): mixed => $item['source_row'] ?? null,
            $errors
        )));

        $statistics = [
            'records_count' => count($rows),
            'valid_records_count' => max(0, count($rows) - count($invalidRows)),
            'invalid_records_count' => count($invalidRows),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
            'auto_corrections_count' => count(array_filter(
                $warnings,
                static fn (array $item): bool => str_contains((string) ($item['message'] ?? ''), 'automáticamente')
                    || str_contains((string) ($item['message'] ?? ''), 'normalizó')
            )),
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'validation' => compact('statistics', 'errors', 'warnings'),
                'sheet_name' => $dataset['sheet_name'],
                'header_row' => $dataset['header_row'],
            ];
        }

        $records = array_map(static fn (array $row): array => $row['values'], $rows);
        $lines = array_map(static fn (array $record): string => implode(';', $record), $records);
        $content = implode("\r\n", $lines);
        $encoded = $this->toAnsi($content);

        $baseName = sprintf('RESOLUCION_1604_FAMILIAR_COLOMBIA_%s', $periodDate->format('mY'));
        $directory = storage_path('app/private/reports/resolucion1604-familiar/'.Str::uuid());

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $txtPath = $directory.DIRECTORY_SEPARATOR.$baseName.'.txt';
        if (file_put_contents($txtPath, $encoded) === false) {
            throw new RuntimeException('No fue posible guardar el TXT de la Resolución 1604.');
        }

        return [
            'success' => true,
            'validation' => compact('statistics', 'errors', 'warnings'),
            'sheet_name' => $dataset['sheet_name'],
            'header_row' => $dataset['header_row'],
            'txt_path' => $txtPath,
            'txt_name' => basename($txtPath),
            'delimiter' => ';',
            'encoding' => 'Windows-1252',
            'field_count' => Resolucion1604FamiliarExcelReader::FIELD_COUNT,
        ];
    }

    private function toAnsi(string $content): string
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
        }
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $content);
        if ($converted === false) {
            throw new RuntimeException('No fue posible convertir el TXT a codificación ANSI.');
        }
        return $converted;
    }
}
