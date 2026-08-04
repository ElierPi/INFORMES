<?php

namespace App\Services\Reports\Validation;

use DateTimeImmutable;
use App\Services\Reports\Validation\ValidationError;
use App\Services\Reports\Validation\ValidationResult;

class ConfigurableValidator
{
    public function validate(
        array $data,
        string $configKey,
        array $additionalErrors = []
    ): array {
        $configuration = config($configKey, []);
        $errors = $additionalErrors;
        $validRecords = [];

        foreach (($configuration['sheets'] ?? []) as $sheetKey => $definition) {
            $sheetData = $data[$sheetKey] ?? null;
            $sheetName = $definition['name'] ?? $sheetKey;
            $records = $sheetData['records'] ?? [];

            if (! is_array($sheetData)) {
                $errors[] = $this->error(
                    $sheetName,
                    null,
                    null,
                    null,
                    null,
                    'La hoja no fue procesada.'
                );
                continue;
            }

            $minimum = (int) ($definition['min_records'] ?? 0);
            $maximum = $definition['max_records'] ?? null;
            $count = count($records);

            if ($count < $minimum) {
                $errors[] = $this->error(
                    $sheetName,
                    null,
                    null,
                    'Cantidad de registros',
                    $count,
                    "La hoja requiere mínimo {$minimum} registro(s)."
                );
            }

            if (is_int($maximum) && $count > $maximum) {
                $errors[] = $this->error(
                    $sheetName,
                    null,
                    null,
                    'Cantidad de registros',
                    $count,
                    "La hoja permite máximo {$maximum} registro(s)."
                );
            }

            foreach ($records as $record) {
                $rowErrors = $this->validateRecord(
                    $sheetName,
                    $record,
                    $definition['columns'] ?? []
                );

                $errors = [...$errors, ...$rowErrors];

                if ($rowErrors === []) {
                    $validRecords[$sheetKey][] = $record;
                }
            }

            if (
                ($configuration['validation']['continuous_consecutives'] ?? true)
                && ($definition['has_consecutive'] ?? true)
                && ! ($definition['is_control'] ?? false)
            ) {
                $errors = [
                    ...$errors,
                    ...$this->validateSheetConsecutives(
                        $sheetName,
                        $records
                    ),
                ];
            }
        }

        if (($configuration['validation']['validate_control_total'] ?? true)) {
            $errors = [
                ...$errors,
                ...$this->validateControlTotal($data, $configuration),
            ];
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values($errors),
            'valid_records' => $validRecords,
            'total_errors' => count($errors),
            'summary' => $this->buildSummary(
                $data,
                $configuration,
                $errors
            ),
        ];
    }

    private function validateRecord(
        string $sheetName,
        array $record,
        array $columns
    ): array {
        $errors = [];
        $values = $record['values'] ?? [];
        $row = $record['excel_row'] ?? null;

        if (count($values) !== count($columns)) {
            return [
                $this->error(
                    $sheetName,
                    $row,
                    null,
                    null,
                    count($values),
                    'La cantidad de columnas es distinta a la configurada.'
                ),
            ];
        }

        foreach ($columns as $index => $definition) {
            $value = $values[$index] ?? null;
            $text = trim((string) $value);
            $column = $index + 1;
            $field = $definition['name'] ?? "Columna {$column}";

if (($definition['required'] ?? false) && $text === '') {

    $errors[] = [
        'sheet' => $sheetName,
        'row' => $row,
        'column' => $column,
        'column_letter' => $this->columnLetter($column),
        'coordinate' => $this->columnLetter($column) . $row,
        'field' => $field,
        'value' => $value,
        'display_value' => '(vacío)',
        'message' => 'El campo es obligatorio.',
        'help' => $definition['help'] ?? null,
        'allowed_values' => $definition['allowed'] ?? [],
        'allowed_labels' => $definition['allowed_labels'] ?? [],
        'severity' => 'error',
        'rule' => 'required',
    ];

    continue;
}

            if ($text === '') {
                continue;
            }

            $max = $definition['max'] ?? null;

            if (is_int($max) && mb_strlen($text) > $max) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    "La longitud máxima permitida es {$max}."
                );
            }

