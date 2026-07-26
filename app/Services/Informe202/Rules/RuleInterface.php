<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;

interface RuleInterface
{
    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool;

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision;
}