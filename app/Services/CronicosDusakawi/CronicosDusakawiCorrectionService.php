<?php

namespace App\Services\CronicosDusakawi;

use DateTimeInterface;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class CronicosDusakawiCorrectionService
{
    public function __construct(
        private readonly CronicosDusakawiErrorParser $parser,
    ) {
    }

    /** @return array<string, mixed> */
    public function correct(
        string $reportPath,
        string $errorsPath,
        string $outputPath,
    ): array {
        if (! is_file($reportPath)) {
            throw new RuntimeException('No se encontró el Excel original de crónicos.');
        }

        try {
            $errors = $this->parser->parse($errorsPath);
            $spreadsheet = IOFactory::load($reportPath);
            $sheet = $spreadsheet->getSheetByName('Hoja1') ?? $spreadsheet->getActiveSheet();

            $audit = [];
            $manual = [];
            $changedCells = [];
            $rowsToDelete = [];

            /*
             * DUSAKAWI AGOSTO 2026
             * ---------------------
             * Se detectó que el archivo institucional trae un campo adicional
             * de tratamiento farmacológico. Esto desplaza una columna todo el
             * bloque final:
             *
             * Archivo recibido:
             *   DO = adherencia Morisky
             *   DP = remitido a
             *   DQ = fecha de remisión
             *   DR = complicaciones
             *   DS = novedades
             *   DT = causa de muerte
             *   DU = fecha de muerte
             *   DV = observaciones
             *
             * Estructura esperada por DUSAKAWI:
             *   DN = adherencia Morisky
             *   DO = remitido a
             *   DP = fecha de remisión
             *   DQ = complicaciones
             *   DR = novedades
             *   DS = causa de muerte
             *   DT = fecha de muerte
             *   DU = observaciones
             *
             * La corrección se aplica de forma global e idempotente: solo se
             * ejecuta cuando los encabezados confirman que el bloque está
             * desplazado. Así no se vuelve a correr si se procesa nuevamente
             * un archivo que ya fue corregido.
             */
            $this->normalizeShiftedFinalBlock(
                $sheet,
                $audit,
                $changedCells,
            );

            foreach ($errors as $error) {
                $row = (int) $error['excel_row'];
                $message = (string) $error['message'];
                $document = (string) $error['document'];
                $normalized = Str::of($message)->ascii()->lower()->toString();

                if ($row < 3 || $row > $sheet->getHighestDataRow()) {
                    $manual[] = $this->manual(
                        $row,
                        $document,
                        'FILA',
                        'La fila reportada no existe en el Excel original.',
                        $message
                    );
                    continue;
                }

                // AY - PARCIAL DE ORINA: ALTERADO -> PATOLOGICO.
                if (
                    str_contains($normalized, 'col ay (parcial_orina)')
                    && str_contains($normalized, 'alterado')
                ) {
                    $this->setValue(
                        $sheet,
                        "AY{$row}",
                        'PATOLOGICO',
                        $audit,
                        $changedCells,
                        $row,
                        $document,
                        'AY',
                        'ALTERADO → PATOLOGICO',
                        $message
                    );
                }

                // BK - DM CONTROLADA.
                // - Sin DX de DM: NO APLICA.
                // - Con DX de DM y HbA1c válida (BI): < 7 = SI, >= 7 = NO.
                // - Sin HbA1c válida: NO APLICA.
                if (str_contains($normalized, 'col bk (dm_controlada)')) {
                    $dxDm = strtoupper(
                        trim((string) $sheet->getCell("AC{$row}")->getFormattedValue())
                    );
                    $hbaRaw = $sheet->getCell("BI{$row}")->getCalculatedValue();
                    $hba = is_numeric($hbaRaw) ? (float) $hbaRaw : 0.0;

                    if ($dxDm === 'NO') {
                        $this->setValue(
                            $sheet,
                            "BK{$row}",
                            'NO APLICA',
                            $audit,
                            $changedCells,
                            $row,
                            $document,
                            'BK',
                            'DX confirmado DM = NO → DM CONTROLADA = NO APLICA',
                            $message
                        );
                    } elseif ($dxDm === 'SI' && $hba > 0) {
                        $newValue = $hba < 7.0 ? 'SI' : 'NO';
                        $operator = $hba < 7.0 ? '< 7' : '>= 7';

                        $this->setValue(
                            $sheet,
                            "BK{$row}",
                            $newValue,
                            $audit,
                            $changedCells,
                            $row,
                            $document,
                            'BK',
                            'HbA1c '
                                .rtrim(rtrim(number_format($hba, 2, '.', ''), '0'), '.')
                                ." {$operator} → DM CONTROLADA = {$newValue}",
                            $message
                        );
                    } else {
                        $this->setValue(
                            $sheet,
                            "BK{$row}",
                            'NO APLICA',
                            $audit,
                            $changedCells,
                            $row,
                            $document,
                            'BK',
                            'Sin HbA1c válida → DM CONTROLADA = NO APLICA',
                            $message
                        );
                    }
                }

                // AE - TIPO DM. Si no hay DM confirmado, NO APLICA.
                if (str_contains($normalized, 'col ae (tipo_dm)')) {
                    $dxDm = strtoupper(
                        trim((string) $sheet->getCell("AC{$row}")->getFormattedValue())
                    );

                    if ($dxDm === 'NO') {
                        $this->setValue(
                            $sheet,
                            "AE{$row}",
                            'NO APLICA',
                            $audit,
                            $changedCells,
                            $row,
                            $document,
                            'AE',
                            'DX confirmado DM = NO → TIPO DM = NO APLICA',
                            $message
                        );
                    } else {
                        $manual[] = $this->manual(
                            $row,
                            $document,
                            'AE',
                            'El usuario tiene DX de DM; se debe confirmar el tipo de diabetes.',
                            $message
                        );
                    }
                }

                /*
                 * Fechas comodín 1800.
                 *
                 * DUSAKAWI reconoce 1800-01-01, pero en este lote llegaron
                 * valores como 01-01-1800. Si el error indica explícitamente
                 * "fecha antigua inválida (1800)", se normaliza sin inventar
                 * información clínica.
                 */
                $this->normalizeHistoricalPlaceholderDatesFromError(
                    $sheet,
                    $row,
                    $document,
                    $normalized,
                    $message,
                    $audit,
                    $changedCells,
                );

                // Valores lipídicos enviados como guion: DUSAKAWI exige número.
                // Solo se corrigen las columnas reportadas explícitamente en el error.
                $this->normalizeDashLipidValuesFromError(
                    $sheet,
                    $row,
                    $document,
                    $message,
                    $audit,
                    $changedCells,
                );

                // Fechas como 04-19-2026 son inequívocamente MM-DD-YYYY
                // porque el segundo componente no puede ser un mes.
                $this->normalizeUnambiguousUsDatesFromError(
                    $sheet,
                    $row,
                    $document,
                    $message,
                    $audit,
                    $changedCells,
                );

                /*
                 * Fechas imposibles.
                 *
                 * Para BG (fecha de perfil lipídico) se intenta recuperar la
                 * fecha desde los controles vecinos del mismo registro.
                 *
                 * Caso real del lote:
                 *   BG = 31-17-2026
                 *   BB = 2026-07-31
                 *   BH = 2026-07-31
                 *
                 * Solo se corrige si una fecha vecina válida coincide con el
                 * mismo día y año del valor mal digitado. De esta forma no se
                 * inventa el mes.
                 */
                foreach ($this->extractImpossibleDateColumns($normalized) as $column) {
                    if (in_array($column, ['AX', 'AZ', 'BB', 'BJ'], true)) {
                        continue;
                    }

                    $coordinate = "{$column}{$row}";
                    if (isset($changedCells[$coordinate])) {
                        continue;
                    }

                    if (
                        $this->repairImpossibleDateFromContext(
                            $sheet,
                            $row,
                            $document,
                            $column,
                            $message,
                            $audit,
                            $changedCells,
                        )
                    ) {
                        continue;
                    }

                    $value = trim(
                        (string) $sheet->getCell("{$column}{$row}")->getFormattedValue()
                    );

                    if ($this->isHistoricalPlaceholder($value)) {
                        continue;
                    }

                    $manual[] = $this->manual(
                        $row,
                        $document,
                        $column,
                        "Fecha inválida '{$value}'. No se modifica porque no puede deducirse una fecha real con seguridad.",
                        $message
                    );
                }

                // Fechas imposibles/antiguas/futuras de reglas anteriores.
                // Se elimina el valor inválido sin inventar una fecha.
                $dateColumns = [
                    'AX' => 'FECHA DE GLICEMIA BASAL',
                    'AZ' => 'FECHA PARCIAL DE ORINA',
                    'BB' => 'FECHA CREATININA SANGRE',
                    'BJ' => 'FECHA REPORTE HbA1c',
                ];

                foreach ($dateColumns as $column => $label) {
                    if (str_contains($normalized, 'col '.strtolower($column).' (')) {
                        $this->setValue(
                            $sheet,
                            "{$column}{$row}",
                            null,
                            $audit,
                            $changedCells,
                            $row,
                            $document,
                            $column,
                            "{$label}: fecha inválida eliminada",
                            $message
                        );
                    }
                }

                // AK - peso con fecha u otro dato no numérico.
                if (str_contains($normalized, 'col ak (ultimo_peso)')) {
                    $this->setValue(
                        $sheet,
                        "AK{$row}",
                        null,
                        $audit,
                        $changedCells,
                        $row,
                        $document,
                        'AK',
                        'Valor no numérico eliminado',
                        $message
                    );

                    $manual[] = $this->manual(
                        $row,
                        $document,
                        'AK',
                        'Confirmar el último peso real del usuario.',
                        $message
                    );
                }

                if (
                    str_contains($normalized, 'administra la ruta rcv')
                    && str_contains($normalized, 'migracion rcv activa')
                ) {
                    $rowsToDelete[$row] = [
                        'row' => $row,
                        'document' => $document,
                        'reason' => 'Ruta RCV administrada por otra IPS / migración RCV activa',
                        'message' => $message,
                    ];
                } elseif (str_contains($normalized, 'no pertenece a su ips')) {
                    $rowsToDelete[$row] = [
                        'row' => $row,
                        'document' => $document,
                        'reason' => 'Usuario no pertenece a la IPS reportante',
                        'message' => $message,
                    ];
                }

                if (str_contains($normalized, 'documento no existe en la base (bdua)')) {
                    $rowsToDelete[$row] = [
                        'row' => $row,
                        'document' => $document,
                        'reason' => 'Documento no existe en BDUA',
                        'message' => $message,
                    ];
                }

                if (str_contains($normalized, 'salto de linea')) {
                    $changed = $this->sanitizeLineBreaksInRow(
                        $sheet,
                        $row,
                        $audit,
                        $changedCells,
                        $document,
                        $message
                    );

                    if (! $changed) {
                        $manual[] = $this->manual(
                            $row,
                            $document,
                            'FILA',
                            'DUSAKAWI reportó un salto de línea, pero no se encontró CR/LF dentro de las celdas.',
                            $message
                        );
                    }
                }
            }

            // Exclusiones administrativas al final para conservar las filas
            // originales utilizadas por el archivo de errores.
            if ($rowsToDelete !== []) {
                krsort($rowsToDelete, SORT_NUMERIC);

                foreach ($rowsToDelete as $deleteRow => $deletion) {
                    $sheet->removeRow((int) $deleteRow, 1);

                    $audit[] = [
                        'row' => (int) $deletion['row'],
                        'document' => (string) $deletion['document'],
                        'column' => 'FILA',
                        'previous_value' => 'Registro completo',
                        'new_value' => '',
                        'action' => 'FILA ELIMINADA: '.(string) $deletion['reason'],
                        'message' => (string) $deletion['message'],
                    ];
                }

                // Renumerar consecutivo de columna A.
                $consecutive = 1;
                for ($dataRow = 3; $dataRow <= $sheet->getHighestDataRow(); $dataRow++) {
                    if (
                        trim(
                            (string) $sheet->getCell("G{$dataRow}")->getFormattedValue()
                        ) === ''
                    ) {
                        continue;
                    }

                    $sheet->getCell("A{$dataRow}")->setValue($consecutive++);
                }
            }

            // Limpieza preventiva global antes de guardar.
            $this->sanitizeTextCellsGlobally(
                $sheet,
                $audit,
                $changedCells
            );

            $directory = dirname($outputPath);
            if (
                ! is_dir($directory)
                && ! mkdir($directory, 0775, true)
                && ! is_dir($directory)
            ) {
                throw new RuntimeException(
                    'No fue posible crear la carpeta de salida.'
                );
            }

            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);

            return [
                'output_path' => $outputPath,
                'parsed_errors' => count($errors),
                'automatic_corrections' => count($audit),
                'updated_cells' => count($changedCells),
                'deleted_rows' => count($rowsToDelete),
                'manual_errors' => $this->uniqueManual($manual),
                'audit' => $audit,
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException(
                'No fue posible corregir el Excel: '.$exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * Corrige el desplazamiento DN:DV que presenta el archivo institucional
     * de agosto de 2026.
     */
    private function normalizeShiftedFinalBlock(
        Worksheet $sheet,
        array &$audit,
        array &$changedCells,
    ): void {
        $headerDo = $this->normalizeHeader(
            (string) $sheet->getCell('DO2')->getFormattedValue()
        );
        $headerDp = $this->normalizeHeader(
            (string) $sheet->getCell('DP2')->getFormattedValue()
        );
        $headerDq = $this->normalizeHeader(
            (string) $sheet->getCell('DQ2')->getFormattedValue()
        );

        $isShifted = str_contains($headerDo, 'adherencia al tratamiento')
            && str_contains($headerDp, 'remitido a')
            && str_contains($headerDq, 'fecha de remision');

        if (! $isShifted) {
            return;
        }

        $highestRow = $sheet->getHighestDataRow();

        for ($row = 3; $row <= $highestRow; $row++) {
            // No tocar filas completamente vacías.
            if (
                trim((string) $sheet->getCell("G{$row}")->getFormattedValue()) === ''
            ) {
                continue;
            }

            $document = trim(
                (string) $sheet->getCell("G{$row}")->getFormattedValue()
            );

            // Capturar todos los valores ANTES de escribir para no pisar datos.
            $dm = $sheet->getCell("DM{$row}")->getValue();
            $dn = $sheet->getCell("DN{$row}")->getValue();
            $do = $sheet->getCell("DO{$row}")->getValue();
            $dp = $sheet->getCell("DP{$row}")->getValue();
            $dqCell = $sheet->getCell("DQ{$row}");
            $dq = $this->dateLikeValueForOutput(
                $dqCell->getValue(),
                $dqCell->getFormattedValue()
            );
            $dr = $sheet->getCell("DR{$row}")->getValue();
            $ds = $sheet->getCell("DS{$row}")->getValue();
            $dt = $sheet->getCell("DT{$row}")->getValue();
            $duCell = $sheet->getCell("DU{$row}");
            $du = $this->dateLikeValueForOutput(
                $duCell->getValue(),
                $duCell->getFormattedValue()
            );
            $dv = $sheet->getCell("DV{$row}")->getValue();

            /*
             * DN era el octavo espacio de tratamiento. Como la estructura
             * DUSAKAWI solo deja DG:DM para tratamientos, se conserva:
             * - si DM está vacío/SINDATO, DN pasa a DM;
             * - si ambos tienen tratamiento real, se concatenan en DM.
             */
            $newDm = $this->mergeLastTreatment($dm, $dn);

            $moves = [
                'DM' => $newDm,
                'DN' => $do,
                'DO' => $dp,
                'DP' => $dq,
                'DQ' => $dr,
                'DR' => $ds,
                'DS' => $dt,
                'DT' => $du,
                'DU' => $dv,
                'DV' => null,
            ];

            foreach ($moves as $column => $newValue) {
                $coordinate = "{$column}{$row}";
                $cell = $sheet->getCell($coordinate);
                $oldValue = $cell->getFormattedValue();

                if ($newValue === null) {
                    $cell->setValue(null);
                } elseif (is_string($newValue)) {
                    $cell->setValueExplicit($newValue, DataType::TYPE_STRING);
                } else {
                    $cell->setValue($newValue);
                }

                $newFormatted = $cell->getFormattedValue();

                if ((string) $oldValue === (string) $newFormatted) {
                    continue;
                }

                $changedCells[$coordinate] = true;
                $audit[] = [
                    'row' => $row,
                    'document' => $document,
                    'column' => $column,
                    'previous_value' => (string) $oldValue,
                    'new_value' => (string) $newFormatted,
                    'action' => 'Estructura DUSAKAWI: bloque final realineado',
                    'message' => 'Corrección automática del desplazamiento de columnas DN:DV.',
                ];
            }
        }

        // Encabezados esperados por DUSAKAWI.
        $headers = [
            'DN2' => 'ADHERENCIA AL TRATAMIENTO FARMACOLOGICO (TEST DE MORISKY GREEN)',
            'DO2' => 'REMITIDO A',
            'DP2' => 'FECHA DE REMISION',
            'DQ2' => 'COMPLICACIONES',
            'DR2' => 'NOVEDADES',
            'DS2' => 'CAUSA DE MUERTE',
            'DT2' => 'FECHA DE MUERTE',
            'DU2' => 'OBSERVACIONES',
        ];

        foreach ($headers as $coordinate => $value) {
            $sheet->getCell($coordinate)->setValueExplicit(
                $value,
                DataType::TYPE_STRING
            );
        }

        $sheet->getCell('DV2')->setValue(null);
    }

    private function mergeLastTreatment(mixed $dm, mixed $dn): mixed
    {
        $dmText = trim((string) ($dm ?? ''));
        $dnText = trim((string) ($dn ?? ''));

        $dmEmpty = $this->isEmptyTreatment($dmText);
        $dnEmpty = $this->isEmptyTreatment($dnText);

        if ($dnEmpty) {
            return $dm;
        }

        if ($dmEmpty) {
            return $dn;
        }

        if ($dmText === $dnText) {
            return $dm;
        }

        return $dmText.' | '.$dnText;
    }

    private function isEmptyTreatment(string $value): bool
    {
        $normalized = strtoupper(
            preg_replace('/\s+/u', '', trim($value)) ?? ''
        );

        return in_array(
            $normalized,
            ['', 'SINDATO', 'NOAPLICA', 'NA', 'NULL'],
            true
        );
    }

    private function dateLikeValueForOutput(
        mixed $rawValue,
        mixed $formattedValue,
    ): mixed {
        if ($rawValue instanceof DateTimeInterface) {
            return $rawValue->format('Y-m-d');
        }

        if (is_numeric($rawValue)) {
            try {
                return ExcelDate::excelToDateTimeObject(
                    (float) $rawValue
                )->format('Y-m-d');
            } catch (Throwable) {
                return trim((string) $formattedValue);
            }
        }

        $text = trim((string) ($rawValue ?? ''));

        if ($this->isHistoricalPlaceholder($text)) {
            return '1800-01-01';
        }

        return $rawValue;
    }

    private function normalizeDashLipidValuesFromError(
        Worksheet $sheet,
        int $row,
        string $document,
        string $originalMessage,
        array &$audit,
        array &$changedCells,
    ): void {
        preg_match_all(
            '/Col\s+(BC|BD|BE|BF)\s+\([^)]+\):\s+no es n[uú]mero:\s*["“”]?-+["“”]?/iu',
            $originalMessage,
            $matches
        );

        $columns = array_values(array_unique(array_map(
            static fn (string $column): string => strtoupper($column),
            $matches[1] ?? []
        )));

        foreach ($columns as $column) {
            $coordinate = "{$column}{$row}";
            $current = trim((string) $sheet->getCell($coordinate)->getFormattedValue());

            if ($current !== '-') {
                continue;
            }

            $this->setValue(
                $sheet,
                $coordinate,
                '0',
                $audit,
                $changedCells,
                $row,
                $document,
                $column,
                'Valor lipídico faltante normalizado: - → 0',
                $originalMessage,
                true
            );
        }
    }

    private function normalizeUnambiguousUsDatesFromError(
        Worksheet $sheet,
        int $row,
        string $document,
        string $originalMessage,
        array &$audit,
        array &$changedCells,
    ): void {
        preg_match_all(
            '/Col\s+([A-Z]{1,3})\s+\([^)]+\):\s+fecha inv[aá]lida:\s*["“”]?(\d{1,2})-(\d{1,2})-(\d{4})["“”]?/iu',
            $originalMessage,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $column = strtoupper((string) ($match[1] ?? ''));
            $month = (int) ($match[2] ?? 0);
            $day = (int) ($match[3] ?? 0);
            $year = (int) ($match[4] ?? 0);

            // Solo corregir cuando MM-DD-YYYY es inequívoco.
            if (
                $month < 1 || $month > 12
                || $day <= 12 || $day > 31
                || $year < 1900
                || ! checkdate($month, $day, $year)
            ) {
                continue;
            }

            $coordinate = "{$column}{$row}";
            $current = trim((string) $sheet->getCell($coordinate)->getFormattedValue());
            $expectedSource = sprintf('%02d-%02d-%04d', $month, $day, $year);

            if ($current !== $expectedSource) {
                continue;
            }

            $normalizedDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $this->setValue(
                $sheet,
                $coordinate,
                $normalizedDate,
                $audit,
                $changedCells,
                $row,
                $document,
                $column,
                "Fecha inequívoca normalizada: {$current} → {$normalizedDate}",
                $originalMessage,
                true
            );
        }
    }

    private function normalizeHistoricalPlaceholderDatesFromError(
        Worksheet $sheet,
        int $row,
        string $document,
        string $normalizedMessage,
        string $originalMessage,
        array &$audit,
        array &$changedCells,
    ): void {
        if (
            ! str_contains($normalizedMessage, 'fecha antigua invalida (1800)')
        ) {
            return;
        }

        preg_match_all(
            '/col\s+([a-z]{1,3})\s+\([^)]+\):\s+fecha antigua invalida\s+\(1800\)/i',
            $normalizedMessage,
            $matches
        );

        $columns = array_unique(
            array_map(
                static fn (string $column): string => strtoupper($column),
                $matches[1] ?? []
            )
        );

        foreach ($columns as $column) {
            $coordinate = "{$column}{$row}";
            $current = trim(
                (string) $sheet->getCell($coordinate)->getFormattedValue()
            );

            if (! $this->isHistoricalPlaceholder($current)) {
                continue;
            }

            $this->setValue(
                $sheet,
                $coordinate,
                '1800-01-01',
                $audit,
                $changedCells,
                $row,
                $document,
                $column,
                'Fecha comodín normalizada: 01-01-1800 → 1800-01-01',
                $originalMessage,
                true
            );
        }
    }

    /**
     * Intenta recuperar una fecha inválida usando fechas vecinas del mismo
     * control clínico, únicamente cuando existe evidencia suficiente.
     */
    private function repairImpossibleDateFromContext(
        Worksheet $sheet,
        int $row,
        string $document,
        string $column,
        string $message,
        array &$audit,
        array &$changedCells,
    ): bool {
        if ($column !== 'BG') {
            return false;
        }

        $coordinate = "BG{$row}";
        $invalidValue = trim(
            (string) $sheet->getCell($coordinate)->getFormattedValue()
        );

        // Formatos que suelen llegar desde el Excel: DD-MM-YYYY o DD/MM/YYYY.
        if (
            ! preg_match(
                '/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/',
                $invalidValue,
                $parts
            )
        ) {
            return false;
        }

        $invalidDay = (int) $parts[1];
        $invalidYear = (int) $parts[3];

        if ($invalidDay < 1 || $invalidDay > 31 || $invalidYear < 1900) {
            return false;
        }

        /*
         * BB = fecha creatinina.
         * BH = fecha solicitud HbA1c.
         *
         * Para el lote de agosto ambas corresponden al mismo control/laboratorio
         * y permiten validar el día y año del perfil lipídico.
         */
        foreach (['BH', 'BB'] as $referenceColumn) {
            $referenceCell = $sheet->getCell(
                "{$referenceColumn}{$row}"
            );

            $candidate = $this->dateLikeValueForOutput(
                $referenceCell->getValue(),
                $referenceCell->getFormattedValue()
            );

            if (
                ! is_string($candidate)
                || ! preg_match(
                    '/^(\d{4})-(\d{2})-(\d{2})$/',
                    $candidate,
                    $candidateParts
                )
            ) {
                continue;
            }

            $candidateYear = (int) $candidateParts[1];
            $candidateDay = (int) $candidateParts[3];

            if (
                $candidateYear !== $invalidYear
                || $candidateDay !== $invalidDay
                || $this->isHistoricalPlaceholder($candidate)
            ) {
                continue;
            }

            $this->setValue(
                $sheet,
                $coordinate,
                $candidate,
                $audit,
                $changedCells,
                $row,
                $document,
                'BG',
                "Fecha de perfil lipídico recuperada desde {$referenceColumn}: {$invalidValue} → {$candidate}",
                $message,
                true
            );

            return true;
        }

        return false;
    }

    /** @return array<int, string> */
    private function extractImpossibleDateColumns(
        string $normalizedMessage,
    ): array {
        preg_match_all(
            '/col\s+([a-z]{1,3})\s+\([^)]+\):\s+fecha invalida:/i',
            $normalizedMessage,
            $matches
        );

        return array_values(
            array_unique(
                array_map(
                    static fn (string $column): string => strtoupper($column),
                    $matches[1] ?? []
                )
            )
        );
    }

    private function isHistoricalPlaceholder(string $value): bool
    {
        $normalized = trim(str_replace('/', '-', $value));

        // DUSAKAWI acepta 1800-01-01 como fecha comodín. Algunos Excel
        // incrementan accidentalmente el día al arrastrar la celda
        // (1800-01-02, 1800-01-03, ...). Todo valor del año 1800 se trata
        // como comodín para poder normalizarlo a 1800-01-01 cuando el
        // archivo de errores lo reporta como "fecha antigua inválida (1800)".
        return preg_match('/^(?:1800-\d{1,2}-\d{1,2}|\d{1,2}-\d{1,2}-1800)$/', $normalized) === 1;
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    private function sanitizeTextCellsGlobally(
        Worksheet $sheet,
        array &$audit,
        array &$changedCells,
    ): void {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex =
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $highestColumn
            );

        for ($row = 1; $row <= $highestRow; $row++) {
            for (
                $columnIndex = 1;
                $columnIndex <= $highestColumnIndex;
                $columnIndex++
            ) {
                $column =
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                        $columnIndex
                    );
                $coordinate = $column.$row;
                $value = $sheet->getCell($coordinate)->getValue();

                // Desactivar wrap en toda celda de texto. No conservar objetos
                // Cell mientras se consultan estilos/otras celdas: PhpSpreadsheet
                // puede desvincularlos de la colección interna.
                if (is_string($value)) {
                    $sheet->getStyle($coordinate)
                        ->getAlignment()
                        ->setWrapText(false);

                    $clean = preg_replace(
                        '/[\r\n]+/u',
                        ' ',
                        $value
                    ) ?? $value;

                    $clean = trim(
                        (string) preg_replace(
                            '/\s{2,}/u',
                            ' ',
                            $clean
                        )
                    );

                    if ($clean !== $value) {
                        $document = $row >= 3
                            ? trim(
                                (string) $sheet
                                    ->getCell("G{$row}")
                                    ->getFormattedValue()
                            )
                            : '';

                        $sheet->getCell($coordinate)->setValueExplicit(
                            $clean,
                            DataType::TYPE_STRING
                        );

                        $changedCells[$coordinate] = true;
                        $audit[] = [
                            'row' => $row,
                            'document' => $document,
                            'column' => $column,
                            'previous_value' => $value,
                            'new_value' => $clean,
                            'action' => 'Limpieza global: CR/LF y espacios normalizados; wrap_text desactivado',
                            'message' => 'Limpieza preventiva global de texto para DUSAKAWI',
                        ];
                    }
                }
            }
        }
    }

    private function sanitizeLineBreaksInRow(
        Worksheet $sheet,
        int $row,
        array &$audit,
        array &$changedCells,
        string $document,
        string $message,
    ): bool {
        $highestColumn = $sheet->getHighestDataColumn();
        $range = $sheet->rangeToArray(
            "A{$row}:{$highestColumn}{$row}",
            null,
            true,
            false,
            false
        )[0] ?? [];

        $changed = false;

        foreach ($range as $index => $value) {
            if (
                ! is_string($value)
                || (
                    ! str_contains($value, "\n")
                    && ! str_contains($value, "\r")
                )
            ) {
                continue;
            }

            $column =
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                    $index + 1
                );
            $coordinate = $column.$row;
            $clean = preg_replace(
                '/[\r\n]+/u',
                ' ',
                $value
            ) ?? $value;

            $clean = trim(
                (string) preg_replace(
                    '/\s{2,}/u',
                    ' ',
                    $clean
                )
            );

            $this->setValue(
                $sheet,
                $coordinate,
                $clean,
                $audit,
                $changedCells,
                $row,
                $document,
                $column,
                'Salto de línea reemplazado por espacio',
                $message,
                true
            );

            $changed = true;
        }

        return $changed;
    }

    private function setValue(
        Worksheet $sheet,
        string $coordinate,
        mixed $newValue,
        array &$audit,
        array &$changedCells,
        int $row,
        string $document,
        string $column,
        string $action,
        string $message,
        bool $allowSecondChange = false,
    ): void {
        if (
            isset($changedCells[$coordinate])
            && ! $allowSecondChange
        ) {
            return;
        }

        $cell = $sheet->getCell($coordinate);
        $oldValue = $cell->getFormattedValue();

        if ($newValue === null) {
            $cell->setValue(null);
        } else {
            $cell->setValueExplicit(
                (string) $newValue,
                DataType::TYPE_STRING
            );
        }

        $newFormatted = $cell->getFormattedValue();

        if ((string) $oldValue === (string) $newFormatted) {
            return;
        }

        $changedCells[$coordinate] = true;
        $audit[] = [
            'row' => $row,
            'document' => $document,
            'column' => $column,
            'previous_value' => $oldValue,
            'new_value' => $newValue === null
                ? ''
                : (string) $newValue,
            'action' => $action,
            'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function manual(
        int $row,
        string $document,
        string $field,
        string $reason,
        string $message,
    ): array {
        return [
            'row' => $row,
            'document' => $document,
            'field' => $field,
            'reason' => $reason,
            'message' => $message,
        ];
    }

    /** @param array<int, array<string, mixed>> $manual */
    private function uniqueManual(array $manual): array
    {
        $seen = [];
        $result = [];

        foreach ($manual as $item) {
            $key = ($item['row'] ?? '')
                .'|'.($item['field'] ?? '')
                .'|'.($item['reason'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }
}
