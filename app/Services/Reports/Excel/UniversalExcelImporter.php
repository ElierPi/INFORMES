<?php

namespace App\Services\Reports\Excel;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class UniversalExcelImporter
{
    /**
     * Analiza un archivo Excel y detecta automáticamente:
     *
     * - Hoja con datos.
     * - Fila de encabezados.
     * - Equivalencias de columnas.
     * - Registros encontrados.
     *
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(
                'No se encontró el archivo Excel seleccionado.'
            );
        }

        $spreadsheet = IOFactory::load($path);

        $candidates = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $candidate = $this->inspectWorksheet($worksheet);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            throw new RuntimeException(
                'No fue posible encontrar una estructura de datos reconocible en el archivo.'
            );
        }

        usort(
            $candidates,
            static fn (array $first, array $second): int =>
                $second['score'] <=> $first['score']
        );

        $selected = $candidates[0];

        return [
            'success' => true,

            'sheet_name' => $selected['sheet_name'],

            'header_row' => $selected['header_row'],

            'data_start_row' => $selected['header_row'] + 1,

            'last_row' => $selected['last_row'],

            'last_column' => $selected['last_column'],

            'score' => $selected['score'],

            'headers' => $selected['headers'],

            'mapping' => $selected['mapping'],

            'unmapped_columns' => $selected['unmapped_columns'],

            'missing_fields' => $this->missingFields(
                $selected['mapping']
            ),

            'records' => $this->readRecords(
                worksheet: $spreadsheet->getSheetByName(
                    $selected['sheet_name']
                ),
                headerRow: $selected['header_row'],
                mapping: $selected['mapping'],
                headers: $selected['headers']
            ),
        ];
    }

    /**
     * Analiza una hoja buscando la fila que más se parece
     * a un encabezado válido.
     *
     * @return array<string, mixed>|null
     */
    private function inspectWorksheet(
        Worksheet $worksheet
    ): ?array {
        $highestRow = $worksheet->getHighestDataRow();
        $highestColumn = $worksheet->getHighestDataColumn();

        if ($highestRow < 1) {
            return null;
        }

        $highestColumnIndex = Coordinate::columnIndexFromString(
            $highestColumn
        );

        $searchLimit = min(
            $highestRow,
            (int) config(
                'report_importer.header_search_limit',
                30
            )
        );

        $bestCandidate = null;

        for ($row = 1; $row <= $searchLimit; $row++) {
            $headers = $this->readHeaderRow(
                worksheet: $worksheet,
                row: $row,
                highestColumnIndex: $highestColumnIndex
            );

            $mapping = $this->detectMapping($headers);

            $matchedFields = array_filter(
                $mapping,
                static fn ($column): bool => $column !== null
            );

            $score = count($matchedFields);

            if (
                $bestCandidate === null
                || $score > $bestCandidate['score']
            ) {
                $bestCandidate = [
                    'sheet_name' => $worksheet->getTitle(),
                    'header_row' => $row,
                    'last_row' => $highestRow,
                    'last_column' => $highestColumn,
                    'headers' => $headers,
                    'mapping' => $mapping,
                    'score' => $score,
                ];
            }
        }

        $minimumMatches = (int) config(
            'report_importer.minimum_header_matches',
            3
        );

        if (
            $bestCandidate === null
            || $bestCandidate['score'] < $minimumMatches
        ) {
            return null;
        }

        $bestCandidate['unmapped_columns'] =
            $this->findUnmappedColumns(
                headers: $bestCandidate['headers'],
                mapping: $bestCandidate['mapping']
            );

        return $bestCandidate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHeaderRow(
        Worksheet $worksheet,
        int $row,
        int $highestColumnIndex
    ): array {
        $headers = [];

        for (
            $column = 1;
            $column <= $highestColumnIndex;
            $column++
        ) {
            $coordinate = Coordinate::stringFromColumnIndex(
                $column
            ) . $row;

            $original = trim(
                (string) $worksheet
                    ->getCell($coordinate)
                    ->getFormattedValue()
            );

            $headers[] = [
                'column_index' => $column,
                'column_letter' =>
                    Coordinate::stringFromColumnIndex($column),
                'original' => $original,
                'normalized' => $this->normalize($original),
            ];
        }

        return $headers;
    }

    /**
     * Devuelve:
     *
     * [
     *   'provider_code' => 3,
     *   'document_type' => 6,
     *   ...
     * ]
     *
     * El número corresponde a la posición física de la columna.
     *
     * @param array<int, array<string, mixed>> $headers
     * @return array<string, int|null>
     */
    private function detectMapping(array $headers): array
    {
        $aliases = config(
            'report_importer.aliases',
            []
        );

        $mapping = [];

        foreach ($aliases as $field => $fieldAliases) {
            $mapping[$field] = null;

            foreach ($headers as $header) {
                if ($header['normalized'] === '') {
                    continue;
                }

                if (
                    $this->headerMatches(
                        normalizedHeader: $header['normalized'],
                        aliases: $fieldAliases
                    )
                ) {
                    $mapping[$field] =
                        $header['column_index'];

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param array<int, string> $aliases
     */
    private function headerMatches(
        string $normalizedHeader,
        array $aliases
    ): bool {
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalize(
                (string) $alias
            );

            if ($normalizedHeader === $normalizedAlias) {
                return true;
            }

            /*
             * Permite encontrar encabezados que agreguen aclaraciones.
             *
             * Ejemplo:
             * "TIPO DOC (CC, TI, RC)"
             * coincide con:
             * "tipo doc"
             */
            if (
                mb_strlen($normalizedAlias) >= 7
                && str_contains(
                    $normalizedHeader,
                    $normalizedAlias
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $headers
     * @param array<string, int|null> $mapping
     * @return array<int, array<string, mixed>>
     */
    private function findUnmappedColumns(
        array $headers,
        array $mapping
    ): array {
        $mappedColumnIndexes = array_values(
            array_filter(
                $mapping,
                static fn ($value): bool => is_int($value)
            )
        );

        return array_values(
            array_filter(
                $headers,
                static fn (array $header): bool =>
                    $header['original'] !== ''
                    && ! in_array(
                        $header['column_index'],
                        $mappedColumnIndexes,
                        true
                    )
            )
        );
    }

    /**
     * Lee los registros y los convierte en una estructura común.
     *
     * @param array<string, int|null> $mapping
     * @param array<int, array<string, mixed>> $headers
     * @return array<int, array<string, mixed>>
     */
    private function readRecords(
        ?Worksheet $worksheet,
        int $headerRow,
        array $mapping,
        array $headers
    ): array {
        if ($worksheet === null) {
            throw new RuntimeException(
                'No fue posible abrir la hoja seleccionada.'
            );
        }

        $records = [];
        $lastRow = $worksheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $lastRow; $row++) {
            if ($this->isEmptyRow($worksheet, $row, $headers)) {
                continue;
            }

            $data = [];

            foreach ($mapping as $field => $columnIndex) {
                if ($columnIndex === null) {
                    $data[$field] = null;
                    continue;
                }

                $cell = $worksheet->getCellByColumnAndRow(
                    $columnIndex,
                    $row
                );

                $data[$field] = $this->cellValue($cell);
            }

            $records[] = [
                'source_row' => $row,
                'data' => $data,
            ];
        }

        return $records;
    }

    /**
     * @param array<int, array<string, mixed>> $headers
     */
    private function isEmptyRow(
        Worksheet $worksheet,
        int $row,
        array $headers
    ): bool {
        foreach ($headers as $header) {
            $value = trim(
                (string) $worksheet
                    ->getCellByColumnAndRow(
                        $header['column_index'],
                        $row
                    )
                    ->getFormattedValue()
            );

            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellValue(
        \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
    ): mixed {
        $value = $cell->getValue();

        if (
            \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime(
                $cell
            )
            && is_numeric($value)
        ) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date
                ::excelToDateTimeObject($value)
                ->format('d/m/Y');
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    /**
     * @param array<string, int|null> $mapping
     * @return array<int, string>
     */
    private function missingFields(array $mapping): array
    {
        $required = [
            'provider_code',
            'document_type',
            'document_number',
            'cups_code',
            'specialty',
            'request_date',
            'appointment_date',
            'specialist_hours',
        ];

        return array_values(
            array_filter(
                $required,
                static fn (string $field): bool =>
                    ($mapping[$field] ?? null) === null
            )
        );
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower($value);

        $value = preg_replace(
            '/[^a-z0-9]+/u',
            ' ',
            $value
        ) ?? $value;

        return trim(
            preg_replace('/\s+/', ' ', $value) ?? $value
        );
    }
}