<?php

namespace App\Services\Importing;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ExcelAnalyzer
{
    public function __construct(
        private readonly HeaderNormalizer $normalizer,
        private readonly HeaderDetector $headerDetector
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(
        string $path,
        array $fieldDefinitions
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                'No se encontró el archivo Excel.'
            );
        }

        $spreadsheet = IOFactory::load($path);

        $candidates = [];

        foreach (
            $spreadsheet->getWorksheetIterator()
            as $worksheet
        ) {
            $candidate = $this->analyzeWorksheet(
                $worksheet,
                $fieldDefinitions
            );

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            throw new RuntimeException(
                'No se pudo encontrar una fila de encabezados reconocible.'
            );
        }

        usort(
            $candidates,
            static function (
                array $a,
                array $b
            ): int {
                $fieldComparison =
                    $b['matched_fields']
                    <=> $a['matched_fields'];

                if ($fieldComparison !== 0) {
                    return $fieldComparison;
                }

                return $b['score'] <=> $a['score'];
            }
        );

        $selected = $candidates[0];

        $worksheet = $spreadsheet->getSheetByName(
            $selected['sheet_name']
        );

        if ($worksheet === null) {
            throw new RuntimeException(
                'No se pudo volver a abrir la hoja seleccionada.'
            );
        }

        $records = $this->readRecords(
            worksheet: $worksheet,
            headerRow: $selected['header_row'],
            mapping: $selected['mapping']
        );

        return [
            'success' => true,
            'sheet_name' => $selected['sheet_name'],
            'header_row' => $selected['header_row'],
            'data_start_row' =>
                $selected['header_row'] + 1,
            'last_row' => $selected['last_row'],
            'last_column' =>
                $selected['last_column'],
            'score' => $selected['score'],
            'matched_fields' =>
                $selected['matched_fields'],
            'headers' => $selected['headers'],
            'mapping' => $selected['mapping'],
            'unmapped_columns' =>
                $selected['unmapped_columns'],
            'missing_fields' =>
                $this->missingFields(
                    $selected['mapping']
                ),
            'records_count' => count($records),
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyzeWorksheet(
        Worksheet $worksheet,
        array $fieldDefinitions
    ): ?array {
        $highestRow =
            $worksheet->getHighestDataRow();

        $highestColumn =
            $worksheet->getHighestDataColumn();

        if ($highestRow < 1) {
            return null;
        }

        $highestColumnIndex =
            Coordinate::columnIndexFromString(
                $highestColumn
            );

        $limit = min(
            $highestRow,
            (int) config(
                'report_importer.header_search_limit',
                30
            )
        );

        $best = null;

        for ($row = 1; $row <= $limit; $row++) {
            $headers = $this->readHeaders(
                worksheet: $worksheet,
                row: $row,
                highestColumnIndex:
                    $highestColumnIndex
            );

            $detected = $this->headerDetector->detect(
                headers: $headers,
                definitions: $fieldDefinitions
            );

            if (
                $best === null
                || $detected['matched_fields']
                    > $best['matched_fields']
                || (
                    $detected['matched_fields']
                        === $best['matched_fields']
                    && $detected['score']
                        > $best['score']
                )
            ) {
                $best = [
                    'sheet_name' =>
                        $worksheet->getTitle(),
                    'header_row' => $row,
                    'last_row' => $highestRow,
                    'last_column' => $highestColumn,
                    'headers' => $headers,
                    ...$detected,
                ];
            }
        }

        $minimum = (int) config(
            'report_importer.minimum_header_matches',
            3
        );

        if (
            $best === null
            || $best['matched_fields'] < $minimum
        ) {
            return null;
        }

        return $best;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHeaders(
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
            $letter =
                Coordinate::stringFromColumnIndex(
                    $column
                );

            $value = trim(
                (string) $worksheet
                    ->getCell($letter . $row)
                    ->getFormattedValue()
            );

            $headers[] = [
                'column_index' => $column,
                'column_letter' => $letter,
                'original' => $value,
                'normalized' =>
                    $this->normalizer->normalize(
                        $value
                    ),
            ];
        }

        return $headers;
    }

    /**
     * @param array<string, array<string, mixed>|null> $mapping
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRecords(
        Worksheet $worksheet,
        int $headerRow,
        array $mapping
    ): array {
        $records = [];
        $lastRow =
            $worksheet->getHighestDataRow();

        for (
            $row = $headerRow + 1;
            $row <= $lastRow;
            $row++
        ) {
            $data = [];
            $hasData = false;

            foreach ($mapping as $field => $mapped) {
                if (! is_array($mapped)) {
                    $data[$field] = null;
                    continue;
                }

                $cell = $worksheet->getCell(
                    $mapped['column_letter'] . $row
                );

                $value = $this->cellValue($cell);

                if (
                    $value !== null
                    && $value !== ''
                ) {
                    $hasData = true;
                }

                $data[$field] = $value;
            }

            if (! $hasData) {
                continue;
            }

            $records[] = [
                'source_row' => $row,
                'data' => $data,
            ];
        }

        return $records;
    }

    private function cellValue(
        \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
    ): mixed {
        $value = $cell->getValue();

        if (
            \PhpOffice\PhpSpreadsheet\Shared\Date
                ::isDateTime($cell)
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
     * @return array<int, string>
     */
    private function missingFields(
        array $mapping
    ): array {
        return array_keys(
            array_filter(
                $mapping,
                static fn ($value): bool =>
                    $value === null
            )
        );
    }
}