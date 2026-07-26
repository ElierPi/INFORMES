<?php

namespace App\Services\Gestantes;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class GestantesExcelReader
{
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el archivo Excel de gestantes.');
        }

        $spreadsheet = $this->loadSpreadsheet($path);
        $result = [];

        try {
            $configuredSheets = config('gestantes.sheets', []);

            if (! is_array($configuredSheets) || $configuredSheets === []) {
                throw new RuntimeException('No existen hojas configuradas en config/gestantes.php.');
            }

            foreach ($configuredSheets as $sheetKey => $definition) {
                if (! is_array($definition)) {
                    throw new RuntimeException("La configuración de la hoja '{$sheetKey}' no es válida.");
                }

                $sheetName = $definition['name'] ?? null;
                $columns = $definition['columns'] ?? [];

                if (! is_string($sheetName) || trim($sheetName) === '') {
                    throw new RuntimeException("La hoja configurada con clave '{$sheetKey}' no tiene un nombre válido.");
                }

                if (! is_array($columns) || $columns === []) {
                    throw new RuntimeException("No hay columnas configuradas para la hoja '{$sheetName}'.");
                }

                $sheet = $spreadsheet->getSheetByName($sheetName);

                if (! $sheet instanceof Worksheet) {
                    $availableSheets = implode(', ', $spreadsheet->getSheetNames());
                    throw new RuntimeException(
                        "No se encontró la hoja obligatoria: {$sheetName}. Hojas disponibles: {$availableSheets}."
                    );
                }

                $expectedColumns = count($columns);

                $result[$sheetKey] = [
                    'sheet_name' => $sheetName,
                    'expected_columns' => $expectedColumns,
                    'headers' => $this->readHeaders(
                        sheet: $sheet,
                        expectedColumns: $expectedColumns
                    ),
                    'records' => $this->readRecords(
                        sheet: $sheet,
                        columns: $columns,
                        sheetKey: (string) $sheetKey,
                        sheetName: $sheetName
                    ),
                ];
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return $result;
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        try {
            return IOFactory::load($path);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible abrir el archivo Excel: '.$exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function readHeaders(Worksheet $sheet, int $expectedColumns): array
    {
        $headers = [];

        for ($column = 1; $column <= $expectedColumns; $column++) {
            $cell = $sheet->getCell([$column, 1]);
            $headers[] = trim((string) $cell->getFormattedValue());
        }

        return $headers;
    }

    private function readRecords(
        Worksheet $sheet,
        array $columns,
        string $sheetKey,
        string $sheetName
    ): array {
        $records = [];
        $highestRow = $sheet->getHighestDataRow();
        $expectedColumns = count($columns);

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = [];

            for ($column = 1; $column <= $expectedColumns; $column++) {
                $cell = $sheet->getCell([$column, $row]);
                $columnDefinition = $columns[$column - 1] ?? [];

                $values[] = $this->normalizeCellValue(
                    cell: $cell,
                    columnDefinition: is_array($columnDefinition) ? $columnDefinition : []
                );
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            if ($this->isTemplatePlaceholderRow(
                sheetKey: $sheetKey,
                sheetName: $sheetName,
                values: $values
            )) {
                continue;
            }

            $records[] = [
                'excel_row' => $row,
                'values' => $values,
            ];
        }

        return $records;
    }

    private function normalizeCellValue(Cell $cell, array $columnDefinition): mixed
    {
        $rawValue = $cell->getValue();

        if ($rawValue === null) {
            return null;
        }

        $columnType = $this->resolveColumnType($columnDefinition);
        $isDateField = $columnType === 'F';

        if (is_string($rawValue) && str_starts_with(trim($rawValue), '=')) {
            try {
                $calculatedValue = $cell->getCalculatedValue();

                return $this->normalizeTypedValue(
                    value: $calculatedValue,
                    cell: $cell,
                    isDateField: $isDateField
                );
            } catch (Throwable) {
                return $this->normalizeTypedValue(
                    value: $cell->getFormattedValue(),
                    cell: $cell,
                    isDateField: $isDateField
                );
            }
        }

        return $this->normalizeTypedValue(
            value: $rawValue,
            cell: $cell,
            isDateField: $isDateField
        );
    }

    private function normalizeTypedValue(mixed $value, Cell $cell, bool $isDateField): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($isDateField) {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_numeric($value)) {
                return $this->convertExcelDate(value: $value, cell: $cell);
            }

            return $this->normalizeScalarValue(value: $value, normalizeDates: true);
        }

        return $this->normalizeScalarValue(value: $value, normalizeDates: false);
    }

    private function convertExcelDate(mixed $value, Cell $cell): mixed
    {
        try {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        } catch (Throwable) {
            return $this->normalizeScalarValue(
                value: $cell->getFormattedValue(),
                normalizeDates: true
            );
        }
    }

    private function normalizeScalarValue(mixed $value, bool $normalizeDates = false): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $normalizeDates
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            if ($normalizeDates) {
                $normalizedDate = $this->normalizeDateString($value);

                if ($normalizedDate !== null) {
                    return $normalizedDate;
                }
            }

            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value;
    }

    private function normalizeDateString(string $value): ?string
    {
        $value = trim($value);
        $formats = ['Y/m/d', 'Y-m-d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if (! $date instanceof DateTimeImmutable) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            if ($date->format($format) !== $value) {
                continue;
            }

            return $date->format('Y-m-d');
        }

        return null;
    }

    private function resolveColumnType(array $columnDefinition): string
    {
        $type = $columnDefinition['type']
            ?? $columnDefinition['tipo']
            ?? $columnDefinition['data_type']
            ?? $columnDefinition['datatype']
            ?? '';

        return mb_strtoupper(trim((string) $type));
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (! $this->isEmptyValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isTemplatePlaceholderRow(
        string $sheetKey,
        string $sheetName,
        array $values
    ): bool {
        $identifier = $this->normalizeIdentifier($sheetKey.' '.$sheetName);

        if (str_contains($identifier, 'control')) {
            return false;
        }

        if (
            str_contains($identifier, 'id gestantes')
            || str_contains($identifier, 'identificaciones')
            || str_contains($identifier, 'identificacion')
        ) {
            return $this->areValuesEmpty([
                $values[6] ?? null,
                $values[7] ?? null,
            ]);
        }

        if (str_contains($identifier, 'atenciones')) {
            return $this->areValuesEmpty([
                $values[2] ?? null,
                $values[3] ?? null,
                $values[4] ?? null,
            ]);
        }

        if (str_contains($identifier, 'seguimientos')) {
            return $this->areValuesEmpty([
                $values[2] ?? null,
                $values[3] ?? null,
                $values[5] ?? null,
            ]);
        }

        if (str_contains($identifier, 'urgencias')) {
            return $this->areValuesEmpty([
                $values[2] ?? null,
                $values[3] ?? null,
                $values[4] ?? null,
            ]);
        }

        return false;
    }

    private function areValuesEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (! $this->isEmptyValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }
}