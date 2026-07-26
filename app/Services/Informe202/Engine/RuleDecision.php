<?php

namespace App\Services\Informe202\Engine;

class RuleDecision
{
    public function __construct(
        public string $status,
        public ?int $variable,
        public mixed $currentValue,
        public mixed $newValue,
        public string $reason,
        public ?string $rule = null
    ) {
    }

    public static function automatic(
        int $variable,
        mixed $currentValue,
        mixed $newValue,
        string $reason,
        string $rule
    ): self {
        return new self(
            status: 'automatic',
            variable: $variable,
            currentValue: $currentValue,
            newValue: $newValue,
            reason: $reason,
            rule: $rule
        );
    }

    public static function manual(
        ?int $variable,
        mixed $currentValue,
        string $reason,
        ?string $rule = null
    ): self {
        return new self(
            status: 'manual',
            variable: $variable,
            currentValue: $currentValue,
            newValue: null,
            reason: $reason,
            rule: $rule
        );
    }

    public static function valid(
        int $variable,
        mixed $currentValue,
        string $reason,
        string $rule
    ): self {
        return new self(
            status: 'valid',
            variable: $variable,
            currentValue: $currentValue,
            newValue: null,
            reason: $reason,
            rule: $rule
        );
    }

    public function toArray(): array
    {
        return [
            'estado' => $this->status,
            'variable' => $this->variable,
            'valor_actual' => $this->currentValue,
            'valor_nuevo' => $this->newValue,
            'motivo' => $this->reason,
            'regla' => $this->rule,
        ];
    }
}