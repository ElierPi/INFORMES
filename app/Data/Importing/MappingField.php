<?php

namespace App\Data\Importing;

class MappingField
{
    public function __construct(
        public readonly string $internalField,
        public readonly int $columnIndex,
        public readonly string $columnLetter,
        public readonly string $header,
        public readonly string $normalizedHeader,
        public readonly float $confidence,
        public readonly ?string $matchedAlias = null,
        public readonly ?string $matchType = null,
        public readonly bool $requiresConfirmation = false,
    ) {
    }

    public static function fromArray(
        string $internalField,
        array $data
    ): self {
        return new self(
            internalField: $internalField,
            columnIndex: (int) (
                $data['column_index'] ?? 0
            ),
            columnLetter: (string) (
                $data['column_letter'] ?? ''
            ),
            header: (string) (
                $data['header'] ?? ''
            ),
            normalizedHeader: (string) (
                $data['normalized_header'] ?? ''
            ),
            confidence: (float) (
                $data['confidence'] ?? 0
            ),
            matchedAlias: isset($data['matched_alias'])
                ? (string) $data['matched_alias']
                : null,
            matchType: isset($data['match_type'])
                ? (string) $data['match_type']
                : null,
            requiresConfirmation: (bool) (
                $data['requires_confirmation'] ?? false
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'internal_field' => $this->internalField,
            'column_index' => $this->columnIndex,
            'column_letter' => $this->columnLetter,
            'header' => $this->header,
            'normalized_header' =>
                $this->normalizedHeader,
            'confidence' => $this->confidence,
            'matched_alias' => $this->matchedAlias,
            'match_type' => $this->matchType,
            'requires_confirmation' =>
                $this->requiresConfirmation,
        ];
    }
}