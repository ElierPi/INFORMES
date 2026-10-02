<?php

namespace App\Services\Gestantes;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class GestantesLogsParser
{
    /**
     * Encabezados esperados en el archivo de errores SIGIRES.
     */
    private const EXPECTED_HEADERS = [
        'fila',
        'columna',
        'tipo error',
        'valor anterior',
        'valor nuevo',
        'descripcion',
    ];

    /**
     * Lee y normaliza el archivo logs.xls generado por SIGIRES.
     */
    public function parse(string $filePath): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException(
                'No se encontró el archivo de errores SIGIRES.'
            );
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $rows = $worksheet->toArray(
                null,
                true,
                true,
                false
            );

            if ($rows === []) {
                throw new RuntimeException(
                    'El archivo de errores está vacío.'
                );
            }

            $headerRowIndex = $this->findHeaderRow($rows);

            if ($headerRowIndex === null) {
                throw new RuntimeException(
                    'No se encontraron los encabezados de errores '
                    . 'esperados en el archivo SIGIRES.'
                );
            }

            $headerMap = $this->buildHeaderMap(
                $rows[$headerRowIndex]
            );

            $errors = [];

            for (
                $index = $headerRowIndex + 1;
                $index < count($rows);
                $index++
            ) {
                $row = $rows[$index];

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $error = $this->buildError(
                    row: $row,
                    headerMap: $headerMap,
                    sourceRow: $index + 1
                );

                if ($error === null) {
                    continue;
                }

                $errors[] = $error;
            }

            if ($errors === []) {
                throw new RuntimeException(
                    'El archivo fue leído, pero no contiene errores procesables.'
                );
            }

            return [
                'errors' => $errors,
                'total' => count($errors),
                'automatic' => count(array_filter(
                    $errors,
                    fn (array $error): bool =>
                        $error['classification'] === 'automatic'
                )),
                'manual' => count(array_filter(
                    $errors,
                    fn (array $error): bool =>
                        $error['classification'] === 'manual'
                )),
                'metadata' => $this->extractMetadata(
                    $rows,
                    $headerRowIndex
                ),
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible interpretar el archivo de errores: '
                . $exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * Encuentra la fila donde comienzan los encabezados de los errores.
     */
    private function findHeaderRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $normalized = array_map(
                fn (mixed $value): string =>
                    $this->normalizeText($value),
                $row
            );

            $matches = 0;

            foreach (self::EXPECTED_HEADERS as $header) {
                if (in_array($header, $normalized, true)) {
                    $matches++;
                }
            }

            /*
             * Consideramos encabezado cuando aparecen al menos
             * cuatro columnas conocidas.
             */
            if ($matches >= 4) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Relaciona cada encabezado con su posición en la hoja.
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $value) {
            $normalized = $this->normalizeText($value);

            if ($normalized === '') {
                continue;
            }

            $canonical = $this->resolveCanonicalHeader(
                $normalized
            );

            if ($canonical !== null) {
                $map[$canonical] = $index;
            }
        }

