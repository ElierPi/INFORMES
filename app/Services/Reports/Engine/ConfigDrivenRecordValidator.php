<?php

namespace App\Services\Reports\Engine;

use App\Data\Reports\RecordValidationResult;
use App\Data\Reports\ValidationIssue;
use DateTimeImmutable;
use Throwable;

final class ConfigDrivenRecordValidator
{
    public function __construct(
        private readonly CatalogValueResolver $catalogResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $columns
     */
    public function validate(
        array $data,
        array $columns,
        int $sourceRow,
    ): RecordValidationResult {
        $errors = [];
        $warnings = [];
        $normalized = [];

        usort(
            $columns,
            static fn (array $a, array $b): int =>
                ((int) ($a['index'] ?? 0))
                <=>
                ((int) ($b['index'] ?? 0))
        );

        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');
            $value = $data[$field] ?? $column['default'] ?? '';

            $value = $this->normalizeValue(
                value: $value,
                column: $column,
                sourceRow: $sourceRow,
                warnings: $warnings,
            );

            $normalized[$field] = $value;

            if (
                ($column['required'] ?? false) &&
                $value === ''
            ) {
                $errors[] = $this->issue(
                    type: 'required',
                    column: $column,
                    message: 'El campo es obligatorio.',
                    sourceRow: $sourceRow,
                    value: $value,
                );

                continue;
            }

            if ($value === '') {
                continue;
            }

            /*
             * Si un valor de catálogo no pudo resolverse, evitamos sumar
             * errores de longitud. En ese caso se reportará únicamente el
             * error de catálogo inválido, que es el problema real.
             */
            $isUnresolvedCatalog =
                ($column['type'] ?? null) === 'catalog'
                && $this->catalogResolver->resolve(
                    (string) ($column['catalog'] ?? ''),
                    $value
                ) === null;

            if (! $isUnresolvedCatalog) {
                $length = mb_strlen($value, 'UTF-8');

                if (
                    isset($column['min_length']) &&
                    $length < (int) $column['min_length']
                ) {
                    $errors[] = $this->issue(
                        type: 'min_length',
                        column: $column,
                        message: sprintf(
                            'Debe tener mínimo %d caracteres.',
                            (int) $column['min_length']
                        ),
                        sourceRow: $sourceRow,
                        value: $value,
                    );
                }

                if (
                    isset($column['max_length']) &&
                    $length > (int) $column['max_length']
                ) {
                    $errors[] = $this->issue(
                        type: 'max_length',
                        column: $column,
                        message: sprintf(
                            'Debe tener máximo %d caracteres.',
                            (int) $column['max_length']
                        ),
                        sourceRow: $sourceRow,
                        value: $value,
                    );
                }
            }

            if (! $this->validType($value, $column)) {
                $errors[] = $this->issue(
                    type: 'invalid_type',
                    column: $column,
                    message: sprintf(
                        'El valor no cumple con el tipo %s.',
                        (string) ($column['type'] ?? 'string')
                    ),
                    sourceRow: $sourceRow,
                    value: $value,
                );
            }

            $allowed = $column['allowed'] ?? null;

            if (
                is_array($allowed) &&
                ! in_array($value, $allowed, true)
            ) {
                $errors[] = $this->issue(
                    type: 'not_allowed',
                    column: $column,
                    message: sprintf(
                        'Valor no permitido. Valores válidos: %s.',
                        implode(', ', $allowed)
                    ),
                    sourceRow: $sourceRow,
                    value: $value,
                );
            }

            if (
                isset($column['minimum']) &&
                is_numeric($value) &&
                (float) $value < (float) $column['minimum']
            ) {
                $errors[] = $this->issue(
                    type: 'minimum',
                    column: $column,
                    message: sprintf(
                        'El valor mínimo permitido es %s.',
                        $column['minimum']
                    ),
                    sourceRow: $sourceRow,
                    value: $value,
                );
            }
        }

        $this->validateCrossFieldRules(
            data: $normalized,
            columns: $columns,
            sourceRow: $sourceRow,
            errors: $errors,
        );

        return new RecordValidationResult(
            sourceRow: $sourceRow,
            normalizedData: $normalized,
            errors: $errors,
            warnings: $warnings,
        );
    }

    private function normalizeValue(
        mixed $value,
        array $column,
        int $sourceRow,
        array &$warnings,
    ): string {
        $value = trim((string) $value);
        $type = (string) ($column['type'] ?? 'string');

        if ($value === '') {
            return '';
        }

        return match ($type) {
            'alpha' => mb_strtoupper($value, 'UTF-8'),

            'numeric' =>
                preg_replace('/\D+/', '', $value) ?? '',

            'alphanumeric' =>
                preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '',

            'decimal' =>
                str_replace(',', '.', $value),

            'contact' =>
                $this->normalizeContact($value),

            'catalog' =>
                $this->normalizeCatalog(
                    value: $value,
                    column: $column,
                    sourceRow: $sourceRow,
                    warnings: $warnings,
                ),

            default => $value,
        };
    }

