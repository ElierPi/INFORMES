<?php

namespace App\Services\Sigires\Cronicos;

use Illuminate\Support\Str;
use RuntimeException;

class SigiresCronicosPreparationService
{
    public function __construct(
        private readonly SigiresClinicalHistoryZipReader $historyZipReader,
        private readonly SigiresCronicosBaseReader $baseReader,
        private readonly SigiresClinicalHistoryParser $historyParser,
        private readonly SigiresPrecursorasRecordBuilder $recordBuilder,
        private readonly SigiresCronicosValidator $validator,
        private readonly SigiresCronicosReviewExporter $reviewExporter,
        private readonly SigiresCronicosTxtGenerator $txtGenerator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prepare(
        string $basePath,
        string $historiesZipPath,
        string $cutoffDate,
        string $eapbCode,
        string $procedure = 'PRECURSORAS'
    ): array {
        $historyResult = $this->historyZipReader->read($historiesZipPath);
        $rawHistories = $historyResult['histories'];

        $documentNumbers = array_values(array_unique(array_map(
            fn (array $history): string =>
                (string) ($history['document_number'] ?? ''),
            $rawHistories
        )));

        $baseResult = $this->baseReader->findByDocuments(
            $basePath,
            $documentNumbers
        );
        $baseRecords = $baseResult['records'];

        $patients = [];
        $warnings = $historyResult['warnings'] ?? [];
        $unmatched = [];

        foreach ($rawHistories as $rawHistory) {
            $parsedHistory = $this->historyParser->parse(
                $rawHistory,
                $cutoffDate
            );
            $documentNumber = (string) (
                $parsedHistory['document_number']
                ?? $rawHistory['document_number']
                ?? ''
            );
            $base = $baseRecords[$documentNumber] ?? [];

            if ($base === []) {
                $unmatched[] = $documentNumber;
                $warnings[] =
                    "El documento {$documentNumber} no fue encontrado en la base regional.";
            }

            $build = $this->recordBuilder->build(
                $base,
                $parsedHistory,
                $cutoffDate,
                $eapbCode
            );
            $validationIssues = $this->validator->validate(
                $build['values']
            );

            if ($validationIssues !== []) {
                $build['ready'] = false;
            }

            $patients[] = [
                'base' => $base,
                'history' => $parsedHistory,
                'build' => $build,
                'validation_issues' => $validationIssues,
            ];
        }

        foreach ($baseResult['duplicates'] ?? [] as $documentNumber) {
            $warnings[] =
                "El documento {$documentNumber} aparece más de una vez en la base regional.";
        }

        $providerCodes = array_values(array_unique(array_filter(array_map(
            fn (array $patient): string =>
                preg_replace(
                    '/\D+/',
                    '',
                    (string) ($patient['base']['provider_code'] ?? '')
                ) ?? '',
            $patients
        ))));

        $providerCode = count($providerCodes) === 1
            ? $providerCodes[0]
            : null;

        if ($providerCode === null) {
            $warnings[] =
                'No fue posible determinar un único código de habilitación para todos los pacientes.';
        }

        $readyPatients = array_values(array_filter(
            $patients,
            fn (array $patient): bool =>
                (bool) ($patient['build']['ready'] ?? false)
        ));
        $pendingFieldsTotal = array_sum(array_map(
            fn (array $patient): int =>
                count($patient['build']['pending'] ?? []),
            $patients
        ));

        $result = [
            'summary' => [
                'cutoff_date' => $cutoffDate,
                'procedure' => mb_strtoupper($procedure),
                'provider_code' => $providerCode,
                'eapb_code' => mb_strtoupper(trim($eapbCode)),
                'histories_total' => count($rawHistories),
                'matched_total' => count($rawHistories) - count($unmatched),
                'unmatched_total' => count($unmatched),
                'ready_total' => count($readyPatients),
                'pending_total' => count($patients) - count($readyPatients),
                'pending_fields_total' => $pendingFieldsTotal,
            ],
            'patients' => $patients,
            'warnings' => array_values(array_unique($warnings)),
        ];

        $outputDirectory = storage_path(
            'app/private/sigires-cronicos/generated/'.
            now()->format('Ymd_His').'_'.Str::lower(Str::random(6))
        );

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de salida del módulo SIGIRES.'
            );
        }

        $reviewPath = $outputDirectory.
            DIRECTORY_SEPARATOR.
            'MATRIZ_REVISION_SIGIRES_PRECURSORAS.xlsx';

        $result['review_path'] = $this->reviewExporter->export(
            $result,
            $reviewPath
        );
        $result['txt_path'] = null;
        $result['zip_path'] = null;

        if (
            count($readyPatients) === count($patients)
            && count($patients) > 0
            && is_string($providerCode)
        ) {
            $generated = $this->txtGenerator->generate(
                $patients,
                $outputDirectory,
                $providerCode,
                $cutoffDate,
                $procedure
            );

            $result['txt_path'] = $generated['txt_path'];
            $result['zip_path'] = $generated['zip_path'];
            $result['generated_file_name'] = $generated['file_name'];
        }

        return $result;
    }
}