        return $map;
    }

    private function resolveCanonicalHeader(
        string $header
    ): ?string {
        return match (true) {
            $header === 'fila' => 'row',
            $header === 'columna' => 'column',
            str_contains($header, 'tipo error') => 'error_type',
            str_contains($header, 'valor anterior') => 'old_value',
            str_contains($header, 'valor nuevo') => 'new_value',
            str_contains($header, 'descripcion') => 'description',
            default => null,
        };
    }

    /**
     * Construye un error normalizado.
     */
    private function buildError(
        array $row,
        array $headerMap,
        int $sourceRow
    ): ?array {
        $description = $this->valueFromColumn(
            $row,
            $headerMap,
            'description'
        );

        $errorType = $this->valueFromColumn(
            $row,
            $headerMap,
            'error_type'
        );

        /*
         * Algunas filas pueden contener información auxiliar,
         * pero no representan un error real.
         */
        if ($description === '' && $errorType === '') {
            return null;
        }

        $reportRow = $this->normalizeInteger(
            $this->valueFromColumn(
                $row,
                $headerMap,
                'row'
            )
        );

        $reportColumn = $this->normalizeInteger(
            $this->valueFromColumn(
                $row,
                $headerMap,
                'column'
            )
        );

        $classification = $this->classifyError(
            description: $description,
            errorType: $errorType
        );

        return [
            'source_row' => $sourceRow,
            'row' => $reportRow,
            'column' => $reportColumn,
            'error_type' => $errorType,
            'old_value' => $this->valueFromColumn(
                $row,
                $headerMap,
                'old_value'
            ),
            'suggested_value' => $this->valueFromColumn(
                $row,
                $headerMap,
                'new_value'
            ),
            'description' => $description,
            'classification' => $classification,
            'rule' => $this->resolveRuleName(
                $description
            ),
            'status' => 'pending',
        ];
    }

    /**
     * Clasificación inicial.
     *
     * Luego esta decisión será reemplazada por el motor de reglas.
     */
    private function classifyError(
        string $description,
        string $errorType
    ): string {
        $text = $this->normalizeText(
            $description . ' ' . $errorType
        );

        $automaticPatterns = [
            'fecha de suministro de anticonceptivo',
            'suministro de metodo anticonceptivo',
            'fecha de terminacion de la gestacion',
            'tipo de terminacion',
            'direccion de residencia',
            'fecha probable de parto',
            'el pais 172 no existe',
            'indice de pulsatilidad',
            'total de registros',
            'consecutivo',
        ];

        foreach ($automaticPatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return 'automatic';
            }
        }

        return 'manual';
    }

    private function resolveRuleName(
        string $description
    ): ?string {
        $text = $this->normalizeText($description);

        return match (true) {
            str_contains(
                $text,
                'fecha de suministro de anticonceptivo'
            ) => 'AnticonceptivoRule',

            str_contains(
                $text,
                'fecha de terminacion de la gestacion'
            ) => 'TerminacionGestacionRule',

            str_contains(
                $text,
                'tipo de terminacion'
            ) => 'TipoTerminacionRule',

            str_contains(
                $text,
                'direccion de residencia'
            ) => 'DireccionRule',

            str_contains(
                $text,
                'total de registros'
            ) => 'ControlTotalsRule',

            str_contains(
                $text,
                'fecha probable de parto'
            ) => 'FechaProbablePartoRule',

            str_contains(
                $text,
                'el pais 172 no existe'
            ) => 'PaisColombiaRule',

            str_contains(
                $text,
                'suministro de metodo anticonceptivo'
            ) => 'SuministroAnticonceptivoRule',

            str_contains(
                $text,
                'indice de pulsatilidad'
            ) => 'IndicePulsatilidadRule',

            str_contains(
                $text,
                'consecutivo'
            ) => 'ConsecutiveRule',

            default => null,
        };
    }

    /**
     * Obtiene información general ubicada antes de la tabla.
     */
    private function extractMetadata(
        array $rows,
        int $headerRowIndex
    ): array {
        $metadata = [];

        for ($index = 0; $index < $headerRowIndex; $index++) {
            $row = $rows[$index];

            $label = $this->normalizeText(
                $row[0] ?? ''
            );

            if ($label === '') {
                continue;
            }

            $value = '';

            foreach (array_slice($row, 1) as $candidate) {
                $candidate = trim((string) ($candidate ?? ''));

                if ($candidate !== '') {
                    $value = $candidate;
                    break;
                }
            }

            if ($value === '') {
                continue;
            }

            $key = match (true) {
                str_contains($label, 'radicado') =>
                    'radicado',

                str_contains($label, 'archivo proceso') =>
                    'processed_file',

                str_contains($label, 'fecha proceso') =>
                    'processed_at',

                str_contains($label, 'cantidad filas') =>
                    'total_rows',

                str_contains($label, 'errores encontrados') =>
                    'reported_errors',

                str_contains($label, 'usuario reporta') =>
                    'reported_by',

                default => null,
            };

            if ($key !== null) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    private function valueFromColumn(
        array $row,
        array $headerMap,
        string $key
    ): string {
        if (! array_key_exists($key, $headerMap)) {
            return '';
        }

        $index = $headerMap[$key];

        return trim(
            (string) ($row[$index] ?? '')
        );
    }

    private function normalizeInteger(
        mixed $value
    ): ?int {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) ($value ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeText(
        mixed $value
    ): string {
        $value = mb_strtolower(
            trim((string) ($value ?? ''))
        );

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return trim($value);
    }
}