<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1552FamiliarExporter
{
    public function __construct(
        private readonly Resolucion1552FamiliarExcelReader $reader,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(string $path, string $reportDate): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $reportDate);

        if (! $date instanceof DateTimeImmutable || $date->format('d') !== '01') {
            throw new RuntimeException('La fecha de reporte debe ser el primer día del mes informado.');
        }

        $dataset = $this->reader->read($path);
        $records = $dataset['records'];
        $errors = $dataset['errors'];
        $warnings = $dataset['warnings'];
        $providerCode = trim((string) ($records[0][1] ?? ''));

        foreach ($records as $record) {
            if (($record[1] ?? '') !== $providerCode) {
                $errors[] = [
                    'source_row' => null,
                    'field_name' => 'Código del prestador',
                    'value' => $record[1] ?? '',
                    'message' => 'El archivo contiene más de un código de prestador.',
                    'severity' => 'error',
                ];
            }
        }

        $statistics = [
            'records_count' => count($records),
            'valid_records_count' => $errors === [] ? count($records) : 0,
            'invalid_records_count' => $errors === [] ? 0 : count(array_unique(array_column($errors, 'source_row'))),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'validation' => compact('statistics', 'errors', 'warnings'),
                'provider_code' => $providerCode,
                'encoding' => 'Windows-1252',
            ];
        }

        $lines = array_map(
            static fn (array $record): string => implode(';', $record),
            $records
        );
        $content = implode("\r\n", $lines);
        $encoded = mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
        $baseName = sprintf(
            'RESOLUCION_1552_FAMILIAR_%s_%s',
            $providerCode,
            $date->format('dmY')
        );
        $directory = storage_path('app/private/reports/resolucion1552-familiar/' . Str::uuid());

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $txtPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.txt';

        if (file_put_contents($txtPath, $encoded) === false) {
            throw new RuntimeException('No fue posible guardar el TXT de Familiar de Colombia.');
        }

        return [
            'success' => true,
            'validation' => compact('statistics', 'errors', 'warnings'),
            'records_count' => count($records),
            'provider_code' => $providerCode,
            'encoding' => 'Windows-1252',
            'delimiter' => ';',
            'txt_path' => $txtPath,
            'txt_name' => basename($txtPath),
            'sheet_name' => $dataset['sheet_name'],
            'header_row' => $dataset['header_row'],
        ];
    }
}
