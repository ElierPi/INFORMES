<?php

namespace App\Services\Informe202\Engine;

use App\Services\Informe202\Rules\AgeRangeRule;
use App\Services\Informe202\Rules\AllowedValuesRule;
use App\Services\Informe202\Rules\DateResultRule;
use App\Services\Informe202\Rules\FixedValueRule;
use App\Services\Informe202\Rules\ProtegerCodeRule;
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
    ProtegerCodeRule $protegerCodeRule,
    DusakawiCodeRule $dusakawiCodeRule,
    DateResultRule $dateResultRule,
    AgeRangeRule $ageRangeRule,
    CatalogValidationRule $catalogValidationRule,
    AllowedValuesRule $allowedValuesRule
) {
    $this->rules = [
        $fixedValueRule,
        $protegerCodeRule,
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
        if (($error['requiere_revision_manual'] ?? false) === true) {
            $reportedVariable = $error['variable'] ?? null;
            $variable = is_numeric($reportedVariable)
                ? (int) $reportedVariable
                : null;

            $message = mb_strtolower(
                (string) ($error['mensaje'] ?? '')
            );

            $reason = str_contains(
                $message,
                'afiliado en mención no fue identificado'
            ) || str_contains(
                $message,
                'afiliado en mencion no fue identificado'
            )
                ? 'La EPS no identificó al afiliado en su base. El sistema '
                    . 'conserva el tipo y número de documento sin cambios, '
                    . 'porque no deben deducirse por edad. La IPS debe tramitar '
                    . 'la actualización mediante el anexo de la 3047 y sus soportes.'
                : 'La EPS reportó un caso administrativo o clínico que '
                    . 'requiere revisión manual y no debe corregirse por inferencia.';

            return RuleDecision::manual(
                variable: $variable,
                currentValue: $variable !== null
                    ? ($record['variables'][$variable] ?? null)
                    : null,
                reason: $reason,
                rule: self::class,
            );
        }

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