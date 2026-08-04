<?php

namespace App\Services\Importing;

use App\Data\Importing\ImportedDataset;
use App\Models\ImportHistory;

class ImportPipeline
{
    public function __construct(
        private readonly ExcelAnalyzer $excelAnalyzer,
        private readonly ImportProfileResolver $profileResolver,
        private readonly ProfileDefaultsApplier $defaultsApplier,
        private readonly ImportHistoryService $historyService
    ) {
    }

    /**
     * Procesa un archivo Excel y devuelve un dataset tipado
     * junto con el historial de importación.
     *
     * @return array{
     *     dataset: ImportedDataset,
     *     history: ImportHistory
     * }
     */
    public function process(
        string $path,
        string $originalFilename,
        string $reportType,
        array $fieldDefinitions
    ): array {
        /*
        |--------------------------------------------------------------------------
        | 1. Analizar el archivo Excel
        |--------------------------------------------------------------------------
        */

        $analysis = $this->excelAnalyzer->analyze(
            path: $path,
            fieldDefinitions: $fieldDefinitions
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Detectar automáticamente el perfil de la IPS
        |--------------------------------------------------------------------------
        */

        $profile = $this->profileResolver->resolve(
            $analysis['records'] ?? []
        );

        /*
        |--------------------------------------------------------------------------
        | 3. Aplicar valores predeterminados del perfil
        |--------------------------------------------------------------------------
        |
        | Ejemplo:
        | - régimen predeterminado
        | - teléfono predeterminado
        | - municipio predeterminado
        |
        */

        $analysis['records'] = $this->defaultsApplier->apply(
            records: $analysis['records'] ?? [],
            profile: $profile
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Recalcular los campos que realmente siguen faltando
        |--------------------------------------------------------------------------
        |
        | Un campo puede no existir como columna del Excel, pero puede haber sido
        | completado mediante el perfil de la IPS.
        |
        */

        $analysis['missing_fields'] =
            $this->calculateMissingFields(
                analysis: $analysis
            );

        /*
        |--------------------------------------------------------------------------
        | 5. Convertir el análisis a un Dataset tipado
        |--------------------------------------------------------------------------
        */

        $dataset = ImportedDataset::fromAnalysis(
            analysis: $analysis,
            profile: $profile
        );

        /*
        |--------------------------------------------------------------------------
        | 6. Guardar el historial de importación
        |--------------------------------------------------------------------------
        */

        $history = $this->historyService->create(
            reportType: $reportType,
            originalFilename: $originalFilename,
            originalPath: $path,
            analysis: $analysis,
            profile: $profile
        );

        /*
        |--------------------------------------------------------------------------
        | 7. Devolver el resultado
        |--------------------------------------------------------------------------
        */

        return [
            'dataset' => $dataset,
            'history' => $history,
        ];
    }

    /**
     * Recalcula los campos faltantes después de aplicar los valores
     * predeterminados del perfil.
     *
     * @return array<int, string>
     */
    private function calculateMissingFields(
        array $analysis
    ): array {
        $missingFields =
            $analysis['missing_fields'] ?? [];

        $records =
            $analysis['records'] ?? [];

        if ($missingFields === []) {
            return [];
        }

        if ($records === []) {
            return array_values($missingFields);
        }

        return array_values(
            array_filter(
                $missingFields,
                static function (
                    string $field
                ) use ($records): bool {
                    /*
                    |--------------------------------------------------------------------------
                    | El campo continúa faltando si al menos un registro está vacío
                    |--------------------------------------------------------------------------
                    */

                    foreach ($records as $record) {
                        $value =
                            $record['data'][$field]
                            ?? null;

                        if (self::isEmptyValue($value)) {
                            return true;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | El campo ya fue completado en todos los registros
                    |--------------------------------------------------------------------------
                    */

                    return false;
                }
            )
        );
    }

    /**
     * Determina si un valor debe considerarse vacío.
     */
    private static function isEmptyValue(
        mixed $value
    ): bool {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }
}