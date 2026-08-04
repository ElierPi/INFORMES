<?php

namespace App\Services\Resolucion1552;

use App\Data\Importing\ImportedDataset;
use App\Data\Importing\ImportedRecord;
use App\Data\Resolucion1552\Resolucion1552Record;

class Resolucion1552DatasetTransformer
{
    public function __construct(
        private readonly Resolucion1552RecordTransformer $recordTransformer
    ) {
    }

    /**
     * @return array{
     *     records: array<int, Resolucion1552Record>,
     *     statistics: array<string, int>,
     *     errors: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>
     * }
     */
    public function transform(
        ImportedDataset $dataset
    ): array {
        $records = [];
        $errors = [];
        $warnings = [];

        foreach ($dataset->records as $importedRecord) {
            if (! $importedRecord instanceof ImportedRecord) {
                continue;
            }

            $record = $this->recordTransformer->transform(
                $importedRecord
            );

            $records[] = $record;

            foreach ($record->errors as $error) {
                $errors[] = $error;
            }

            foreach ($record->warnings as $warning) {
                $warnings[] = $warning;
            }
        }

        $validRecords = count(
            array_filter(
                $records,
                static fn (
                    Resolucion1552Record $record
                ): bool => $record->isValid()
            )
        );

        return [
            'records' => $records,

            'statistics' => [
                'records_count' => count($records),
                'valid_records_count' => $validRecords,
                'invalid_records_count' =>
                    count($records) - $validRecords,
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
            ],

            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Convierte el resultado a una estructura apta para JSON.
     */
    public function toArray(
        ImportedDataset $dataset
    ): array {
        $result = $this->transform($dataset);

        return [
            'statistics' => $result['statistics'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],

            'records' => array_map(
                static fn (
                    Resolucion1552Record $record
                ): array => $record->toArray(),
                $result['records']
            ),
        ];
    }
}