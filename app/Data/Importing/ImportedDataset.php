<?php

namespace App\Data\Importing;

use App\Models\ImportProfile;

class ImportedDataset
{
    /**
     * @param array<int, ImportedRecord> $records
     * @param array<int, array<string, mixed>> $headers
     * @param array<int, array<string, mixed>> $warnings
     * @param array<string, mixed> $statistics
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $sheetName,
        public readonly int $headerRow,
        public readonly int $dataStartRow,
        public readonly int $lastRow,
        public readonly string $lastColumn,

        public readonly MappingResult $mapping,

        public readonly array $records,
        public readonly array $headers = [],
        public readonly array $warnings = [],
        public readonly array $statistics = [],
        public readonly array $metadata = [],

        public readonly ?ImportProfile $profile = null,
    ) {
    }

    public static function fromAnalysis(
        array $analysis,
        ?ImportProfile $profile = null
    ): self {
        $records = array_map(
            static fn (array $record): ImportedRecord =>
                ImportedRecord::fromArray($record),
            $analysis['records'] ?? []
        );

        return new self(
            sheetName: (string) (
                $analysis['sheet_name'] ?? ''
            ),

            headerRow: (int) (
                $analysis['header_row'] ?? 0
            ),

            dataStartRow: (int) (
                $analysis['data_start_row'] ?? 0
            ),

            lastRow: (int) (
                $analysis['last_row'] ?? 0
            ),

            lastColumn: (string) (
                $analysis['last_column'] ?? ''
            ),

            mapping:
                MappingResult::fromAnalysis(
                    $analysis
                ),

            records: $records,

            headers:
                $analysis['headers'] ?? [],

            warnings:
                $analysis['warnings'] ?? [],

            statistics:
                $analysis['statistics'] ?? [],

            metadata: [
                'success' =>
                    $analysis['success'] ?? true,

                'records_count' =>
                    count($records),

                'matched_fields' =>
                    $analysis['matched_fields'] ?? 0,

                'mapping_score' =>
                    $analysis['score'] ?? 0,
            ],

            profile: $profile,
        );
    }

    public function recordsCount(): int
    {
        return count($this->records);
    }

    public function validRecordsCount(): int
    {
        return count(
            array_filter(
                $this->records,
                static fn (
                    ImportedRecord $record
                ): bool => $record->isValid()
            )
        );
    }

    public function invalidRecordsCount(): int
    {
        return $this->recordsCount()
            - $this->validRecordsCount();
    }

    public function warningsCount(): int
    {
        $count = count($this->warnings);

        foreach ($this->records as $record) {
            $count += count($record->warnings);
        }

        return $count;
    }

    public function findBySourceRow(
        int $sourceRow
    ): ?ImportedRecord {
        foreach ($this->records as $record) {
            if ($record->sourceRow === $sourceRow) {
                return $record;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'sheet_name' => $this->sheetName,
            'header_row' => $this->headerRow,
            'data_start_row' =>
                $this->dataStartRow,
            'last_row' => $this->lastRow,
            'last_column' => $this->lastColumn,

            'profile' => $this->profile === null
                ? null
                : [
                    'id' => $this->profile->id,
                    'name' => $this->profile->name,
                    'slug' => $this->profile->slug,
                    'default_regime' =>
                        $this->profile
                            ->default_regime,
                    'default_phone' =>
                        $this->profile
                            ->default_phone,
                ],

            'mapping' =>
                $this->mapping->toArray(),

            'headers' => $this->headers,

            'statistics' => [
                ...$this->statistics,

                'records_count' =>
                    $this->recordsCount(),

                'valid_records_count' =>
                    $this->validRecordsCount(),

                'invalid_records_count' =>
                    $this->invalidRecordsCount(),

                'warnings_count' =>
                    $this->warningsCount(),
            ],

            'metadata' => $this->metadata,

            'warnings' => $this->warnings,

            'records' => array_map(
                static fn (
                    ImportedRecord $record
                ): array =>
                    $record->toArray(),
                $this->records
            ),
        ];
    }
}