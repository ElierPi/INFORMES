<?php

namespace App\Services\Informe202;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class Resolution202ExcelReader
{
    private const PREFERRED_SHEET_NAME = 'ESTRUCTURA';

    private const DEFAULT_RECORD_TYPE = '2';

    private const DEFAULT_PROVIDER_CODE = '444300063502';

    private const TOTAL_VARIABLES = 119;

    private const SOURCE_COLUMNS_WITHOUT_CONTROL = 116;

    public function __construct(
        private readonly AgeCalculator $ageCalculator
    ) {
    }

    public function read(
        string $filePath,
        string $cutoffDate
    ): array {
        if (! is_file($filePath)) {
            throw new RuntimeException(
                'No se encontró el archivo de la Resolución 202.'
            );
        }

        $spreadsheet = IOFactory::load($filePath);

        $sheet = $this->resolveSheet($spreadsheet);

        $headerRow = $this->findHeaderRow($sheet);

        $variableColumns = $this->mapVariableColumns(
            $sheet,
            $headerRow
        );

        $this->validateVariableMap($variableColumns);

        $records = [];
        $firstDataRow = $headerRow + 1;
        $lastDataRow = $sheet->getHighestDataRow();

        for (
            $excelRow = $firstDataRow;
            $excelRow <= $lastDataRow;
            $excelRow++
        ) {
            $nextSequence = count($records) + 1;

            $variables = $this->readVariables(
                sheet: $sheet,
                excelRow: $excelRow,
                variableColumns: $variableColumns,
                generatedSequence: $nextSequence
            );

            if ($this->recordIsEmpty($variables)) {
                continue;
            }

            $recordNumber = $variables[1] ?? null;
            $birthDate = $variables[9] ?? null;
            $sex = $variables[10] ?? null;

            try {
                $age = $this->ageCalculator->calculate(
                    $birthDate,
                    $cutoffDate
                );

                $ageError = null;
            } catch (Throwable $exception) {
                $age = null;
                $ageError = $exception->getMessage();
            }

            $records[] = [
                'excel_row' => $excelRow,

                'record_number' =>
                    is_numeric($recordNumber)
                        ? (int) $recordNumber
                        : $recordNumber,

                'birth_date' => $this->normalizeCellValue(
                    $birthDate
                ),

                'sex' => mb_strtoupper(
                    trim((string) $sex),
                    'UTF-8'
                ),

                'age' => $age,

                'age_error' => $ageError,

                'variables' => $variables,
            ];
        }

        return [
            'sheet_name' => $sheet->getTitle(),

            'header_row' => $headerRow,

            'cutoff_date' =>
                $this->ageCalculator->normalizeDate(
                    $cutoffDate
                ),

            'variable_columns' => $variableColumns,

            'generated_variables' => [
                '0' => ! isset($variableColumns[0]),
                '1' => ! isset($variableColumns[1]),
                '2' => ! isset($variableColumns[2]),
            ],

            'records' => $records,

            'total_records' => count($records),
        ];
    }

    private function resolveSheet(
        Spreadsheet $spreadsheet
    ): Worksheet {
        $preferred = $spreadsheet->getSheetByName(
            self::PREFERRED_SHEET_NAME
        );

        if ($preferred instanceof Worksheet) {
            return $preferred;
        }

        foreach (
            $spreadsheet->getWorksheetIterator()
            as $sheet
        ) {
            if ($sheet->getHighestDataRow() < 1) {
                continue;
            }

            $highestColumn = Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn()
            );

            if ($highestColumn > 1) {
                return $sheet;
            }

            if (! $this->isEmpty(
                $sheet->getCell('A1')->getValue()
            )) {
                return $sheet;
            }
        }

