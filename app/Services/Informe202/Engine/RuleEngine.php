<?php

namespace App\Services\Informe202\Engine;

use App\Services\Informe202\Rules\AgeRangeRule;
use App\Services\Informe202\Rules\AllowedValuesRule;
use App\Services\Informe202\Rules\DateResultRule;
use App\Services\Informe202\Rules\FixedValueRule;
use App\Services\Informe202\Rules\RuleInterface;
use App\Services\Informe202\Rules\CatalogValidationRule;
use App\Services\Informe202\Rules\DusakawiCodeRule;

class RuleEngine
{
    /**
     * El orden importa:
     * primero reglas que sí pueden deducir una corrección;
     * al final, validaciones generales.
     *
     * @var array<int, RuleInterface>
     */
    private array $rules;

public function __construct(
    private VariableResolver $variableResolver,
    FixedValueRule $fixedValueRule,
    DusakawiCodeRule $dusakawiCodeRule,
    DateResultRule $dateResultRule,
    AgeRangeRule $ageRangeRule,
    CatalogValidationRule $catalogValidationRule,
    AllowedValuesRule $allowedValuesRule
) {
    $this->rules = [
        $fixedValueRule,
        $dusakawiCodeRule,
        $dateResultRule,
        $ageRangeRule,
        $catalogValidationRule,
        $allowedValuesRule,
    ];
}
    public function resolve(
        array $record,
        array $error
    ): RuleDecision {
        $variable =
            $this->variableResolver->resolve($error);

        if ($variable === null) {
            return RuleDecision::manual(
                variable: null,
                currentValue: null,
                reason:
                    'No fue posible identificar la variable afectada '
                    . 'a partir del reporte de la EPS.',
                rule: self::class,
            );
        }

        $definition = config(
            "resolucion202.fields.{$variable}"
        );

        if (! is_array($definition)) {
            return RuleDecision::manual(
                variable: $variable,
                currentValue:
                    $record['variables'][$variable] ?? null,
                reason:
                    "La variable {$variable} no existe en el catálogo.",
                rule: self::class,
            );
        }

        foreach ($this->rules as $rule) {
            if (
                ! $rule->supports(
                    $variable,
                    $definition,
                    $record,
                    $error
                )
            ) {
                continue;
            }

            $decision = $rule->evaluate(
                $variable,
                $definition,
                $record,
                $error
            );

            if ($decision !== null) {
                return $decision;
            }
        }

        return RuleDecision::manual(
            variable: $variable,
            currentValue:
                $record['variables'][$variable] ?? null,
            reason:
                'La variable fue identificada, pero todavía no existe '
                . 'una regla automática que determine el valor correcto.',
            rule: self::class,
        );
    }
}