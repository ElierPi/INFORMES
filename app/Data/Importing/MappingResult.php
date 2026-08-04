<?php

namespace App\Data\Importing;

class MappingResult
{
    /**
     * @param array<string, MappingField|null> $fields
     * @param array<int, array<string, mixed>> $unmappedColumns
     * @param array<int, string> $missingFields
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $unmappedColumns = [],
        public readonly array $missingFields = [],
        public readonly float $score = 0,
        public readonly int $matchedFields = 0,
    ) {
    }

    public static function fromAnalysis(
        array $analysis
    ): self {
        $fields = [];

        foreach (
            $analysis['mapping'] ?? []
            as $internalField => $mapping
        ) {
            $fields[$internalField] =
                is_array($mapping)
                    ? MappingField::fromArray(
                        internalField: $internalField,
                        data: $mapping
                    )
                    : null;
        }

        return new self(
            fields: $fields,
            unmappedColumns:
                $analysis['unmapped_columns'] ?? [],
            missingFields:
                $analysis['missing_fields'] ?? [],
            score: (float) (
                $analysis['score'] ?? 0
            ),
            matchedFields: (int) (
                $analysis['matched_fields'] ?? 0
            ),
        );
    }

    public function get(
        string $internalField
    ): ?MappingField {
        return $this->fields[$internalField]
            ?? null;
    }

    public function has(
        string $internalField
    ): bool {
        return $this->get($internalField)
            instanceof MappingField;
    }

    public function requiresConfirmation(): bool
    {
        foreach ($this->fields as $field) {
            if (
                $field instanceof MappingField
                && $field->requiresConfirmation
            ) {
                return true;
            }
        }

        return false;
    }

    public function confirmedFieldsCount(): int
    {
        return count(
            array_filter(
                $this->fields,
                static fn (
                    ?MappingField $field
                ): bool =>
                    $field instanceof MappingField
                    && ! $field->requiresConfirmation
            )
        );
    }

    public function toArray(): array
    {
        return [
            'fields' => array_map(
                static fn (
                    ?MappingField $field
                ): ?array =>
                    $field?->toArray(),
                $this->fields
            ),
            'unmapped_columns' =>
                $this->unmappedColumns,
            'missing_fields' =>
                $this->missingFields,
            'score' => $this->score,
            'matched_fields' =>
                $this->matchedFields,
            'requires_confirmation' =>
                $this->requiresConfirmation(),
        ];
    }
}