        throw new RuntimeException(
            'El archivo no contiene ninguna hoja con datos.'
        );
    }

    /**
     * Detecta dos formatos:
     *
     * 1. Encabezados numerados:
     *    "3. Tipo de documento", "4. Documento", etc.
     *
     * 2. Excel DUSAKAWI sin numeración:
     *    116 columnas desde TIPODOC hasta la variable 118.
     */
    private function findHeaderRow(
        Worksheet $sheet
    ): int {
        $maximumRowsToInspect = min(
            30,
            $sheet->getHighestDataRow()
        );

        $bestNumberedRow = null;
        $bestNumberedCount = 0;

        for (
            $row = 1;
            $row <= $maximumRowsToInspect;
            $row++
        ) {
            $highestColumnIndex =
                Coordinate::columnIndexFromString(
                    $sheet->getHighestDataColumn($row)
                );

            $numberedVariables = [];
            $nonEmptyHeaders = [];

            for (
                $columnIndex = 1;
                $columnIndex <= $highestColumnIndex;
                $columnIndex++
            ) {
                $header = trim(
                    (string) $sheet
                        ->getCell([
                            $columnIndex,
                            $row,
                        ])
                        ->getValue()
                );

                if ($header === '') {
                    continue;
                }

                $nonEmptyHeaders[] = $header;

                $variable = $this->extractVariableNumber(
                    $header
                );

                if ($variable !== null) {
                    $numberedVariables[$variable] = true;
                }
            }

            $numberedCount = count($numberedVariables);

            if ($numberedCount > $bestNumberedCount) {
                $bestNumberedCount = $numberedCount;
                $bestNumberedRow = $row;
            }

            /*
             * Formato DUSAKAWI:
             * exactamente 116 encabezados no vacíos y comienza por
             * TIPODOC, DOCUMENTO, PAPELLIDO...
             */
            if (
                count($nonEmptyHeaders)
                    === self::SOURCE_COLUMNS_WITHOUT_CONTROL
                && $this->looksLikeDusakawiHeader(
                    $nonEmptyHeaders
                )
            ) {
                return $row;
            }
        }

        if (
            $bestNumberedRow !== null
            && $bestNumberedCount >= 20
        ) {
            return $bestNumberedRow;
        }

        throw new RuntimeException(
            'No se encontró una fila de encabezados válida. '
            . 'El archivo debe traer encabezados numerados o las 116 columnas del formato DUSAKAWI.'
        );
    }

    /**
     * Si los encabezados están numerados, usa sus números.
     * Si son las 116 columnas DUSAKAWI sin numeración,
     * asigna A => variable 3, B => variable 4, ...,
     * hasta la variable 118.
     */
    private function mapVariableColumns(
        Worksheet $sheet,
        int $headerRow
    ): array {
        $highestColumnIndex =
            Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn(
                    $headerRow
                )
            );

        $numberedColumns = [];

        for (
            $columnIndex = 1;
            $columnIndex <= $highestColumnIndex;
            $columnIndex++
        ) {
            $header = trim(
                (string) $sheet
                    ->getCell([
                        $columnIndex,
                        $headerRow,
                    ])
                    ->getValue()
            );

            $variable = $this->extractVariableNumber(
                $header
            );

            if ($variable === null) {
                continue;
            }

            if (isset($numberedColumns[$variable])) {
                continue;
            }

            $numberedColumns[$variable] =
                Coordinate::stringFromColumnIndex(
                    $columnIndex
                );
        }

        if (count($numberedColumns) >= 20) {
            ksort($numberedColumns);

            return $numberedColumns;
        }

        /*
         * Fallback posicional para el Excel DUSAKAWI:
         * las 116 columnas representan variables 3 a 118.
         */
        if (
            $highestColumnIndex
                === self::SOURCE_COLUMNS_WITHOUT_CONTROL
        ) {
            $columns = [];

            for (
                $columnIndex = 1;
                $columnIndex
                    <= self::SOURCE_COLUMNS_WITHOUT_CONTROL;
                $columnIndex++
            ) {
                $variable = $columnIndex + 2;

                $columns[$variable] =
                    Coordinate::stringFromColumnIndex(
                        $columnIndex
                    );
            }

            return $columns;
        }

        throw new RuntimeException(
            sprintf(
                'La fila de encabezados contiene %d columnas. '
                . 'Para el formato DUSAKAWI se esperaban 116.',
                $highestColumnIndex
            )
        );
    }

    private function validateVariableMap(
        array $variableColumns
    ): void {
        $missing = [];

        for (
            $variable = 3;
            $variable <= 118;
            $variable++
        ) {
            if (! isset($variableColumns[$variable])) {
                $missing[] = $variable;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Faltan columnas obligatorias de la Resolución 202: '
                . implode(', ', $missing)
            );
        }
    }

    private function readVariables(
        Worksheet $sheet,
        int $excelRow,
        array $variableColumns,
        int $generatedSequence
    ): array {
        $variables = [];

        for (
            $variable = 0;
            $variable <= 118;
            $variable++
        ) {
            if (isset($variableColumns[$variable])) {
                $column = $variableColumns[$variable];

                $value = $sheet
                    ->getCell(
                        "{$column}{$excelRow}"
                    )
                    ->getCalculatedValue();

                $variables[$variable] =
                    $this->normalizeVariableValue(
                        $variable,
                        $value
                    );

                continue;
            }

            $variables[$variable] = match ($variable) {
                0 => self::DEFAULT_RECORD_TYPE,
                1 => $generatedSequence,
                2 => self::DEFAULT_PROVIDER_CODE,
                default => null,
            };
        }

        /*
         * Si las tres columnas existen pero vienen vacías,
         * se completan igualmente.
         */
        if ($this->isEmpty($variables[0] ?? null)) {
            $variables[0] = self::DEFAULT_RECORD_TYPE;
        }

        if ($this->isEmpty($variables[1] ?? null)) {
            $variables[1] = $generatedSequence;
        }

        if ($this->isEmpty($variables[2] ?? null)) {
            $variables[2] = self::DEFAULT_PROVIDER_CODE;
        }

        return $variables;
    }

    private function recordIsEmpty(
        array $variables
    ): bool {
        for (
            $variable = 3;
            $variable <= 118;
            $variable++
        ) {
            if (! $this->isEmpty(
                $variables[$variable] ?? null
            )) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeDusakawiHeader(
        array $headers
    ): bool {
        $normalized = array_map(
            fn (string $header): string =>
                $this->normalizeHeader($header),
            $headers
        );

        $expectedStart = [
            'TIPODOC',
            'DOCUMENTO',
            'PAPELLIDO',
            'SAPELLIDO',
            'PNOMBRE',
            'SNOMBRE',
            'FNACIMIENTO',
            'SEXO',
        ];

        foreach (
            $expectedStart
            as $index => $expected
        ) {
            if (
                ($normalized[$index] ?? null)
                !== $expected
            ) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(
        string $header
    ): string {
        $header = mb_strtoupper(
            trim($header),
            'UTF-8'
        );

        $header = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $header
        ) ?: $header;

        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            $header
        ) ?? $header;
    }

    private function extractVariableNumber(
        string $header
    ): ?int {
        if ($header === '') {
            return null;
        }

        if (
            ! preg_match(
                '/^(\d{1,3})\s*(?:[\.\-:]\s*|\s+)(.+)$/u',
                $header,
                $matches
            )
        ) {
            return null;
        }

        $variable = (int) $matches[1];
        $description = trim(
            (string) ($matches[2] ?? '')
        );

        if (
            $variable < 0
            || $variable > 118
            || $description === ''
            || is_numeric($description)
        ) {
            return null;
        }

        return $variable;
    }

    private function normalizeVariableValue(
        int $variable,
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        $definition = config(
            "resolucion202.fields.{$variable}",
            []
        );

        if (
            ($definition['type'] ?? null) === 'F'
            && is_numeric($value)
            && (float) $value > 0
        ) {
            try {
                return ExcelDate::excelToDateTimeObject(
                    (float) $value
                )->format('Y-m-d');
            } catch (Throwable) {
                return $value;
            }
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function normalizeCellValue(
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    private function isEmpty(
        mixed $value
    ): bool {
        return $value === null
            || trim((string) $value) === '';
    }
}