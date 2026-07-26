<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;

class AllowedValuesRule implements RuleInterface
{
    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool {
        return isset($definition['allowed'])
            && is_array($definition['allowed']);
    }

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision {
        $currentValue =
            $record['variables'][$variable] ?? null;

        $allowedValues = array_map(
            'strval',
            array_keys($definition['allowed'])
        );

        if (
            in_array(
                trim((string) $currentValue),
                $allowedValues,
                true
            )
        ) {
            return null;
        }

        return RuleDecision::manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El valor no está permitido. Valores aceptados: '
                . implode(', ', $allowedValues)
                . '. Debe seleccionarse el valor que corresponda '
                . 'al caso real.',
            rule: self::class,
        );
    }
}