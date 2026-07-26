<?php

namespace App\Services\Gestantes;

use DateTimeImmutable;

class GestantesValidationService
{
    public function validate(array $data): array
    {
        $errors = [];
        $validRecords = [];
        $detailConsecutives = [];
        $identificationKeys = [];
        $attentionKeys = [];
        $followUpKeys = [];

        foreach (config('gestantes.sheets', []) as $key => $definition) {
            $sheetData = $data[$key] ?? null;

            if (! is_array($sheetData)) {
                $errors[] = $this->error(
                    sheet: $definition['name'],
                    row: null,
                    column: null,
                    field: null,
                    value: null,
                    message: 'La hoja no fue procesada.'
                );

                continue;
            }

            foreach ($sheetData['records'] as $record) {
                $rowErrors = $this->validateRecord(
                    sheetKey: $key,
                    sheetName: $definition['name'],
                    record: $record,
                    columns: $definition['columns']
                );

                $errors = [...$errors, ...$rowErrors];

                if ($key !== 'control') {
                    $consecutive = trim((string) ($record['values'][1] ?? ''));

                    if ($consecutive !== '') {
                        $detailConsecutives[] = [
                            'sheet' => $definition['name'],
                            'row' => $record['excel_row'],
                            'value' => $consecutive,
                        ];
                    }
                }

                $duplicateErrors = $this->validateDuplicates(
                    sheetKey: $key,
                    sheetName: $definition['name'],
                    record: $record,
                    identificationKeys: $identificationKeys,
                    attentionKeys: $attentionKeys,
                    followUpKeys: $followUpKeys
                );

                $errors = [...$errors, ...$duplicateErrors];

                if ($rowErrors === [] && $duplicateErrors === []) {
                    $validRecords[$key][] = $record;
                }
            }
        }

        $errors = [
            ...$errors,
            ...$this->validateConsecutives($detailConsecutives),
            ...$this->validateControlTotals($data),
        ];

        return [
            'valid' => $errors === [],
            'errors' => array_values($errors),
            'valid_records' => $validRecords,
            'total_errors' => count($errors),
            'summary' => $this->buildSummary($data, $errors),
        ];
    }

    private function validateRecord(
        string $sheetKey,
        string $sheetName,
        array $record,
        array $columns
    ): array {
        $errors = [];
        $values = $record['values'];
        $row = $record['excel_row'];

        if (count($values) !== count($columns)) {
            $errors[] = $this->error(
                sheet: $sheetName,
                row: $row,
                column: null,
                field: null,
                value: count($values),
                message: 'La cantidad de columnas es distinta a la permitida.'
            );

            return $errors;
        }

        foreach ($columns as $index => $definition) {
            $value = $values[$index] ?? null;
            $text = trim((string) $value);
            $column = $index + 1;
            $field = $definition['name'];

            if (($definition['required'] ?? false) && $text === '') {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    'El campo es obligatorio.'
                );

                continue;
            }

            if ($text === '') {
                continue;
            }

            $max = $definition['max'] ?? null;

            if ($max !== null && mb_strlen($text) > $max) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    "La longitud máxima permitida es {$max}."
                );
            }

            $type = $definition['type'] ?? 'A';

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

            if (
                is_array($allowed)
                && ! in_array($text, $allowed, true)
            ) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    $column,
                    $field,
                    $value,
                    'El valor no está dentro de los valores permitidos.'
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
            'A', 'T' => true,
            default => true,
        };
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false
            && $date->format('Y-m-d') === $value;
    }

    private function validateDuplicates(
        string $sheetKey,
        string $sheetName,
        array $record,
        array &$identificationKeys,
        array &$attentionKeys,
        array &$followUpKeys
    ): array {
        $values = $record['values'];
        $row = $record['excel_row'];
        $errors = [];

        if ($sheetKey === 'identificaciones') {
            $key = strtoupper(trim((string) ($values[6] ?? '')))
                . '|'
                . strtoupper(trim((string) ($values[7] ?? '')));

            if (isset($identificationKeys[$key])) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    8,
                    'Número de identificación de la usuaria',
                    $values[7] ?? null,
                    "La gestante está repetida. Primera aparición en fila {$identificationKeys[$key]}."
                );
            } else {
                $identificationKeys[$key] = $row;
            }
        }

        if ($sheetKey === 'atenciones') {
            $key = implode('|', [
                strtoupper(trim((string) ($values[2] ?? ''))),
                strtoupper(trim((string) ($values[3] ?? ''))),
                trim((string) ($values[4] ?? '')),
                trim((string) ($values[5] ?? '')),
            ]);

            if (isset($attentionKeys[$key])) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    null,
                    'Identificación + fecha + CUPS',
                    $key,
                    "La atención está repetida. Primera aparición en fila {$attentionKeys[$key]}."
                );
            } else {
                $attentionKeys[$key] = $row;
            }
        }

        if ($sheetKey === 'seguimientos') {
            $key = implode('|', [
                strtoupper(trim((string) ($values[2] ?? ''))),
                strtoupper(trim((string) ($values[3] ?? ''))),
                trim((string) ($values[4] ?? '')),
                trim((string) ($values[5] ?? '')),
            ]);

            if (isset($followUpKeys[$key])) {
                $errors[] = $this->error(
                    $sheetName,
                    $row,
                    null,
                    'Identificación + tipo de caso + fecha',
                    $key,
                    "El seguimiento está repetido. Primera aparición en fila {$followUpKeys[$key]}."
                );
            } else {
                $followUpKeys[$key] = $row;
            }
        }

        return $errors;
    }

    private function validateConsecutives(array $items): array
    {
        $errors = [];
        $expected = 1;

        foreach ($items as $item) {
            $actual = (int) $item['value'];

            if ($actual !== $expected) {
                $errors[] = $this->error(
                    $item['sheet'],
                    $item['row'],
                    2,
                    'Consecutivo de registro',
                    $item['value'],
                    "El consecutivo esperado era {$expected}."
                );

                $expected = $actual + 1;

                continue;
            }

            $expected++;
        }

        return $errors;
    }

    private function validateControlTotals(array $data): array
    {
        $control = $data['control']['records'][0]['values'] ?? null;

        if (! is_array($control)) {
            return [];
        }

        $reported = (int) ($control[6] ?? 0);
        $actual = 0;

        foreach (['identificaciones', 'atenciones', 'seguimientos', 'urgencias'] as $key) {
            $actual += count($data[$key]['records'] ?? []);
        }

        if ($reported === $actual) {
            return [];
        }

        return [
            $this->error(
                '1 - Control',
                $data['control']['records'][0]['excel_row'] ?? 2,
                7,
                'Total de registros de detalle',
                $reported,
                "El total reportado es {$reported}, pero el archivo contiene {$actual} registros de detalle."
            ),
        ];
    }

    private function buildSummary(array $data, array $errors): array
    {
        $summary = [];

        foreach (config('gestantes.sheets', []) as $key => $definition) {
            $summary[$key] = [
                'sheet' => $definition['name'],
                'records' => count($data[$key]['records'] ?? []),
                'errors' => count(array_filter(
                    $errors,
                    fn (array $error): bool =>
                        ($error['sheet'] ?? null) === $definition['name']
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
        return [
            'sheet' => $sheet,
            'row' => $row,
            'column' => $column,
            'field' => $field,
            'value' => $value,
            'message' => $message,
        ];
    }
}
