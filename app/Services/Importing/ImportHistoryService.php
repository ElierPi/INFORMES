<?php

namespace App\Services\Importing;

use App\Models\ImportHistory;
use App\Models\ImportProfile;

class ImportHistoryService
{
    public function create(
        string $reportType,
        string $originalFilename,
        string $originalPath,
        array $analysis,
        ?ImportProfile $profile = null
    ): ImportHistory {
        return ImportHistory::create([
            'report_type' => $reportType,

            'import_profile_id' => $profile?->id,

            'original_filename' => $originalFilename,

            'original_path' => $originalPath,

            'file_hash' => is_file($originalPath)
                ? hash_file('sha256', $originalPath)
                : null,

            'sheet_name' =>
                $analysis['sheet_name'] ?? null,

            'header_row' =>
                $analysis['header_row'] ?? null,

            'data_start_row' =>
                $analysis['data_start_row'] ?? null,

            'records_count' =>
                $analysis['records_count'] ?? 0,

            'valid_records_count' => 0,

            'invalid_records_count' => 0,

            'warnings_count' => count(
                $analysis['missing_fields'] ?? []
            ),

            'status' => $this->determineStatus(
                $analysis
            ),

            'mapping' =>
                $analysis['mapping'] ?? [],

            'missing_fields' =>
                $analysis['missing_fields'] ?? [],

            'warnings' => [],

            'statistics' => [],

            'metadata' => [
                'matched_fields' =>
                    $analysis['matched_fields'] ?? 0,

                'mapping_score' =>
                    $analysis['score'] ?? 0,
            ],

            'created_by' => auth()->id(),
        ]);
    }

    private function determineStatus(
        array $analysis
    ): string {
        $missingFields =
            $analysis['missing_fields'] ?? [];

        if ($missingFields !== []) {
            return 'mapping_required';
        }

        return 'analyzed';
    }
}