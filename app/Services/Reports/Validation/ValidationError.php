<?php

namespace App\Services\Reports\Validation;

use JsonSerializable;

final class ValidationError implements JsonSerializable
{
    public function __construct(
        public readonly string $sheet,
        public readonly int $row,
        public readonly int $column,
        public readonly string $columnLetter,
        public readonly string $coordinate,
        public readonly string $field,
        public readonly mixed $value,
        public readonly string $message,
        public readonly ?string $help = null,
        public readonly array $allowedValues = [],
        public readonly string $severity = 'error',
        public readonly ?string $rule = null,
    ) {
    }

    public static function create(
        string $sheet,
        int $row,
        int $column,
        string $field,
        mixed $value,
        string $message,
        ?string $help = null,
        array $allowedValues = [],
        string $severity = 'error',
        ?string $rule = null,
    ): self {
        $columnLetter = self::columnLetter($column);

        return new self(
            sheet: $sheet,
            row: $row,
            column: $column,
            columnLetter: $columnLetter,
            coordinate: $columnLetter . $row,
            field: $field,
            value: $value,
            message: $message,
            help: $help,
            allowedValues: $allowedValues,
            severity: $severity,
            rule: $rule,
        );
    }

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    public function displayValue(): string
    {
        if ($this->value === null || $this->value === '') {
            return '(vacío)';
        }

        if (is_bool($this->value)) {
            return $this->value ? 'Sí' : 'No';
        }

        if (is_array($this->value)) {
            return json_encode(
                $this->value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '(valor no disponible)';
        }

        return (string) $this->value;
    }

    public function allowedValuesText(): ?string
    {
        if ($this->allowedValues === []) {
            return null;
        }

        $values = [];

        foreach ($this->allowedValues as $key => $description) {
            if (is_int($key)) {
                $values[] = (string) $description;
                continue;
            }

            $values[] = "{$key} = {$description}";
        }

        return implode(', ', $values);
    }

    public function toArray(): array
    {
        return [
            'sheet' => $this->sheet,
            'row' => $this->row,
            'column' => $this->column,
            'column_letter' => $this->columnLetter,
            'coordinate' => $this->coordinate,
            'field' => $this->field,
            'value' => $this->value,
            'display_value' => $this->displayValue(),
            'message' => $this->message,
            'help' => $this->help,
            'allowed_values' => $this->allowedValues,
            'allowed_values_text' => $this->allowedValuesText(),
            'severity' => $this->severity,
            'rule' => $this->rule,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function columnLetter(int $column): string
    {
        if ($column < 1) {
            throw new \InvalidArgumentException(
                'El número de columna debe ser mayor o igual a 1.'
            );
        }

        $letter = '';

        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $column = intdiv($column - 1, 26);
        }

        return $letter;
    }
}