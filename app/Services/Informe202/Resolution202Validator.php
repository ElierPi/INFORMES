<?php

namespace App\Services\Informe202;

use DateTime;
use RuntimeException;

class Resolution202Validator
{
    public function validateField(
        int $variable,
        mixed $value
    ): array {
        $definition = config(
            "resolucion202.fields.{$variable}"
        );

        if (! is_array($definition)) {
            throw new RuntimeException(
                "No existe definición para la variable {$variable}."
            );
        }

        $errors = [];

        if (
            ($definition['required'] ?? false)
            && $this->isEmpty($value)
        ) {
            $errors[] = [
                'type' => 'required',
                'message' => 'El campo es obligatorio.',
            ];

            return $errors;
        }

        if ($this->isEmpty($value)) {
            return [];
        }

        $errors = array_merge(
            $errors,
            $this->validateLength(
                $value,
                $definition
            ),
            $this->validateType(
                $value,
                $definition
            ),
            $this->validateAllowedValues(
                $value,
                $definition
            ),
        );

        return $errors;
    }

    private function validateLength(
        mixed $value,
        array $definition
    ): array {
        if (! isset($definition['length'])) {
            return [];
        }

        $valueLength = mb_strlen(
            trim((string) $value)
        );

        if ($valueLength <= (int) $definition['length']) {
            return [];
        }

        return [[
            'type' => 'length',
            'message' =>
                "La longitud máxima permitida es {$definition['length']}.",
        ]];
    }

    private function validateType(
        mixed $value,
        array $definition
    ): array {
        $type = $definition['type'] ?? null;

        return match ($type) {
            'N' => $this->validateInteger($value),
            'D' => $this->validateDecimal($value),
            'F' => $this->validateDate($value),
            'A' => $this->validateAlphanumeric($value),
            default => [],
        };
    }

    private function validateInteger(mixed $value): array
    {
        $value = trim((string) $value);

        if (preg_match('/^-?\d+$/', $value)) {
            return [];
        }

        return [[
            'type' => 'numeric',
            'message' => 'El valor debe ser numérico entero.',
        ]];
    }

    private function validateDecimal(mixed $value): array
    {
        $value = trim((string) $value);

        if (preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return [];
        }

        return [[
            'type' => 'decimal',
            'message' =>
                'El valor debe ser numérico y usar punto como separador decimal.',
        ]];
    }

    private function validateDate(mixed $value): array
    {
        $value = trim((string) $value);

        $date = DateTime::createFromFormat(
            'Y-m-d',
            $value
        );

        $isValid = $date
            && $date->format('Y-m-d') === $value;

        if ($isValid) {
            return [];
        }

        return [[
            'type' => 'date',
            'message' =>
                'La fecha debe tener el formato AAAA-MM-DD.',
        ]];
    }

    private function validateAlphanumeric(
        mixed $value
    ): array {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        if (str_contains($value, '|')) {
            return [[
                'type' => 'pipe',
                'message' =>
                    'El valor no puede contener el carácter pipe.',
            ]];
        }

        return [];
    }

    private function validateAllowedValues(
        mixed $value,
        array $definition
    ): array {
        $allowed = $definition['allowed'] ?? null;

        if (! is_array($allowed)) {
            return [];
        }

        $allowedValues = array_map(
            'strval',
            array_keys($allowed)
        );

        if (
            in_array(
                trim((string) $value),
                $allowedValues,
                true
            )
        ) {
            return [];
        }

        return [[
            'type' => 'allowed',
            'message' =>
                'El valor no está dentro de los valores permitidos.',
            'allowed_values' => $allowedValues,
        ]];
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null
            || trim((string) $value) === '';
    }
}