            $type = strtoupper((string) ($definition['type'] ?? 'A'));

            if (! $this->matchesType($text, $type)) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    "El valor no corresponde al tipo {$type}."
                );
            }

            $allowed = $definition['allowed'] ?? null;

            if (is_array($allowed) && ! in_array($text, $allowed, true)) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    'El valor no está dentro de los valores permitidos.'
                );
            }

            if (
                ($definition['uppercase'] ?? false)
                && $text !== mb_strtoupper($text)
            ) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    'El valor debe estar escrito en mayúsculas.'
                );
            }
        }

        return $errors;
    }

    private function matchesType(string $value, string $type): bool
    {
        return match ($type) {
            'N' => preg_match('/^\d+$/', $value) === 1,
            'D' => preg_match('/^\d+(\.\d+)?$/', $value) === 1,
            'F' => $this->isValidDate($value),
            'H' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
            'A', 'T' => true,
            default => true,
        };
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $value;
    }

    private function validateSheetConsecutives(
        string $sheetName,
        array $records
    ): array {
        $errors = [];
        $expected = 1;

        foreach ($records as $record) {
            $actual = trim((string) ($record['values'][1] ?? ''));

            if ($actual === '') {
                continue;
            }

            if ((int) $actual !== $expected) {
                $errors[] = $this->error(
                    $sheetName,
                    $record['excel_row'] ?? null,
                    2,
                    'Consecutivo de registro',
                    $actual,
                    "El consecutivo esperado era {$expected}."
                );
            }

            $expected++;
        }

        return $errors;
    }

    private function validateControlTotal(
        array $data,
        array $configuration
    ): array {
        $controlKey = $configuration['control_sheet'] ?? 'tipo1';
        $controlIndex = (int) ($configuration['control_total_index'] ?? 6);
        $controlRecords = $data[$controlKey]['records'] ?? [];

        if (count($controlRecords) !== 1) {
            return [];
        }

        $control = $controlRecords[0];
        $reported = (int) ($control['values'][$controlIndex] ?? 0);
        $actual = 0;

        foreach (($configuration['sheet_order'] ?? []) as $key) {
            if ($key === $controlKey) {
                continue;
            }

            $actual += count($data[$key]['records'] ?? []);
        }

        if ($reported === $actual) {
            return [];
        }

        return [
            $this->error(
                $configuration['sheets'][$controlKey]['name'] ?? $controlKey,
                $control['excel_row'] ?? null,
                $controlIndex + 1,
                'Número total de registros de detalle',
                $reported,
                "El total reportado es {$reported}, pero existen {$actual} registros de detalle."
            ),
        ];
    }

    private function buildSummary(
        array $data,
        array $configuration,
        array $errors
    ): array {
        $summary = [];

        foreach (($configuration['sheets'] ?? []) as $key => $definition) {
            $sheetName = $definition['name'] ?? $key;

            $summary[$key] = [
                'sheet' => $sheetName,
                'records' => count($data[$key]['records'] ?? []),
                'errors' => count(array_filter(
                    $errors,
                    fn (array $error): bool =>
                        ($error['sheet'] ?? null) === $sheetName
                )),
            ];
        }

        return $summary;
    }

    private function error(
        string $sheet,
        ?int $row,
        ?int $column,
        ?string $field,
        mixed $value,
        string $message
    ): array {
        return compact(
            'sheet',
            'row',
            'column',
            'field',
            'value',
            'message'
        );
    }

private function columnLetter(int $column): string
{
    $letter = '';

    while ($column > 0) {
        $remainder = ($column - 1) % 26;
        $letter = chr(65 + $remainder) . $letter;
        $column = intdiv($column - 1, 26);
    }

    return $letter;
}
}
