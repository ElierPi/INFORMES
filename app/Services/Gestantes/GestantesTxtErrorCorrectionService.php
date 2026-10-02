<?php

namespace App\Services\Gestantes;

use RuntimeException;
use Throwable;
use ZipArchive;

class GestantesTxtErrorCorrectionService
{
    private const SENTINEL_DATE = '1845-01-01';

    /**
     * Corrige directamente el TXT contenido en el ZIP que fue enviado a SIGIRES.
     *
     * Ventaja: los números de fila y variable del LOG corresponden exactamente
     * a las líneas/campos que estamos modificando.
     */
    public function correctZip(
        string $zipPath,
        string $outputDirectory,
        array $errors
    ): array {
        if (! is_file($zipPath)) {
            throw new RuntimeException('No se encontró el ZIP original.');
        }

        if ($errors === []) {
            throw new RuntimeException(
                'No existen errores analizados para corregir.'
            );
        }

        try {
            if (! is_dir($outputDirectory)) {
                mkdir($outputDirectory, 0775, true);
            }

            $extracted = $this->extractTxtFromZip(
                zipPath: $zipPath,
                outputDirectory: $outputDirectory
            );

            $txtPath = $extracted['txt_path'];
            $txtName = $extracted['txt_name'];

            $raw = file_get_contents($txtPath);

            if ($raw === false) {
                throw new RuntimeException(
                    'No fue posible leer el TXT contenido en el ZIP.'
                );
            }

            /*
             * Los archivos SIGIRES se generan en Windows-1252/ANSI.
             * Para trabajar internamente convertimos a UTF-8.
             */
            $utf8 = mb_convert_encoding(
                $raw,
                'UTF-8',
                'Windows-1252'
            );

            $lines = preg_split(
                '/\r\n|\n|\r/',
                $utf8
            );

            if (! is_array($lines)) {
                throw new RuntimeException(
                    'No fue posible separar las líneas del TXT.'
                );
            }

            /*
             * Quitar únicamente líneas vacías finales accidentales.
             */
            while (
                $lines !== []
                && trim((string) end($lines)) === ''
            ) {
                array_pop($lines);
            }

            $changes = [];
            $manual = [];

            foreach ($errors as $error) {
                $result = $this->processError(
                    lines: $lines,
                    error: $error
                );

                if (($result['status'] ?? '') === 'corrected') {
                    foreach ($result['changes'] ?? [] as $change) {
                        $changes[] = $change;
                    }

                    continue;
                }

                $manual[] = $result;
            }

            /*
             * Volvemos a escribir en ANSI Windows-1252 y CRLF.
             */
            $correctedUtf8 = implode("\r\n", $lines);
            $correctedAnsi = mb_convert_encoding(
                $correctedUtf8,
                'Windows-1252',
                'UTF-8'
            );

            $baseName = pathinfo(
                $txtName,
                PATHINFO_FILENAME
            );

            $correctedTxtName = $txtName;

            $correctedTxtPath =
                rtrim($outputDirectory, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR
                .$correctedTxtName;

            if (
                file_put_contents(
                    $correctedTxtPath,
                    $correctedAnsi
                ) === false
            ) {
                throw new RuntimeException(
                    'No fue posible guardar el TXT corregido.'
                );
            }

            $correctedZipName =
                $baseName.'.zip';

            $correctedZipPath =
                rtrim($outputDirectory, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR
                .$correctedZipName;

            $zip = new ZipArchive();

            if (
                $zip->open(
                    $correctedZipPath,
                    ZipArchive::CREATE | ZipArchive::OVERWRITE
                ) !== true
            ) {
                throw new RuntimeException(
                    'No fue posible crear el ZIP corregido.'
                );
            }

            $zip->addFile(
                $correctedTxtPath,
                $correctedTxtName
            );

            $zip->close();

            return [
                'txt_path' => $correctedTxtPath,
                'zip_path' => $correctedZipPath,
                'txt_name' => $correctedTxtName,
                'zip_name' => $correctedZipName,
                'changes' => $changes,
                'manual' => $manual,
                'total' => count($errors),
                'corrected' => count($changes),
                'pending' => count($manual),
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible corregir el ZIP: '
                .$exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function extractTxtFromZip(
        string $zipPath,
        string $outputDirectory
    ): array {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(
                'No fue posible abrir el ZIP.'
            );
        }

        try {
            $txtEntries = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (
                    ! is_string($name)
                    || str_ends_with($name, '/')
                ) {
                    continue;
                }

                if (
                    strtolower(
                        pathinfo($name, PATHINFO_EXTENSION)
                    ) === 'txt'
                ) {
                    $txtEntries[] = [
                        'index' => $index,
                        'name' => $name,
                    ];
                }
            }

            if ($txtEntries === []) {
                throw new RuntimeException(
                    'El ZIP no contiene ningún archivo TXT.'
                );
            }

            /*
             * El reporte de Gestantes MSPS debe contener un solo TXT.
             */
            $entry = $txtEntries[0];

            $content = $zip->getFromIndex(
                $entry['index']
            );

            if ($content === false) {
                throw new RuntimeException(
                    'No fue posible extraer el TXT del ZIP.'
                );
            }

            $txtName = basename(
                $entry['name']
            );

            $safeName = preg_replace(
                '/[^A-Za-z0-9_.-]+/',
                '_',
                $txtName
            ) ?: 'gestantes.txt';

            $txtPath =
                rtrim($outputDirectory, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR
                .$safeName;

            if (
                file_put_contents(
                    $txtPath,
                    $content
                ) === false
            ) {
                throw new RuntimeException(
                    'No fue posible guardar temporalmente el TXT.'
                );
            }

            return [
                'txt_path' => $txtPath,
                'txt_name' => $safeName,
            ];
        } finally {
            $zip->close();
        }
    }

    private function processError(
        array &$lines,
        array $error
    ): array {
        $reportedRow = $this->nullableInteger(
            $error['row'] ?? null
        );

        if (
            $reportedRow === null
            || $reportedRow < 1
            || ! array_key_exists(
                $reportedRow - 1,
                $lines
            )
        ) {
            return $this->manualResult(
                error: $error,
                reason: 'La fila reportada por SIGIRES no existe en el TXT.'
            );
        }

        $lineIndex = $reportedRow - 1;
        $fields = explode(
            '|',
            (string) $lines[$lineIndex]
        );

        if ($fields === []) {
            return $this->manualResult(
                error: $error,
                reason: 'La línea del TXT está vacía.'
            );
        }

        $recordType = $this->nullableInteger(
            trim((string) ($fields[0] ?? ''))
        );

        if ($recordType === null) {
            return $this->manualResult(
                error: $error,
                reason: 'No fue posible identificar el tipo de registro.'
            );
        }

        $description = $this->normalizeText(
            (string) ($error['description'] ?? '')
        );

        /*
         * IMPORTANTE:
         * Las reglas siguientes usan la posición OFICIAL del campo
         * según el tipo de registro del anexo técnico, no dependen de
         * una posible numeración desplazada del LOG.
         */

        $result = null;

        // REGISTRO TIPO 2 · IDENTIFICACIÓN
        if (
            $recordType === 2
            && str_contains($description, 'fecha probable de parto')
        ) {
            $result = $this->correctProbableDeliveryDate(
                fields: $fields,
                fieldIndex: 16,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow
            );
        } elseif (
            $recordType === 2
            && (
                str_contains($description, 'el pais 172 no existe')
                || str_contains($description, 'pais 172')
            )
        ) {
            $result = $this->correctCountry172(
                fields: $fields,
                fieldIndex: 2,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow
            );
        } elseif (
            $recordType === 2
            && str_contains($description, 'direccion de residencia')
        ) {
            $result = $this->correctAddress(
                fields: $fields,
                fieldIndex: 17,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow,
                reportedVariable: 17,
                fieldConfig: [
                    'key' => 'direccion_residencia',
                    'header' => 'Dirección de residencia de la gestante',
                ]
            );
        }

        // REGISTRO TIPO 3 · ATENCIONES
        elseif (
            $recordType === 3
            && str_contains(
                $description,
                'suministro de metodo anticonceptivo'
            )
        ) {
            $result = $this->correctContraceptiveSupplySemantic(
                fields: $fields,
                fieldIndex: 14,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow
            );
        } elseif (
            $recordType === 3
            && str_contains(
                $description,
                'tipo de terminacion de la gestacion'
            )
        ) {
            $result = $this->correctSingleValue(
                fields: $fields,
                fieldIndex: 17,
                newValue: '0',
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow,
                reportedVariable: 17,
                fieldConfig: [
                    'key' => 'tipo_terminacion_gestacion',
                    'header' => 'Tipo de terminación de la gestación',
                ],
                reason:
                    'Se asignó 0 (No aplica) al tipo de terminación.'
            );
        } elseif (
            $recordType === 3
            && str_contains(
                $description,
                'indice de pulsatilidad'
            )
        ) {
            $result = $this->correctPulsatilityIndex(
                fields: $fields,
                fieldIndex: 22,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow
            );
        } elseif (
            $recordType === 3
            && str_contains(
                $description,
                'fecha de suministro de anticonceptivo'
            )
        ) {
            $result = $this->correctSingleValue(
                fields: $fields,
                fieldIndex: 13,
                newValue: self::SENTINEL_DATE,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow,
                reportedVariable: 13,
                fieldConfig: [
                    'key' => 'fecha_anticonceptivo',
                    'header' => 'Fecha de suministro de anticonceptivo post evento obstétrico',
                ],
                reason:
                    'Se asignó 1845-01-01 como fecha centinela de anticoncepción.'
            );
        } elseif (
            $recordType === 3
            && str_contains(
                $description,
                'fecha de terminacion de la gestacion'
            )
        ) {
            $result = $this->correctSingleValue(
                fields: $fields,
                fieldIndex: 16,
                newValue: self::SENTINEL_DATE,
                error: $error,
                recordType: $recordType,
                reportedRow: $reportedRow,
                reportedVariable: 16,
                fieldConfig: [
                    'key' => 'fecha_terminacion_gestacion',
                    'header' => 'Fecha de terminación de la gestación',
                ],
                reason:
                    'Se asignó 1845-01-01 como fecha centinela de terminación.'
            );
        }

        if (! is_array($result)) {
            return $this->manualResult(
                error: $error,
                reason:
                    'El error todavía no tiene una regla automática implementada.'
            );
        }

        if (($result['status'] ?? '') === 'corrected') {
            foreach ($result['assignments'] ?? [] as $assignment) {
                $index = (int) $assignment['index'];
                $fields[$index] = (string) $assignment['value'];
            }

            $lines[$lineIndex] = implode(
                '|',
                $fields
            );
        }

        unset($result['assignments']);

        return $result;
    }

    private function correctProbableDeliveryDate(
        array $fields,
        int $fieldIndex,
        array $error,
        int $recordType,
        int $reportedRow
    ): array {
        $oldValue = trim(
            (string) ($fields[$fieldIndex] ?? '')
        );

        $normalized = $this->normalizeDateValue(
            $oldValue
        );

        if ($normalized === null) {
            /*
             * Si el LOG trae el valor nuevo sugerido, también intentamos
             * normalizarlo. Nunca inventamos una fecha probable de parto.
             */
            $suggested = trim(
                (string) ($error['suggested_value'] ?? '')
            );

            $normalized = $this->normalizeDateValue(
                $suggested
            );
        }

        if ($normalized === null) {
            return $this->manualResult(
                error: $error,
                reason:
                    'La fecha probable de parto no puede inferirse automáticamente porque el valor no representa una fecha válida.'
            );
        }

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $fieldIndex,
                    'value' => $normalized,
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: 16,
                    fieldConfig: [
                        'key' => 'fecha_probable_parto',
                        'header' => 'Fecha probable de parto',
                    ],
                    oldValue: $oldValue,
                    newValue: $normalized,
                    reason:
                        'Se normalizó la fecha probable de parto a AAAA-MM-DD.'
                ),
            ],
        ];
    }

    private function correctCountry172(
        array $fields,
        int $fieldIndex,
        array $error,
        int $recordType,
        int $reportedRow
    ): array {
        $oldValue = trim(
            (string) ($fields[$fieldIndex] ?? '')
        );

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $fieldIndex,
                    'value' => '170',
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: 2,
                    fieldConfig: [
                        'key' => 'pais_nacionalidad',
                        'header' => 'País de la nacionalidad',
                    ],
                    oldValue: $oldValue,
                    newValue: '170',
                    reason:
                        'Se corrigió 172 a 170 para Colombia (código numérico ISO 3166-1).'
                ),
            ],
        ];
    }

    private function correctContraceptiveSupplySemantic(
        array $fields,
        int $fieldIndex,
        array $error,
        int $recordType,
        int $reportedRow
    ): array {
        $oldValue = trim(
            (string) ($fields[$fieldIndex] ?? '')
        );

        $normalized = $this->normalizeIntegerValue(
            $oldValue
        );

        $allowed = [
            '0', '1', '2', '3', '4', '5', '6', '7',
            '8', '9', '10', '13', '14', '15',
        ];

        /*
         * Si el valor no puede normalizarse o queda fuera del catálogo,
         * se usa 0 = No aplica, que está permitido por el anexo.
         */
        $newValue = (
            $normalized !== null
            && in_array($normalized, $allowed, true)
        )
            ? $normalized
            : '0';

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $fieldIndex,
                    'value' => $newValue,
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: 14,
                    fieldConfig: [
                        'key' => 'suministro_anticonceptivo',
                        'header' => 'Suministro de método anticonceptivo post evento obstétrico',
                    ],
                    oldValue: $oldValue,
                    newValue: $newValue,
                    reason:
                        'Se normalizó al catálogo permitido; cuando no fue posible interpretar el valor se asignó 0 (No aplica).'
                ),
            ],
        ];
    }

    private function correctPulsatilityIndex(
        array $fields,
        int $fieldIndex,
        array $error,
        int $recordType,
        int $reportedRow
    ): array {
        $oldValue = trim(
            (string) ($fields[$fieldIndex] ?? '')
        );

        /*
         * Este campo NO es obligatorio. Si viene vacío, debe permanecer vacío.
         */
        if ($oldValue === '') {
            return [
                'status' => 'corrected',
                'assignments' => [
                    [
                        'index' => $fieldIndex,
                        'value' => '',
                    ],
                ],
                'changes' => [
                    $this->changeInfo(
                        error: $error,
                        recordType: $recordType,
                        reportedRow: $reportedRow,
                        reportedVariable: 22,
                        fieldConfig: [
                            'key' => 'indice_pulsatilidad',
                            'header' => 'Índice de pulsatilidad de arterias uterinas',
                        ],
                        oldValue: '',
                        newValue: '',
                        reason:
                            'El campo es opcional y se conservó vacío.'
                    ),
                ],
            ];
        }

        $numeric = $this->normalizeDecimalValue(
            $oldValue
        );

        if ($numeric === null) {
            /*
             * Como es opcional, un valor inválido se limpia.
             */
            $newValue = '';
        } else {
            /*
             * 1 entero y máximo 2 decimales con punto.
             */
            $newValue = number_format(
                $numeric,
                2,
                '.',
                ''
            );
        }

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $fieldIndex,
                    'value' => $newValue,
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: 22,
                    fieldConfig: [
                        'key' => 'indice_pulsatilidad',
                        'header' => 'Índice de pulsatilidad de arterias uterinas',
                    ],
                    oldValue: $oldValue,
                    newValue: $newValue,
                    reason:
                        'Se normalizó con punto y dos decimales; si el dato no era interpretable se dejó vacío porque el campo no es obligatorio.'
                ),
            ],
        ];
    }

    private function normalizeDateValue(
        string $value
    ): ?string {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // DD/MM/AAAA o DD-MM-AAAA, admitiendo día/mes de 1 o 2 dígitos.
        if (
            preg_match(
                '/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/',
                $value,
                $matches
            )
        ) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }

            return null;
        }

        // AAAA/MM/DD o AAAA-MM-DD, admitiendo mes/día de 1 o 2 dígitos.
        if (
            preg_match(
                '/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/',
                $value,
                $matches
            )
        ) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }

            return null;
        }

        // AAAAMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }
        }

        return null;
    }

    private function normalizeIntegerValue(
        string $value
    ): ?string {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace(
            '/[,.]+$/',
            '',
            $value
        ) ?? $value;

        if (
            str_contains($value, ',')
            && ! str_contains($value, '.')
        ) {
            $value = str_replace(
                ',',
                '.',
                $value
            );
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (floor($number) !== $number) {
            return null;
        }

        return (string) ((int) $number);
    }

    private function normalizeDecimalValue(
        string $value
    ): ?float {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace(
            '/[,.]+$/',
            '',
            $value
        ) ?? $value;

        if (
            str_contains($value, ',')
            && ! str_contains($value, '.')
        ) {
            $value = str_replace(
                ',',
                '.',
                $value
            );
        }

        return is_numeric($value)
            ? (float) $value
            : null;
    }

    private function normalizeText(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value)
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

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;
    }

    private function correctAddress(
        array $fields,
        int $fieldIndex,
        array $error,
        int $recordType,
        int $reportedRow,
        int $reportedVariable,
        array $fieldConfig
    ): array {
        $oldValue = trim(
            (string) ($fields[$fieldIndex] ?? '')
        );

        $suggestedValue = trim(
            (string) ($error['suggested_value'] ?? '')
        );

        $source = $suggestedValue !== ''
            ? $suggestedValue
            : $oldValue;

        $newValue = $this->normalizeAddress(
            $source
        );

        if ($newValue === '') {
            return $this->manualResult(
                error: $error,
                reason:
                    'La dirección está vacía y no puede corregirse automáticamente.'
            );
        }

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $fieldIndex,
                    'value' => $newValue,
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: $reportedVariable,
                    fieldConfig: $fieldConfig,
                    oldValue: $oldValue,
                    newValue: $newValue,
                    reason:
                        'Se normalizó la nomenclatura de la dirección.'
                ),
            ],
        ];
    }

    private function correctContraceptiveSupply(
        array $fields,
        array $error,
        int $recordType,
        int $reportedRow,
        int $reportedVariable,
        array $fieldConfig
    ): array {
        /*
         * Variable 15 -> índice 14.
         * Variable 14 relacionada -> índice 13.
         */
        $supplyIndex = 14;
        $dateIndex = 13;

        $oldSupply = (string) (
            $fields[$supplyIndex] ?? ''
        );

        $oldDate = (string) (
            $fields[$dateIndex] ?? ''
        );

        $dateConfig = config(
            'gestantes_sigires.fields.3.14'
        ) ?? [
            'key' => 'fecha_anticonceptivo',
            'header' => 'Fecha de suministro de anticonceptivo post evento obstétrico',
        ];

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $supplyIndex,
                    'value' => '0',
                ],
                [
                    'index' => $dateIndex,
                    'value' => self::SENTINEL_DATE,
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: $reportedVariable,
                    fieldConfig: $fieldConfig,
                    oldValue: $oldSupply,
                    newValue: '0',
                    reason:
                        'Se asignó 0 al suministro de anticonceptivo.'
                ),
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: 14,
                    fieldConfig: $dateConfig,
                    oldValue: $oldDate,
                    newValue: self::SENTINEL_DATE,
                    reason:
                        'Se asignó 1845-01-01 a la fecha relacionada.'
                ),
            ],
        ];
    }

    private function correctSingleValue(
        array $fields,
        int $fieldIndex,
        string $newValue,
        array $error,
        int $recordType,
        int $reportedRow,
        int $reportedVariable,
        array $fieldConfig,
        string $reason
    ): array {
        $oldValue = (string) (
            $fields[$fieldIndex] ?? ''
        );

        return [
            'status' => 'corrected',
            'assignments' => [
                [
                    'index' => $fieldIndex,
                    'value' => $newValue,
                ],
            ],
            'changes' => [
                $this->changeInfo(
                    error: $error,
                    recordType: $recordType,
                    reportedRow: $reportedRow,
                    reportedVariable: $reportedVariable,
                    fieldConfig: $fieldConfig,
                    oldValue: $oldValue,
                    newValue: $newValue,
                    reason: $reason
                ),
            ],
        ];
    }

    private function changeInfo(
        array $error,
        int $recordType,
        int $reportedRow,
        int $reportedVariable,
        array $fieldConfig,
        string $oldValue,
        string $newValue,
        string $reason
    ): array {
        return [
            'status' => 'corrected',
            'source_row' => $error['source_row'] ?? null,
            'report_row' => $reportedRow,
            'report_column' => $reportedVariable,
            'record_type' => $recordType,
            'field_key' => $fieldConfig['key'] ?? null,
            'field' => $fieldConfig['header'] ?? null,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'rule' => $error['rule'] ?? null,
            'description' => $error['description'] ?? '',
            'reason' => $reason,
        ];
    }

    private function normalizeAddress(
        string $value
    ): string {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = mb_strtoupper(
            $this->stripAccents($value)
        );

        /*
         * Nomenclatura urbana/rural básica.
         */
        $prefixes = [
            '/^CARRERA\s+/u' => 'CR;',
            '/^CRA\.?\s+/u' => 'CR;',
            '/^CALLE\s+/u' => 'CL;',
            '/^CL\.?\s+/u' => 'CL;',
            '/^DIAGONAL\s+/u' => 'DG;',
            '/^TRANSVERSAL\s+/u' => 'TV;',
            '/^AVENIDA\s+/u' => 'AV;',
            '/^VIA\s+/u' => 'VIA;',
            '/^VEREDA\s+/u' => 'VDA;',
        ];

        foreach ($prefixes as $pattern => $replacement) {
            $value = preg_replace(
                $pattern,
                $replacement,
                $value,
                1
            ) ?? $value;
        }

        $value = str_replace(
            ['#', ' N° ', ' NO. ', ' NUMERO '],
            [';', ';', ';', ';'],
            $value
        );

        $value = preg_replace(
            '/\s*[-,]\s*/',
            ';',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s*;\s*/',
            ';',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s+/',
            ' ',
            $value
        ) ?? $value;

        $value = trim(
            $value,
            " ;\t\n\r\0\x0B"
        );

        return $value;
    }

    private function manualResult(
        array $error,
        string $reason
    ): array {
        return [
            'status' => 'manual',
            'source_row' => $error['source_row'] ?? null,
            'report_row' => $error['row'] ?? null,
            'report_column' => $error['column'] ?? null,
            'old_value' => $error['old_value'] ?? null,
            'description' => $error['description'] ?? '',
            'rule' => $error['rule'] ?? null,
            'reason' => $reason,
        ];
    }

    private function nullableInteger(
        mixed $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (
            is_float($value)
            && floor($value) === $value
        ) {
            return (int) $value;
        }

        $text = trim((string) $value);

        if (! preg_match('/^\d+$/', $text)) {
            return null;
        }

        return (int) $text;
    }

    private function stripAccents(
        string $value
    ): string {
        return strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
            'á' => 'A',
            'é' => 'E',
            'í' => 'I',
            'ó' => 'O',
            'ú' => 'U',
            'ü' => 'U',
            'ñ' => 'N',
        ]);
    }
}
