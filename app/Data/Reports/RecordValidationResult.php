<?php

namespace App\Data\Reports;

final class RecordValidationResult
{
    /**
     * @param array<string, string> $normalizedData
     * @param array<int, ValidationIssue> $errors
     * @param array<int, ValidationIssue> $warnings
     */
    public function __construct(
        public readonly int $sourceRow,
        public readonly array $normalizedData,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function toArray(): array
    {
        return [
            'source_row' => $this->sourceRow,
            'valid' => $this->isValid(),
            'data' => $this->normalizedData,
            'errors_count' => count($this->errors),
            'warnings_count' => count($this->warnings),

            'errors' => array_map(
                static fn (ValidationIssue $issue): array => $issue->toArray(),
                $this->errors
            ),

            'warnings' => array_map(
                static fn (ValidationIssue $issue): array => $issue->toArray(),
                $this->warnings
            ),
        ];
    }
}