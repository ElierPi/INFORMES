<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class Resolucion1552ProtegerExporter
{
    public function __construct(
        private readonly Resolucion1552ProtegerExcelReader $reader,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(string $path, string $period, string $receiverNit): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m', $period);

        if (! $date instanceof DateTimeImmutable || $date->format('Y-m') !== $period) {
            throw new RuntimeException('El período debe tener el formato AAAA-MM.');
        }

        $receiverNit = preg_replace('/\D+/', '', $receiverNit) ?? '';

        if (strlen($receiverNit) < 9 || strlen($receiverNit) > 12) {
            throw new RuntimeException('El NIT utilizado para el nombre debe contener entre 9 y 12 dígitos.');
        }

        $dataset = $this->reader->read($path);
        $records = $dataset['records'];
        $errors = $dataset['errors'];
        $warnings = $dataset['warnings'];
        $providerCode = trim((string) ($records[0][0] ?? ''));

        foreach ($records as $index => $record) {
            $sourceRow = $dataset['source_rows'][$index] ?? null;

            if (($record[0] ?? '') !== $providerCode) {
                $errors[] = $this->issue(
                    $sourceRow,
                    'Código del prestador',
                    'El archivo contiene más de un código de prestador.',
                    $record[0] ?? ''
                );
            }

            foreach ([7, 8, 9] as $dateIndex) {
                $recordDate = DateTimeImmutable::createFromFormat('!d/m/Y', (string) ($record[$dateIndex] ?? ''));

                if ($recordDate instanceof DateTimeImmutable && $recordDate->format('Y-m') !== $period) {
                    $errors[] = $this->issue(
                        $sourceRow,
                        ['Fecha de solicitud', 'Fecha de asignación', 'Fecha requerida'][$dateIndex - 7],
                        sprintf('La fecha debe pertenecer al período %s.', $period),
                        $record[$dateIndex]
                    );
                }
            }
        }

        $invalidRows = array_filter(array_column($errors, 'source_row'), static fn ($row): bool => $row !== null);
        $statistics = [
            'records_count' => count($records),
            'valid_records_count' => $errors === [] ? count($records) : max(0, count($records) - count(array_unique($invalidRows))),
            'invalid_records_count' => count(array_unique($invalidRows)),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
            'duplicate_count' => (int) ($dataset['duplicate_count'] ?? 0),
            'skipped_rows_count' => (int) ($dataset['skipped_rows'] ?? 0),
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'validation' => compact('statistics', 'errors', 'warnings'),
                'provider_code' => $providerCode,
                'encoding' => 'UTF-8',
                'delimiter' => ',',
            ];
        }

        $lines = array_map(
            static fn (array $record): string => implode(',', $record),
            $records
        );
        $content = implode("\r\n", $lines);
        $baseName = $receiverNit . '_' . $date->format('mY');
        $directory = storage_path('app/private/reports/resolucion1552-proteger/' . Str::uuid());

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $txtPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.txt';
        $zipPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.zip';

        if (file_put_contents($txtPath, $content) === false) {
            throw new RuntimeException('No fue posible guardar el TXT de Proteger.');
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el ZIP de Proteger.');
        }

        $zip->addFile($txtPath, basename($txtPath));
        $zip->close();

        return [
            'success' => true,
            'validation' => compact('statistics', 'errors', 'warnings'),
            'records_count' => count($records),
            'provider_code' => $providerCode,
            'receiver_nit' => $receiverNit,
            'encoding' => 'UTF-8',
            'delimiter' => ',',
            'txt_path' => $txtPath,
            'txt_name' => basename($txtPath),
            'zip_path' => $zipPath,
            'zip_name' => basename($zipPath),
            'sheet_name' => $dataset['sheet_name'],
            'header_row' => $dataset['header_row'],
        ];
    }

    /** @return array<string, mixed> */
    private function issue(?int $row, string $field, string $message, mixed $value): array
    {
        return [
            'source_row' => $row,
            'field_name' => $field,
            'value' => $value,
            'message' => $message,
            'severity' => 'error',
        ];
    }
}
