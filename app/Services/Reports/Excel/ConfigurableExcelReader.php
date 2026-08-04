<?php

namespace App\Services\Reports\Excel;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class ConfigurableExcelReader
{
    public function read(string $path, string $configKey): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el archivo Excel.');
        }

        $configuration = config($configKey);

        if (! is_array($configuration)) {
            throw new RuntimeException("No existe la configuración '{$configKey}'.");
        }

        $sheets = $configuration['sheets'] ?? [];

        if (! is_array($sheets) || $sheets === []) {
            throw new RuntimeException(
                "La configuración '{$configKey}' no contiene hojas."
            );
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible abrir el archivo Excel: '.$exception->getMessage(),
                previous: $exception
            );
        }

        $result = [];

        try {
            foreach ($sheets as $sheetKey => $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                $columns = $definition['columns'] ?? [];
                $requiredSheet = (bool) ($definition['required_sheet'] ?? true);
                $sheet = $this->findSheet($spreadsheet, $definition);

                if (! $sheet instanceof Worksheet) {
                    if ($requiredSheet) {
                        throw new RuntimeException(
                            "No se encontró la hoja obligatoria ".
                            "'".($definition['name'] ?? $sheetKey)."'. ".
                            'Hojas disponibles: '.
                            implode(', ', $spreadsheet->getSheetNames()).'.'
                        );
                    }

                    $result[$sheetKey] = [
                        'sheet_name' => $definition['name'] ?? $sheetKey,
                        'found' => false,
                        'expected_columns' => count($columns),
                        'headers' => [],
                        'records' => [],
                    ];

                    continue;
                }

                $recordType = (string) ($definition['record_type'] ?? '');
                $headerRow = (int) ($definition['header_row'] ?? 2);
                $configuredStart = (int) ($definition['data_start_row'] ?? 3);

                $dataStartRow = ($definition['detect_data_start'] ?? true)
                    ? $this->detectDataStartRow(
                        $sheet,
                        $recordType,
                        $configuredStart
                    )
                    : $configuredStart;

                $result[$sheetKey] = [
                    'sheet_name' => $sheet->getTitle(),
                    'found' => true,
                    'expected_columns' => count($columns),
                    'headers' => $this->readHeaders(
                        $sheet,
                        count($columns),
                        $headerRow
                    ),
                    'records' => $this->readRecords(
                        $sheet,
                        $columns,
                        $dataStartRow,
                        $recordType
                    ),
                ];
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return $result;
    }

    private function detectDataStartRow(
        Worksheet $sheet,
        string $recordType,
        int $fallback
    ): int {
        if ($recordType === '') {
            return $fallback;
        }

        $highestRow = $sheet->getHighestDataRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $value = trim((string) $sheet->getCell([1, $row])->getFormattedValue());

            if ($value === $recordType) {
                return $row;
            }
        }

        // La hoja puede estar vacía y contener únicamente encabezados.
        return max($fallback, $highestRow + 1);
    }

    private function findSheet(
        Spreadsheet $spreadsheet,
        array $definition
    ): ?Worksheet {
        $names = array_values(array_filter([
            $definition['name'] ?? null,
            ...($definition['aliases'] ?? []),
        ], fn ($value) => is_string($value) && trim($value) !== ''));

        foreach ($names as $name) {
            $sheet = $spreadsheet->getSheetByName($name);

            if ($sheet instanceof Worksheet) {
                return $sheet;
            }
        }

        $normalizedNames = array_map(
            fn (string $value): string => $this->normalizeIdentifier($value),
            $names
        );

        foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
            if (in_array(
                $this->normalizeIdentifier($candidate->getTitle()),
                $normalizedNames,
                true
            )) {
                return $candidate;
            }
        }

        return null;
    }

    private function readHeaders(
        Worksheet $sheet,
        int $expectedColumns,
        int $headerRow
    ): array {
        $headers = [];

        for ($column = 1; $column <= $expectedColumns; $column++) {
            $headers[] = trim((string) $sheet
                ->getCell([$column, $headerRow])
                ->getFormattedValue());
        }

        return $headers;
    }

    private function readRecords(
        Worksheet $sheet,
        array $columns,
        int $dataStartRow,
        string $recordType
    ): array {
        $records = [];
        $highestRow = $sheet->getHighestDataRow();
        $expectedColumns = count($columns);

        if ($dataStartRow > $highestRow) {
            return [];
        }

        for ($row = $dataStartRow; $row <= $highestRow; $row++) {
            $values = [];

            for ($column = 1; $column <= $expectedColumns; $column++) {
                $values[] = $this->normalizeCellValue(
                    $sheet->getCell([$column, $row]),
                    $columns[$column - 1] ?? []
                );
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            // Evita leer títulos, numeración de columnas o filas ajenas.
            if (
                $recordType !== ''
                && trim((string) ($values[0] ?? '')) !== $recordType
            ) {
                continue;
            }

            $records[] = [
                'excel_row' => $row,
                'values' => $values,
            ];
        }

        return $records;
    }

    private function normalizeCellValue(
        Cell $cell,
        array $columnDefinition
    ): mixed {
        $rawValue = $cell->getValue();

        if ($rawValue === null) {
            return null;
        }

        $type = strtoupper(trim((string) ($columnDefinition['type'] ?? 'A')));

        if (is_string($rawValue) && str_starts_with(trim($rawValue), '=')) {
            try {
                $rawValue = $cell->getCalculatedValue();
            } catch (Throwable) {
                $rawValue = $cell->getFormattedValue();
            }
        }

        if ($type === 'F') {
            return $this->normalizeDate($rawValue, $cell);
        }

        if ($type === 'H') {
            return $this->normalizeTime($rawValue, $cell);
        }

        if ($rawValue instanceof DateTimeInterface) {
            return $rawValue->format('Y-m-d H:i:s');
        }

        if (is_bool($rawValue)) {
            return $rawValue ? '1' : '0';
        }

        if (is_string($rawValue)) {
            $rawValue = trim($rawValue);

            return $rawValue === '' ? null : $rawValue;
        }

        return $rawValue;
    }

    private function normalizeDate(mixed $value, Cell $cell): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (Throwable) {
                $value = $cell->getFormattedValue();
            }
        }

        $text = trim((string) $value);

        foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);

            if ($date && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return $text === '' ? null : $text;
    }

    private function normalizeTime(mixed $value, Cell $cell): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)
                    ->format('H:i');
            } catch (Throwable) {
                $value = $cell->getFormattedValue();
            }
        }

        $text = trim((string) $value);

        foreach (['H:i', 'H:i:s', 'h:i A', 'g:i A'] as $format) {
            $time = DateTimeImmutable::createFromFormat('!'.$format, $text);

            if ($time instanceof DateTimeImmutable) {
                return $time->format('H:i');
            }
        }

        return $text === '' ? null : $text;
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i',
            'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }
}
