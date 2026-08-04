<?php

namespace App\Services\Reports\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class ConfigurableExcelDebugger
{
    public function inspectRow(
        string $path,
        string $sheetName,
        int $row,
        int $before = 1,
        int $after = 1
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo: {$path}");
        }

        if ($row < 1) {
            throw new RuntimeException('La fila debe ser mayor o igual a 1.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'No fue posible abrir el Excel: '.$e->getMessage(),
                previous: $e
            );
        }

        try {
            $sheet = $this->findSheet($spreadsheet, $sheetName);

            if (! $sheet instanceof Worksheet) {
                throw new RuntimeException(
                    "No se encontró la hoja '{$sheetName}'. Disponibles: ".
                    implode(', ', $spreadsheet->getSheetNames())
                );
            }

            $start = max(1, $row - max(0, $before));
            $end = min(
                max($row, $sheet->getHighestRow()),
                $row + max(0, $after)
            );

            $rows = [];

            for ($current = $start; $current <= $end; $current++) {
                $rows[] = $this->inspectSingleRow($sheet, $current);
            }

            return [
                'file' => basename($path),
                'sheet' => $sheet->getTitle(),
                'requested_row' => $row,
                'highest_row' => $sheet->getHighestRow(),
                'highest_data_row' => $sheet->getHighestDataRow(),
                'highest_column' => $sheet->getHighestColumn(),
                'highest_data_column' => $sheet->getHighestDataColumn(),
                'rows' => $rows,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function inspectWorkbook(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo: {$path}");
        }

        $spreadsheet = IOFactory::load($path);

        try {
            $sheets = [];

            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $sheets[] = [
                    'name' => $sheet->getTitle(),
                    'state' => $sheet->getSheetState(),
                    'highest_row' => $sheet->getHighestRow(),
                    'highest_data_row' => $sheet->getHighestDataRow(),
                    'highest_column' => $sheet->getHighestColumn(),
                    'highest_data_column' => $sheet->getHighestDataColumn(),
                    'merged_ranges' => array_values($sheet->getMergeCells()),
                    'hidden_rows' => $this->hiddenRows($sheet),
                ];
            }

            return [
                'file' => basename($path),
                'sheet_count' => count($sheets),
                'sheets' => $sheets,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function inspectSingleRow(Worksheet $sheet, int $row): array
    {
        $lastColumn = Coordinate::columnIndexFromString(
            $sheet->getHighestColumn()
        );

        $cells = [];
        $nonEmpty = 0;

        for ($column = 1; $column <= $lastColumn; $column++) {
            $cell = $sheet->getCell([$column, $row]);
            $coordinate = $cell->getCoordinate();
            $raw = $cell->getValue();

            try {
                $calculated = $cell->isFormula()
                    ? $cell->getCalculatedValue()
                    : $raw;
            } catch (Throwable $e) {
                $calculated = '[ERROR DE FÓRMULA] '.$e->getMessage();
            }

            $formatted = $cell->getFormattedValue();
            $merged = $this->mergedRangeForCell($sheet, $coordinate);

            if ($raw !== null && trim((string) $formatted) !== '') {
                $nonEmpty++;
            }

            $letter = Coordinate::stringFromColumnIndex($column);

            $cells[] = [
                'coordinate' => $coordinate,
                'column' => $column,
                'column_letter' => $letter,
                'raw_value' => $raw,
                'formatted_value' => $formatted,
                'calculated_value' => $calculated,
                'data_type' => $cell->getDataType(),
                'is_formula' => $cell->isFormula(),
                'formula' => $cell->isFormula() ? $raw : null,
                'is_merged' => $merged !== null,
                'merged_range' => $merged,
                'column_hidden' => ! $sheet
                    ->getColumnDimension($letter)
                    ->getVisible(),
            ];
        }

        return [
            'row' => $row,
            'row_hidden' => ! $sheet->getRowDimension($row)->getVisible(),
            'row_height' => $sheet->getRowDimension($row)->getRowHeight(),
            'non_empty_cells' => $nonEmpty,
            'is_visually_empty' => $nonEmpty === 0,
            'cells' => $cells,
        ];
    }

    private function mergedRangeForCell(
        Worksheet $sheet,
        string $coordinate
    ): ?string {
        foreach ($sheet->getMergeCells() as $range) {
            if (Coordinate::coordinateIsInsideRange($coordinate, $range)) {
                return $range;
            }
        }

        return null;
    }

    private function hiddenRows(Worksheet $sheet): array
    {
        $hidden = [];

        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if (! $sheet->getRowDimension($row)->getVisible()) {
                $hidden[] = $row;
            }
        }

        return $hidden;
    }

    private function findSheet(
        object $spreadsheet,
        string $requestedName
    ): ?Worksheet {
        $exact = $spreadsheet->getSheetByName($requestedName);

        if ($exact instanceof Worksheet) {
            return $exact;
        }

        $requested = $this->normalize($requestedName);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($this->normalize($sheet->getTitle()) === $requested) {
                return $sheet;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i',
            'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }
}
