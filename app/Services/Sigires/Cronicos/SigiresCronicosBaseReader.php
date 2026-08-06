<?php

namespace App\Services\Sigires\Cronicos;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class SigiresCronicosBaseReader
{
    /**
     * @param  array<int, string>  $documentNumbers
     * @return array{headers: array<string, int>, records: array<string, array<string, mixed>>, duplicates: array<int, string>}
     */
    public function findByDocuments(string $path, array $documentNumbers): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró la base regional de usuarios.');
        }

        $targets = [];

        foreach ($documentNumbers as $documentNumber) {
            $normalized = $this->normalizeDocumentNumber($documentNumber);

            if ($normalized !== '') {
                $targets[$normalized] = true;
            }
        }

        if ($targets === []) {
            throw new RuntimeException('No se encontraron documentos válidos en las historias clínicas.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible abrir la base regional: '.$exception->getMessage(),
                previous: $exception
            );
        }

        try {
            $sheet = $this->findDataSheet($spreadsheet->getWorksheetIterator());

            if (! $sheet instanceof Worksheet) {
                throw new RuntimeException(
                    'No se encontró una hoja con las columnas de identificación esperadas.'
                );
            }

            $headerRow = $this->detectHeaderRow($sheet);
            $headers = $this->readHeaderMap($sheet, $headerRow);
            $canonicalColumns = $this->resolveCanonicalColumns($headers);

            $records = [];
            $duplicates = [];
            $highestRow = $sheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $documentNumber = $this->normalizeDocumentNumber(
                    $this->readCell($sheet, $canonicalColumns['document_number'], $row)
                );

                if ($documentNumber === '' || ! isset($targets[$documentNumber])) {
                    continue;
                }

                if (isset($records[$documentNumber])) {
                    $duplicates[] = $documentNumber;

                    continue;
                }

                $record = ['excel_row' => $row];

                foreach ($canonicalColumns as $key => $column) {
                    $record[$key] = $this->readCell($sheet, $column, $row);
                }

                $record['document_number'] = $documentNumber;
                $records[$documentNumber] = $record;
            }

            return [
                'headers' => $headers,
                'records' => $records,
                'duplicates' => array_values(array_unique($duplicates)),
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    /**
     * @param  iterable<int, Worksheet>  $worksheets
     */
    private function findDataSheet(iterable $worksheets): ?Worksheet
    {
        foreach ($worksheets as $worksheet) {
            try {
                $this->detectHeaderRow($worksheet);

                return $worksheet;
            } catch (RuntimeException) {
                continue;
            }
        }

        return null;
    }

    private function detectHeaderRow(Worksheet $sheet): int
    {
        $highestColumnIndex = min(
            Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn()
            ),
            52
        );
        $highestColumn = Coordinate::stringFromColumnIndex(
            $highestColumnIndex
        );

        for ($row = 1; $row <= 15; $row++) {
            $values = $sheet->rangeToArray(
                "A{$row}:{$highestColumn}{$row}",
                null,
                true,
                true,
                false
            )[0] ?? [];

            $normalized = array_map(
                fn (mixed $value): string => $this->normalizeHeader((string) $value),
                $values
            );

            $hasDocumentType = $this->containsAlias(
                $normalized,
                config('sigires_cronicos.base_aliases.document_type', [])
            );
            $hasDocumentNumber = $this->containsAlias(
                $normalized,
                config('sigires_cronicos.base_aliases.document_number', [])
            );

            if ($hasDocumentType && $hasDocumentNumber) {
                return $row;
            }
        }

        throw new RuntimeException(
            "La hoja '{$sheet->getTitle()}' no contiene un encabezado reconocible."
        );
    }

    /**
     * @return array<string, int>
     */
    private function readHeaderMap(Worksheet $sheet, int $headerRow): array
    {
        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn($headerRow)
        );

        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $header = $this->normalizeHeader(
                (string) $sheet->getCell([$column, $headerRow])->getFormattedValue()
            );

            if ($header !== '') {
                $headers[$header] = $column;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, int>  $headers
     * @return array<string, int>
     */
    private function resolveCanonicalColumns(array $headers): array
    {
        $resolved = [];

        foreach (config('sigires_cronicos.base_aliases', []) as $key => $aliases) {
            foreach ((array) $aliases as $alias) {
                $normalized = $this->normalizeHeader((string) $alias);

                if (isset($headers[$normalized])) {
                    $resolved[(string) $key] = $headers[$normalized];

                    break;
                }
            }
        }

        foreach ([
            'provider_name',
            'provider_code',
            'document_type',
            'document_number',
            'first_name',
            'first_surname',
            'birth_date',
            'sex',
            'hta',
            'dm',
            'erc',
        ] as $required) {
            if (! isset($resolved[$required])) {
                throw new RuntimeException(
                    "No se encontró la columna obligatoria '{$required}' en la base regional."
                );
            }
        }

        return $resolved;
    }

    private function readCell(Worksheet $sheet, int $column, int $row): mixed
    {
        $cell = $sheet->getCell([$column, $row]);
        $value = $cell->getValue();

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value) && Date::isDateTime($cell)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return trim((string) $cell->getFormattedValue());
            }
        }

        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '.', '');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $formatted = trim((string) $cell->getFormattedValue());

        return $formatted === '' ? null : $formatted;
    }

    /**
     * @param  array<int, string>  $normalizedHeaders
     * @param  array<int, string>  $aliases
     */
    private function containsAlias(array $normalizedHeaders, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if (in_array($this->normalizeHeader($alias), $normalizedHeaders, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return preg_replace('/[^A-Z0-9]+/', ' ', $value) !== null
            ? trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $value))
            : $value;
    }

    private function normalizeDocumentNumber(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/\.0+$/', '', $value) ?? $value;

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }
}
