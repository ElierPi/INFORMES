<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;

class FixedValueRule implements RuleInterface
{
    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool {
        return array_key_exists(
            'fixed_value',
            $definition
        );
    }

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision {
        $currentValue =
            $record['variables'][$variable] ?? null;

        $fixedValue = $definition['fixed_value'];

        if ((string) $currentValue === (string) $fixedValue) {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $currentValue,
                reason: 'El campo ya contiene el valor fijo requerido.',
                rule: self::class,
            );
        }

        return RuleDecision::automatic(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $fixedValue,
            reason: 'La Resolución 202 define un valor fijo para este campo.',
            rule: self::class,
        );
    }
}