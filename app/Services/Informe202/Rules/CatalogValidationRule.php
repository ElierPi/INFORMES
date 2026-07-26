<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;
use DateTime;
use Illuminate\Support\Str;

class CatalogValidationRule implements RuleInterface
{
    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool {
        return true;
    }

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision {
        $currentValue =
            $record['variables'][$variable] ?? null;

        /*
         * 1. Campos obligatorios vacíos.
         */
        if (
            ($definition['required'] ?? false)
            && $this->isEmpty($currentValue)
        ) {
            if (array_key_exists('fallback', $definition)) {
                return RuleDecision::automatic(
                    variable: $variable,
                    currentValue: $currentValue,
                    newValue: $definition['fallback'],
                    reason:
                        'El campo es obligatorio y la Resolución 202 '
                        . 'define un valor de respaldo.',
                    rule: self::class
                );
            }

            return RuleDecision::manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'El campo es obligatorio, pero no existe un valor '
                    . 'seguro que pueda deducirse automáticamente.',
                rule: self::class
            );
        }

        if ($this->isEmpty($currentValue)) {
            return null;
        }

        /*
         * 2. Normalización segura de nombres y textos.
         */
        $normalizedText = $this->normalizeTextIfNeeded(
            $currentValue,
            $definition
        );

        if (
            $normalizedText !== null
            && (string) $normalizedText
                !== (string) $currentValue
        ) {
            return RuleDecision::automatic(
                variable: $variable,
                currentValue: $currentValue,
                newValue: $normalizedText,
                reason:
                    'El texto fue normalizado a mayúsculas, sin tildes '
                    . 'ni caracteres especiales, según la Resolución 202.',
                rule: self::class
            );
        }

        /*
         * 3. Longitud máxima.
         * No se trunca automáticamente porque podría dañar un documento,
         * código o resultado clínico.
         */
        if (
            isset($definition['length'])
            && mb_strlen(trim((string) $currentValue))
                > (int) $definition['length']
        ) {
            return RuleDecision::manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'La longitud supera el máximo permitido de '
                    . $definition['length']
                    . ' caracteres. Debe revisarse el dato original.',
                rule: self::class
            );
        }

        /*
         * 4. Tipo de dato.
         */
        $typeError = $this->validateType(
            $currentValue,
            $definition['type'] ?? null
        );

        if ($typeError !== null) {
            return RuleDecision::manual(
                variable: $variable,
                currentValue: $currentValue,
                reason: $typeError,
                rule: self::class
            );
        }

        /*
         * 5. Valores permitidos cerrados.
         */
        if (
            isset($definition['allowed'])
            && is_array($definition['allowed'])
        ) {
            $allowedValues = array_map(
                'strval',
                array_keys($definition['allowed'])
            );

            if (! in_array(
                trim((string) $currentValue),
                $allowedValues,
                true
            )) {
                return RuleDecision::manual(
                    variable: $variable,
                    currentValue: $currentValue,
                    reason:
                        'El valor no está permitido. Valores aceptados: '
                        . implode(', ', $allowedValues)
                        . '. Debe seleccionarse el que corresponda '
                        . 'al caso real.',
                    rule: self::class
                );
            }
        }

        /*
         * 6. Rango numérico.
         */
        if (
            isset($definition['allowed_range'])
            && is_numeric($currentValue)
        ) {
            [$minimum, $maximum] =
                $definition['allowed_range'];

            $numericValue = (float) $currentValue;

            $specialValues = array_map(
                'strval',
                array_keys(
                    $definition['special_values'] ?? []
                )
            );

            $isSpecial = in_array(
                trim((string) $currentValue),
                $specialValues,
                true
            );

            if (
                ! $isSpecial
                && (
                    $numericValue < $minimum
                    || $numericValue > $maximum
                )
            ) {
                return RuleDecision::manual(
                    variable: $variable,
                    currentValue: $currentValue,
                    reason:
                        "El valor debe estar entre {$minimum} y "
                        . "{$maximum}, o corresponder a uno de los "
                        . 'valores especiales definidos.',
                    rule: self::class
                );
            }
        }

        return null;
    }

    private function normalizeTextIfNeeded(
        mixed $value,
        array $definition
    ): ?string {
        if (
            ! ($definition['uppercase'] ?? false)
            && ! ($definition['strip_accents'] ?? false)
            && ! (
                $definition['strip_special_characters']
                ?? false
            )
        ) {
            return null;
        }

        $text = trim((string) $value);

        if ($definition['strip_accents'] ?? false) {
            $text = Str::ascii($text);
        }

        if (
            $definition['strip_special_characters']
            ?? false
        ) {
            $text = preg_replace(
                '/[^A-Za-z0-9 ]+/',
                '',
                $text
            ) ?? $text;
        }

        if ($definition['uppercase'] ?? false) {
            $text = mb_strtoupper($text);
        }

        return trim(
            preg_replace('/\s+/', ' ', $text) ?? $text
        );
    }

    private function validateType(
        mixed $value,
        ?string $type
    ): ?string {
        $text = trim((string) $value);

        return match ($type) {
            'N' => preg_match('/^\d+$/', $text)
                ? null
                : 'El campo debe contener un número entero.',

            'D' => preg_match('/^\d+(\.\d+)?$/', $text)
                ? null
                : 'El campo debe contener un número decimal '
                    . 'usando punto como separador.',

            'F' => $this->isValidDate($text)
                ? null
                : 'La fecha debe tener el formato AAAA-MM-DD.',

            'A', 'T' => str_contains($text, '|')
                ? 'El campo no puede contener el carácter pipe.'
                : null,

            default => null,
        };
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat(
            'Y-m-d',
            $value
        );

        return $date !== false
            && $date->format('Y-m-d') === $value;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null
            || trim((string) $value) === '';
    }
}