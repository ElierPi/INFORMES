<?php

namespace App\Data\Reports;

final class ValidationIssue
{
    public function __construct(
        public readonly string $level,
        public readonly string $type,
        public readonly string $field,
        public readonly int $fieldIndex,
        public readonly string $fieldName,
        public readonly string $message,
        public readonly int $sourceRow,
        public readonly mixed $value = null,
    ) {
    }

    public static function error(
        string $type,
        string $field,
        int $fieldIndex,
        string $fieldName,
        string $message,
        int $sourceRow,
        mixed $value = null,
    ): self {
        return new self(
            level: 'error',
            type: $type,
            field: $field,
            fieldIndex: $fieldIndex,
            fieldName: $fieldName,
            message: $message,
            sourceRow: $sourceRow,
            value: $value,
        );
    }

    public static function warning(
        string $type,
        string $field,
        int $fieldIndex,
        string $fieldName,
        string $message,
        int $sourceRow,
        mixed $value = null,
    ): self {
        return new self(
            level: 'warning',
            type: $type,
            field: $field,
            fieldIndex: $fieldIndex,
            fieldName: $fieldName,
            message: $message,
            sourceRow: $sourceRow,
            value: $value,
        );
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'type' => $this->type,
            'field' => $this->field,
            'field_index' => $this->fieldIndex,
            'field_name' => $this->fieldName,
            'message' => $this->message,
            'source_row' => $this->sourceRow,
            'value' => $this->value,
        ];
    }
}