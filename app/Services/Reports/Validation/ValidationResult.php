<?php

namespace App\Services\Reports\Validation;

use Countable;
use JsonSerializable;

final class ValidationResult implements Countable, JsonSerializable
{
    /**
     * @param array<int, ValidationError> $errors
     * @param array<int, ValidationError> $warnings
     * @param array<int, array<string, mixed>> $validRecords
     */
    public function __construct(
        private array $errors = [],
        private array $warnings = [],
        private array $validRecords = [],
        private array $summary = [],
    ) {
    }

    public function addError(ValidationError $error): void
    {
        $this->errors[] = $error;
    }

    public function addWarning(ValidationError $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addValidRecord(array $record): void
    {
        $this->validRecords[] = $record;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function isValid(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * @return array<int, ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, ValidationError>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function validRecords(): array
    {
        return $this->validRecords;
    }

    public function setSummary(array $summary): void
    {
        $this->summary = $summary;
    }

    public function summary(): array
    {
        return array_merge([
            'valid' => $this->isValid(),
            'total_errors' => count($this->errors),
            'total_warnings' => count($this->warnings),
            'total_valid_records' => count($this->validRecords),
        ], $this->summary);
    }

    public function errorsBySheet(): array
    {
        $grouped = [];

        foreach ($this->errors as $error) {
            $grouped[$error->sheet][] = $error;
        }

        return $grouped;
    }

    public function count(): int
    {
        return count($this->errors);
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'summary' => $this->summary(),
            'errors' => array_map(
                static fn (ValidationError $error): array => $error->toArray(),
                $this->errors
            ),
            'warnings' => array_map(
                static fn (ValidationError $warning): array => $warning->toArray(),
                $this->warnings
            ),
            'valid_records' => $this->validRecords,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}