    private function normalizeCatalog(
        string $value,
        array $column,
        int $sourceRow,
        array &$warnings,
    ): string {
        $catalogName = $column['catalog'] ?? null;

        if (! is_string($catalogName) || $catalogName === '') {
            return $value;
        }

        $resolved = $this->catalogResolver->resolve(
            $catalogName,
            $value
        );

        if ($resolved === null) {
            return $value;
        }

        if ($resolved !== $value) {
            $warnings[] = ValidationIssue::warning(
                type: 'catalog_transformed',
                field: (string) ($column['field'] ?? ''),
                fieldIndex: (int) ($column['index'] ?? 0),
                fieldName: (string) ($column['name'] ?? ''),
                message: sprintf(
                    'El valor "%s" se transformó en "%s".',
                    $value,
                    $resolved
                ),
                sourceRow: $sourceRow,
                value: $value,
            );
        }

        return $resolved;
    }

    private function validType(string $value, array $column): bool
    {
        $type = (string) ($column['type'] ?? 'string');

        return match ($type) {
            'numeric' => ctype_digit($value),

            'alpha' =>
                preg_match('/^[A-Za-zÁÉÍÓÚÜÑ]+$/u', $value) === 1,

            'alphanumeric' =>
                preg_match('/^[A-Za-z0-9]+$/', $value) === 1,

            'decimal' => is_numeric($value),

            'contact' =>
                preg_match('/^\d{10}(,\d{10})?$/', $value) === 1,

            'date' =>
                $this->parseDate(
                    $value,
                    (string) ($column['format'] ?? 'd/m/Y')
                ) !== null,

            'catalog' =>
                $this->catalogResolver->resolve(
                    (string) ($column['catalog'] ?? ''),
                    $value
                ) !== null,

            default => true,
        };
    }

    private function validateCrossFieldRules(
        array $data,
        array $columns,
        int $sourceRow,
        array &$errors,
    ): void {
        $columnsByIndex = [];

        foreach ($columns as $column) {
            $columnsByIndex[(int) ($column['index'] ?? 0)] = $column;
        }

        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');
            $value = (string) ($data[$field] ?? '');

            foreach (($column['rules'] ?? []) as $rule) {
                if (($rule['type'] ?? null) === 'not_future') {
                    $date = $this->parseDate(
                        $value,
                        (string) ($column['format'] ?? 'd/m/Y')
                    );

                    if (
                        $date !== null &&
                        $date > new DateTimeImmutable('today')
                    ) {
                        $errors[] = $this->issue(
                            type: 'future_date',
                            column: $column,
                            message: 'La fecha no puede ser futura.',
                            sourceRow: $sourceRow,
                            value: $value,
                        );
                    }
                }

                if (($rule['type'] ?? null) === 'date_not_before') {
                    $referenceIndex = (int) ($rule['field_index'] ?? 0);
                    $referenceColumn = $columnsByIndex[$referenceIndex] ?? null;

                    if ($referenceColumn === null) {
                        continue;
                    }

                    $referenceField =
                        (string) ($referenceColumn['field'] ?? '');

                    $referenceValue =
                        (string) ($data[$referenceField] ?? '');

                    $date = $this->parseDate(
                        $value,
                        (string) ($column['format'] ?? 'd/m/Y')
                    );

                    $referenceDate = $this->parseDate(
                        $referenceValue,
                        (string) ($referenceColumn['format'] ?? 'd/m/Y')
                    );

                    if (
                        $date !== null &&
                        $referenceDate !== null &&
                        $date < $referenceDate
                    ) {
                        $errors[] = $this->issue(
                            type: 'date_not_before',
                            column: $column,
                            message: sprintf(
                                'La fecha no puede ser anterior al campo %d.',
                                $referenceIndex
                            ),
                            sourceRow: $sourceRow,
                            value: $value,
                        );
                    }
                }
            }
        }
    }

    private function normalizeContact(string $value): string
    {
        $phones = array_map(
            static fn (string $phone): string =>
                preg_replace('/\D+/', '', $phone) ?? '',
            explode(',', $value)
        );

        $phones = array_values(array_filter($phones));

        return implode(',', array_slice($phones, 0, 2));
    }

    private function parseDate(
        string $value,
        string $format,
    ): ?DateTimeImmutable {
        try {
            $date = DateTimeImmutable::createFromFormat(
                '!' . $format,
                $value
            );

            if ($date === false) {
                return null;
            }

            return $date->format($format) === $value
                ? $date
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function issue(
        string $type,
        array $column,
        string $message,
        int $sourceRow,
        mixed $value = null,
    ): ValidationIssue {
        return ValidationIssue::error(
            type: $type,
            field: (string) ($column['field'] ?? ''),
            fieldIndex: (int) ($column['index'] ?? 0),
            fieldName: (string) ($column['name'] ?? ''),
            message: $message,
            sourceRow: $sourceRow,
            value: $value,
        );
    }
}