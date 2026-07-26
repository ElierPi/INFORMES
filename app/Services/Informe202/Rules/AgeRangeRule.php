<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;

class AgeRangeRule implements RuleInterface
{
    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool {
        return isset($definition['age_months']);
    }

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision {
        $currentValue =
            $record['variables'][$variable] ?? null;

        $ageMonths =
            $record['age']['months'] ?? null;

        if ($ageMonths === null) {
            return RuleDecision::manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'No fue posible calcular la edad para aplicar esta regla.',
                rule: self::class,
            );
        }

        [$minimum, $maximum] =
            $definition['age_months'];

        if (
            $ageMonths >= $minimum
            && $ageMonths <= $maximum
        ) {
            return null;
        }

        if ((string) $currentValue === '0') {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    "La edad está fuera del rango {$minimum}-{$maximum} "
                    . 'meses y el campo ya está en 0.',
                rule: self::class,
            );
        }

        return RuleDecision::automatic(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La edad es de {$ageMonths} meses y está fuera del "
                . "rango permitido {$minimum}-{$maximum}; "
                . 'corresponde registrar 0, no aplica.',
            rule: self::class,
        );
    }
}