<?php

namespace App\Services\Informe202\Engine;

use InvalidArgumentException;

class RuleDecision
{
    /**
     * @param array<int, mixed> $changes
     */
    public function __construct(
        public string $status,
        public ?int $variable,
        public mixed $currentValue,
        public mixed $newValue,
        public string $reason,
        public ?string $rule = null,
        public array $changes = []
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
            rule: $rule,
            changes: [$variable => $newValue]
        );
    }

    /**
     * Crea una decisión que modifica varias variables del mismo registro.
     *
     * La decisión se aplica de forma atómica por el servicio: primero se
     * validan todas las variables y después se escriben todos los cambios.
     *
     * @param array<int, mixed> $changes
     * @param array<int, mixed> $currentValues
     */
    public static function automaticMany(
        array $changes,
        array $currentValues,
        string $reason,
        string $rule
    ): self {
        $normalized = [];

        foreach ($changes as $variable => $newValue) {
            if (! is_int($variable) && ! ctype_digit((string) $variable)) {
                throw new InvalidArgumentException(
                    'Las variables de una corrección múltiple deben ser numéricas.'
                );
            }

            $normalized[(int) $variable] = $newValue;
        }

        if ($normalized === []) {
            throw new InvalidArgumentException(
                'Una corrección múltiple debe contener al menos una variable.'
            );
        }

        $firstVariable = array_key_first($normalized);

        return new self(
            status: 'automatic',
            variable: $firstVariable,
            currentValue: $currentValues[$firstVariable] ?? null,
            newValue: $normalized[$firstVariable],
            reason: $reason,
            rule: $rule,
            changes: $normalized
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

    /**
     * @return array<int, mixed>
     */
    public function automaticChanges(): array
    {
        if ($this->status !== 'automatic') {
            return [];
        }

        if ($this->changes !== []) {
            return $this->changes;
        }

        return $this->variable === null
            ? []
            : [$this->variable => $this->newValue];
    }

    public function toArray(): array
    {
        return [
            'estado' => $this->status,
            'variable' => $this->variable,
            'valor_actual' => $this->currentValue,
            'valor_nuevo' => $this->newValue,
            'cambios' => $this->automaticChanges(),
            'motivo' => $this->reason,
            'regla' => $this->rule,
        ];
    }
}
