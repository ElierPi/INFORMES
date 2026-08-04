<?php

namespace App\Services\Resolucion1552;

use App\Data\Reports\RecordValidationResult;
use App\Data\Reports\ValidationIssue;
use App\Data\Resolucion1552\Resolucion1552Record;
use App\Services\Reports\Engine\ConfigDrivenRecordValidator;

final class Resolucion1552Validator
{
    public function __construct(
        private readonly ConfigDrivenRecordValidator $validator,
    ) {
    }

    public function validateRecord(
        Resolucion1552Record $record,
    ): RecordValidationResult {
        return $this->validator->validate(
            data: $record->officialValues(),
            columns: config('resolucion1552.columns', []),
            sourceRow: $record->sourceRow,
        );
    }

    /**
     * @param array<int, Resolucion1552Record> $records
     */
    public function validateRecords(array $records): array
    {
        $results = [];
        $errors = [];
        $warnings = [];

        foreach ($records as $record) {
            if (! $record instanceof Resolucion1552Record) {
                continue;
            }

            $result = $this->validateRecord($record);

            $results[] = $result;
            $errors = [...$errors, ...$result->errors];
            $warnings = [...$warnings, ...$result->warnings];
        }

        $validRecords = count(array_filter(
            $results,
            static fn (RecordValidationResult $result): bool =>
                $result->isValid()
        ));

        return [
            'valid' => $errors === [],

            'statistics' => [
                'records_count' => count($results),
                'valid_records_count' => $validRecords,
                'invalid_records_count' =>
                    count($results) - $validRecords,
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
            ],

            'records' => $results,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, Resolucion1552Record> $records
     */
    public function toArray(array $records): array
    {
        $validation = $this->validateRecords($records);

        return [
            'valid' => $validation['valid'],
            'statistics' => $validation['statistics'],

            'errors' => array_map(
                static fn (ValidationIssue $issue): array =>
                    $issue->toArray(),
                $validation['errors']
            ),

            'warnings' => array_map(
                static fn (ValidationIssue $issue): array =>
                    $issue->toArray(),
                $validation['warnings']
            ),

            'records' => array_map(
                static fn (RecordValidationResult $result): array =>
                    $result->toArray(),
                $validation['records']
            ),
        ];
    }
}