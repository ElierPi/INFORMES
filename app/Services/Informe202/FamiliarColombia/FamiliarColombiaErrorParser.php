<?php

namespace App\Services\Informe202\FamiliarColombia;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class FamiliarColombiaErrorParser
{
    /**
     * @return array{
     *     metadata: array<string, mixed>,
     *     errors: array<int, array<string, mixed>>
     * }
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(
                'No se encontró el Excel de errores de Familiar de Colombia.'
            );
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $headerRow = $this->findHeaderRow($sheet);

        $metadata = $this->readMetadata(
            $sheet,
            $headerRow
        );

        $errors = [];
        $lastRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $lastRow; $row++) {
            $record = trim(
                (string) $sheet->getCell("A{$row}")
                    ->getCalculatedValue()
            );

            $column = trim(
                (string) $sheet->getCell("B{$row}")
                    ->getCalculatedValue()
            );

            $type = mb_strtoupper(
                trim(
                    (string) $sheet->getCell("C{$row}")
                        ->getCalculatedValue()
                ),
                'UTF-8'
            );

            $oldValue = $sheet->getCell("D{$row}")
                ->getCalculatedValue();

            $newValue = $sheet->getCell("E{$row}")
                ->getCalculatedValue();

            $description = trim(
                (string) $sheet->getCell("F{$row}")
                    ->getCalculatedValue()
            );

            if (
                $record === ''
                && $column === ''
                && $type === ''
                && $description === ''
            ) {
                continue;
            }

            if (! is_numeric($record) || ! is_numeric($column)) {
                continue;
            }

            $errors[] = [
                'source_row' => $row,
                'record' => (int) $record,
                'variable' => (int) $column,
                'type' => $type,
                'old_value' => $this->normalizeCell($oldValue),
                'new_value' => $this->normalizeCell($newValue),
                'description' => $description,
            ];
        }

        if ($errors === []) {
            throw new RuntimeException(
                'No se encontraron errores reconocibles en el archivo.'
            );
        }

        return [
            'metadata' => $metadata,
            'errors' => $errors,
        ];
    }

    private function findHeaderRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
    ): int {
        $limit = min(30, $sheet->getHighestDataRow());

        for ($row = 1; $row <= $limit; $row++) {
            $a = $this->normalizeHeader(
                (string) $sheet->getCell("A{$row}")->getValue()
            );

            $b = $this->normalizeHeader(
                (string) $sheet->getCell("B{$row}")->getValue()
            );

            $c = $this->normalizeHeader(
                (string) $sheet->getCell("C{$row}")->getValue()
            );

            if (
                $a === 'FILA'
                && $b === 'COLUMNA'
                && $c === 'TIPOERROR'
            ) {
                return $row;
            }
        }

        throw new RuntimeException(
            'No se encontró la fila de encabezados Fila, Columna y Tipo error.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $headerRow
    ): array {
        $metadata = [];

        for ($row = 1; $row < $headerRow; $row++) {
            $label = $this->normalizeHeader(
                (string) $sheet->getCell("A{$row}")->getValue()
            );

            $value = $sheet->getCell("C{$row}")
                ->getCalculatedValue();

            $key = match ($label) {
                'RADICADOPROCESO' => 'radicado',
                'ARCHIVOPROCESO' => 'file_name',
                'FECHAPROCESO' => 'process_date',
                'CANTIDADFILAS' => 'records_count',
                'ERRORESENCONTRADOS' => 'errors_count',
                'USUARIOREPORTA' => 'user',
                default => null,
            };

            if ($key !== null) {
                $metadata[$key] = $this->normalizeCell($value);
            }
        }

        return $metadata;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');

        $ascii = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            $ascii !== false ? $ascii : $value
        ) ?? '';
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}