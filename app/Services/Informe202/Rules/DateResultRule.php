<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;

class DateResultRule implements RuleInterface
{
    private const NO_RESULT_DATES = [
        '1800-01-01',
        '1805-01-01',
        '1810-01-01',
        '1825-01-01',
        '1830-01-01',
        '1835-01-01',
    ];

    private const NOT_APPLICABLE_DATE =
        '1845-01-01';

    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool {
        return isset($definition['paired_date'])
            && in_array(
                $variable,
                config(
                    'resolucion202.rule_groups.laboratory_results',
                    []
                ),
                true
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

        $dateVariable =
            (int) $definition['paired_date'];

        $dateValue = trim(
            (string) (
                $record['variables'][$dateVariable]
                ?? ''
            )
        );

        if ($dateValue === self::NOT_APPLICABLE_DATE) {
            if ((string) $currentValue === '0') {
                return RuleDecision::valid(
                    variable: $variable,
                    currentValue: $currentValue,
                    reason: 'La fecha indica que el examen no aplica.',
                    rule: self::class,
                );
            }

            return RuleDecision::automatic(
                variable: $variable,
                currentValue: $currentValue,
                newValue: 0,
                reason:
                    "La variable {$dateVariable} contiene 1845-01-01; "
                    . 'el resultado debe registrarse como 0, no aplica.',
                rule: self::class,
            );
        }

        if (
            in_array(
                $dateValue,
                self::NO_RESULT_DATES,
                true
            )
        ) {
            if ((string) $currentValue === '998') {
                return RuleDecision::valid(
                    variable: $variable,
                    currentValue: $currentValue,
                    reason:
                        'La fecha especial y el resultado 998 son coherentes.',
                    rule: self::class,
                );
            }

            return RuleDecision::automatic(
                variable: $variable,
                currentValue: $currentValue,
                newValue: 998,
                reason:
                    "La variable {$dateVariable} contiene una fecha "
                    . 'especial de no realización o dato no disponible; '
                    . 'el resultado debe ser 998.',
                rule: self::class,
            );
        }

        return null;
    }
}