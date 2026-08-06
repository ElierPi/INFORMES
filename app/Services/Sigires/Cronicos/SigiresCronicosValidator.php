<?php

namespace App\Services\Sigires\Cronicos;

use DateTimeImmutable;

class SigiresCronicosValidator
{
    /**
     * @param  array<int, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    public function validate(array $values): array
    {
        $issues = [];

        foreach (config('sigires_cronicos.fields', []) as $position => $definition) {
            $value = $values[$position] ?? null;
            $required = (bool) ($definition['required'] ?? false);
            $code = (string) ($definition['code'] ?? '');
            $description = (string) ($definition['description'] ?? '');
            $fieldNumber = (int) $position + 1;

            if ($value === null || $value === '') {
                if ($required) {
                    $issues[] = $this->issue(
                        $fieldNumber,
                        $code,
                        $description,
                        'Obligatoriedad',
                        'El campo obligatorio está vacío.'
                    );
                }

                continue;
            }

            $text = trim((string) $value);
            $length = mb_strlen($text);
            $minimum = $definition['min'] ?? null;
            $maximum = $definition['max'] ?? null;

            if (is_int($minimum) && $length < $minimum) {
                $issues[] = $this->issue(
                    $fieldNumber,
                    $code,
                    $description,
                    'Longitud',
                    "La longitud {$length} es menor al mínimo {$minimum}."
                );
            }

            if (is_int($maximum) && $length > $maximum) {
                $issues[] = $this->issue(
                    $fieldNumber,
                    $code,
                    $description,
                    'Longitud',
                    "La longitud {$length} supera el máximo {$maximum}."
                );
            }

            $type = mb_strtoupper((string) ($definition['type'] ?? ''));

            if (! $this->matchesType($text, $type)) {
                $issues[] = $this->issue(
                    $fieldNumber,
                    $code,
                    $description,
                    'Tipo de dato',
                    "El valor '{$text}' no corresponde al tipo {$type}."
                );
            }

            $allowed = trim((string) ($definition['allowed'] ?? ''));

            if (
                $allowed !== ''
                && ! $this->isAllowed($text, $allowed)
            ) {
                $issues[] = $this->issue(
                    $fieldNumber,
                    $code,
                    $description,
                    'Valores permitidos',
                    "El valor '{$text}' no está incluido en {$allowed}."
                );
            }
        }

        return $issues;
    }

    private function matchesType(string $value, string $type): bool
    {
        return match ($type) {
            'DT' => $this->isDate($value),
            'UN' => preg_match('/^\d+$/', $value) === 1,
            'DE' => preg_match('/^\d+(?:\.\d+)?$/', $value) === 1,
            'VCYUN' => preg_match('/^[A-Z0-9]+$/i', $value) === 1,
            'VC' => preg_match('/^[A-Z0-9 ]+$/i', $value) === 1,
            default => true,
        };
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date instanceof DateTimeImmutable
            && (! is_array($errors)
                || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    private function isAllowed(string $value, string $expression): bool
    {
        foreach (array_map('trim', explode(',', $expression)) as $option) {
            if ($option === '') {
                continue;
            }

            if (preg_match('/^(-?\d+(?:\.\d+)?)-(-?\d+(?:\.\d+)?)$/', $option, $matches) === 1) {
                if (! is_numeric($value)) {
                    continue;
                }

                $numeric = (float) $value;

                if (
                    $numeric >= (float) $matches[1]
                    && $numeric <= (float) $matches[2]
                ) {
                    return true;
                }

                continue;
            }

            if (strcasecmp($value, $option) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(
        int $fieldNumber,
        string $code,
        string $field,
        string $type,
        string $message
    ): array {
        return [
            'position' => $fieldNumber,
            'code' => $code,
            'field' => $field,
            'type' => $type,
            'message' => $message,
        ];
    }
}
