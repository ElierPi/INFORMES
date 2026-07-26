<?php

namespace App\Services\Informe202;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class Resolution202ExcelReader
{
    private const SHEET_NAME = 'ESTRUCTURA';

    public function __construct(
        private readonly AgeCalculator $ageCalculator
    ) {
    }

    /**
     * Lee todos los registros del informe 202.
     */
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

        $sheet = $spreadsheet->getSheetByName(
            self::SHEET_NAME
        );

        if (! $sheet) {
            throw new RuntimeException(
                'El archivo no contiene la hoja ESTRUCTURA.'
            );
        }

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
            $variables = $this->readVariables(
                $sheet,
                $excelRow,
                $variableColumns
            );

            /*
             * Una fila sin tipo de registro ni consecutivo
             * se considera vacía.
             */
            if (
                $this->isEmpty($variables[0] ?? null)
                && $this->isEmpty($variables[1] ?? null)
            ) {
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
            } catch (\Throwable $exception) {
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

                'sex' => strtoupper(
                    trim((string) $sex)
                ),

                'age' => $age,

                'age_error' => $ageError,

                /*
                 * Todas las variables de la 0 a la 118.
                 */
                'variables' => $variables,
            ];
        }

        return [
            'sheet_name' => self::SHEET_NAME,
            'header_row' => $headerRow,
            'cutoff_date' =>
                $this->ageCalculator->normalizeDate(
                    $cutoffDate
                ),
            'variable_columns' => $variableColumns,
            'records' => $records,
            'total_records' => count($records),
        ];
    }

    /**
     * Busca la fila que contiene los encabezados de variables.
     */
    private function findHeaderRow(
        Worksheet $sheet
    ): int {
        $maximumRowsToInspect = min(
            20,
            $sheet->getHighestDataRow()
        );

        for (
            $row = 1;
            $row <= $maximumRowsToInspect;
            $row++
        ) {
            $foundVariableZero = false;
            $foundBirthDate = false;

            foreach (
                $sheet->getRowIterator($row, $row)
                as $rowObject
            ) {
                foreach (
                    $rowObject->getCellIterator()
                    as $cell
                ) {
                    $value = trim(
                        (string) $cell->getValue()
                    );

                    if (
                        preg_match(
                            '/^0\s*\./',
                            $value
                        )
                    ) {
                        $foundVariableZero = true;
                    }

                    if (
                        preg_match(
                            '/^9\s*\./',
                            $value
                        )
                    ) {
                        $foundBirthDate = true;
                    }
                }
            }

            if (
                $foundVariableZero
                && $foundBirthDate
            ) {
                return $row;
            }
        }

        throw new RuntimeException(
            'No se encontró la fila de encabezados de las variables 0 a 118.'
        );
    }

    /**
     * Relaciona cada variable con su columna real.
     *
     * Ejemplo:
     * 0 => B
     * 1 => C
     * 9 => K
     */
    private function mapVariableColumns(
        Worksheet $sheet,
        int $headerRow
    ): array {
        $columns = [];

        $highestColumnIndex =
            $sheet->getHighestDataColumn(
                $headerRow
            );

        $highestColumnIndex =
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate
                ::columnIndexFromString(
                    $highestColumnIndex
                );

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

            /*
             * Acepta encabezados como:
             * "54. Suministro..."
             * "54 Suministro..."
             */
            if (
                ! preg_match(
                    '/^(\d{1,3})\s*[\.\-:]?\s*/u',
                    $header,
                    $matches
                )
            ) {
                continue;
            }

            $variable = (int) $matches[1];

            if (
                $variable < 0
                || $variable > 118
            ) {
                continue;
            }

            $columns[$variable] =
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate
                    ::stringFromColumnIndex(
                        $columnIndex
                    );
        }

        ksort($columns);

        return $columns;
    }

    private function validateVariableMap(
        array $variableColumns
    ): void {
        $missing = [];

        for (
            $variable = 0;
            $variable <= 118;
            $variable++
        ) {
            if (! isset($variableColumns[$variable])) {
                $missing[] = $variable;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Faltan columnas de variables en la hoja ESTRUCTURA: '
                . implode(', ', $missing)
            );
        }
    }

private function readVariables(
    Worksheet $sheet,
    int $excelRow,
    array $variableColumns
): array {
    $variables = [];

    for ($variable = 0; $variable <= 118; $variable++) {
        $column = $variableColumns[$variable];

        $cell = $sheet->getCell(
            "{$column}{$excelRow}"
        );

        $value = $cell->getCalculatedValue();

        $variables[$variable] =
            $this->normalizeVariableValue(
                $variable,
                $value
            );
    }

    return $variables;
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

    /*
     * Convierte fechas seriales de Excel a AAAA-MM-DD.
     */
    if (
        ($definition['type'] ?? null) === 'F'
        && is_numeric($value)
        && (float) $value > 0
    ) {
        try {
            return ExcelDate::excelToDateTimeObject(
                (float) $value
            )->format('Y-m-d');
        } catch (\Throwable) {
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