<?php

namespace App\Services\DemandaInducida;

use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use ZipArchive;

class DemandaInducidaService
{
    public function process(
        string $inputPath,
        string $codigoHabilitacion
    ): array {
        if (! is_file($inputPath)) {
            throw new RuntimeException(
                'No se encontró el Excel cargado.'
            );
        }

        $codigoHabilitacion = preg_replace(
            '/\D+/',
            '',
            $codigoHabilitacion
        );

        if (strlen($codigoHabilitacion) !== 12) {
            throw new RuntimeException(
                'El código de habilitación debe tener 12 dígitos.'
            );
        }

        try {
            $spreadsheet = IOFactory::load($inputPath);
            [$sheet, $headerRow] =
                $this->findDataSheet($spreadsheet);

            $fields = config(
                'demanda_inducida.fields',
                []
            );

            if (count($fields) !== 35) {
                throw new RuntimeException(
                    'La configuración de Demanda inducida debe tener 35 campos.'
                );
            }

            $this->validateHeader(
                $sheet,
                $headerRow
            );

            $firstDataRow = $headerRow + 1;
            $highestRow = $sheet->getHighestDataRow();

            $corrections = [];
            $warnings = [];
            $records = 0;

            for (
                $row = $firstDataRow;
                $row <= $highestRow;
                $row++
            ) {
                if ($this->rowIsEmpty($sheet, $row)) {
                    continue;
                }

                $records++;

                /*
                 * Primero normalizamos cada campo.
                 */
                for ($field = 1; $field <= 35; $field++) {
                    $definition = $fields[$field];
                    $cell = $sheet->getCell([$field, $row]);
                    $original = $cell->getValue();

                    $normalized = $this->normalize(
                        value: $original,
                        definition: $definition,
                        field: $field
                    );

                    $mustRewrite =
                        in_array(
                            $definition['type'] ?? '',
                            ['F', 'H'],
                            true
                        )
                        && $normalized['value'] !== '';

                    if (
                        $normalized['changed']
                        || $mustRewrite
                    ) {
                        $sheet->setCellValueExplicit(
                            [$field, $row],
                            $normalized['value'],
                            DataType::TYPE_STRING
                        );

                        if (
                            $normalized['changed']
                            || (string) $original
                                !== (string) $normalized['value']
                        ) {
                            $corrections[] = [
                                'row' => $row,
                                'field' => $field,
                                'name' => $definition['name'],
                                'old' => $original,
                                'new' => $normalized['value'],
                            ];
                        }
                    }
                }

                /*
                 * Regla explícita de la guía:
                 * seguimiento efectivo sin cita -> 01/01/1800.
                 */
                $classification =
                    trim((string) $sheet
                        ->getCell([8, $row])
                        ->getValue());

                $appointment =
                    trim((string) $sheet
                        ->getCell([35, $row])
                        ->getValue());

                if (
                    $this->sameText(
                        $classification,
                        'Efectivo'
                    )
                    && $appointment === ''
                ) {
                    $sheet->setCellValueExplicit(
                        [35, $row],
                        '01/01/1800',
                        DataType::TYPE_STRING
                    );

                    $corrections[] = [
                        'row' => $row,
                        'field' => 35,
                        'name' => $fields[35]['name'],
                        'old' => '',
                        'new' => '01/01/1800',
                    ];
                }

                /*
                 * Reglas aprendidas de los LOG reales de SIGIRES.
                 */
                $this->applyKnownSigiresRules(
                    sheet: $sheet,
                    row: $row,
                    fields: $fields,
                    corrections: $corrections
                );

                /*
                 * Luego validamos el registro ya corregido.
                 */
                $this->validateRow(
                    sheet: $sheet,
                    row: $row,
                    warnings: $warnings
                );
            }

            if ($records === 0) {
                throw new RuntimeException(
                    'No se encontraron registros de Demanda inducida.'
                );
            }

            $date = now()->format('Ymd');
            $baseName =
                'DEMANDA_INDUCIDA_'
                .$codigoHabilitacion
                .'_'
                .$date;

            $workDir = storage_path(
                'app/private/demanda-inducida/generated/'
                .uniqid('', true)
            );

            File::ensureDirectoryExists(
                $workDir
            );

            $xlsxPath =
                $workDir
                .DIRECTORY_SEPARATOR
                .$baseName
                .'.xlsx';

            $zipPath =
                $workDir
                .DIRECTORY_SEPARATOR
                .$baseName
                .'.zip';

            IOFactory::createWriter(
                $spreadsheet,
                'Xlsx'
            )->save($xlsxPath);

            $zip = new ZipArchive();

            if (
                $zip->open(
                    $zipPath,
                    ZipArchive::CREATE
                    | ZipArchive::OVERWRITE
                ) !== true
            ) {
                throw new RuntimeException(
                    'No fue posible crear el ZIP.'
                );
            }

            $zip->addFile(
                $xlsxPath,
                basename($xlsxPath)
            );
            $zip->close();

            return [
                'xlsx_path' => $xlsxPath,
                'zip_path' => $zipPath,
                'xlsx_name' => basename($xlsxPath),
                'zip_name' => basename($zipPath),
                'records' => $records,
                'corrections' => $corrections,
                'correction_count' => count($corrections),
                'warnings' => $warnings,
                'warning_count' => count($warnings),
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException(
                'No fue posible preparar Demanda inducida: '
                .$exception->getMessage(),
                previous: $exception
            );
        }
    }

    private function findDataSheet(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
    ): array {
        $preferred = config(
            'demanda_inducida.sheet_name',
            'Estructura DI'
        );

        $sheet = $spreadsheet->getSheetByName(
            $preferred
        );

        if ($sheet instanceof Worksheet) {
            $headerRow = $this->findHeaderRow(
                $sheet
            );

            if ($headerRow !== null) {
                return [$sheet, $headerRow];
            }
        }

        foreach (
            $spreadsheet->getWorksheetIterator()
            as $candidate
        ) {
            $headerRow = $this->findHeaderRow(
                $candidate
            );

            if ($headerRow !== null) {
                return [$candidate, $headerRow];
            }
        }

        throw new RuntimeException(
            'No se encontró la fila de encabezados de los 35 campos de Demanda inducida.'
        );
    }

    private function findHeaderRow(
        Worksheet $sheet
    ): ?int {
        $max = min(
            20,
            max(1, $sheet->getHighestDataRow())
        );

        for ($row = 1; $row <= $max; $row++) {
            $a = $this->normalizeHeader(
                $sheet->getCell([1, $row])->getValue()
            );

            $b = $this->normalizeHeader(
                $sheet->getCell([2, $row])->getValue()
            );

            if (
                $a === 'TIPO DOC'
                && $b === 'NUMERO DOC'
            ) {
                return $row;
            }
        }

        return null;
    }

    private function validateHeader(
        Worksheet $sheet,
        int $headerRow
    ): void {
        $expected = [
            'TIPO DOC',
            'NUMERO DOC',
            'FECHA ACTIVIDAD DI',
            'ELEMENTO DI',
            'TIPO ELEMENTO',
            'OBJETIVO',
        ];

        for ($i = 1; $i <= count($expected); $i++) {
            $actual = $this->normalizeHeader(
                $sheet
                    ->getCell([$i, $headerRow])
                    ->getValue()
            );

            if ($actual !== $expected[$i - 1]) {
                throw new RuntimeException(
                    'La estructura de Demanda inducida no coincide con la plantilla oficial en la columna '
                    .$i.'.'
                );
            }
        }

        if (
            $sheet->getHighestColumn()
            && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $sheet->getHighestColumn()
            ) < 35
        ) {
            throw new RuntimeException(
                'El Excel debe contener los 35 campos oficiales.'
            );
        }
    }

    private function normalize(
        mixed $value,
        array $definition,
        int $field
    ): array {
        if ($value === null) {
            return [
                'value' => '',
                'changed' => false,
            ];
        }

        $original = (string) $value;
        $type = $definition['type'] ?? 'A';

        if ($type === 'F') {
            $date = $this->normalizeDate(
                $value
            );

            if ($date !== null) {
                return [
                    'value' => $date,
                    'changed' =>
                        $date !== trim($original),
                ];
            }

            return [
                'value' => trim($original),
                'changed' =>
                    trim($original) !== $original,
            ];
        }

        if ($type === 'H') {
            $hour = $this->normalizeHour(
                $value
            );

            if ($hour !== null) {
                return [
                    'value' => $hour,
                    'changed' =>
                        $hour !== trim($original),
                ];
            }
        }

        $clean = trim(
            preg_replace(
                '/[\r\n\x{2028}\x{2029}]+/u',
                ' ',
                $original
            )
        );

        $clean = preg_replace(
            '/\s+/u',
            ' ',
            $clean
        ) ?? $clean;

        if ($field === 1) {
            $clean = mb_strtoupper(
                preg_replace(
                    '/[^A-Za-z]/',
                    '',
                    $clean
                )
            );
        }

        if ($field === 2) {
            $clean = preg_replace(
                '/[\s.,-]+/',
                '',
                $clean
            );
        }

        if (in_array($field, [7, 23], true)) {
            $clean = preg_replace(
                '/\D+/',
                '',
                $clean
            );
        }

        if ($field === 24) {
            $clean = mb_strtolower(
                $clean
            );
        }

        if ($field === 14) {
            $clean = mb_strtoupper(
                $clean
            );
        }

        /*
         * Catálogos: si coincide sin distinguir
         * mayúsculas/tildes, usamos exactamente
         * el valor oficial de la guía.
         */
        $allowed = $definition['allowed'] ?? [];

        if (
            is_array($allowed)
            && $allowed !== []
            && $clean !== ''
        ) {
            foreach ($allowed as $candidate) {
                if (
                    $this->sameText(
                        $clean,
                        $candidate
                    )
                ) {
                    $clean = $candidate;
                    break;
                }
            }
        }

        return [
            'value' => $clean,
            'changed' => $clean !== $original,
        ];
    }

    private function validateRow(
        Worksheet $sheet,
        int $row,
        array &$warnings
    ): void {
        $fields = config(
            'demanda_inducida.fields',
            []
        );

        $element = trim(
            (string) $sheet
                ->getCell([4, $row])
                ->getValue()
        );

        $classification = trim(
            (string) $sheet
                ->getCell([8, $row])
                ->getValue()
        );

        for ($field = 1; $field <= 35; $field++) {
            $definition = $fields[$field];
            $value = trim(
                (string) $sheet
                    ->getCell([$field, $row])
                    ->getValue()
            );

            $required = $this->isRequired(
                field: $field,
                definition: $definition,
                element: $element,
                classification: $classification
            );

            if ($required && $value === '') {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): campo obligatorio vacío.";

                continue;
            }

            if ($value === '') {
                continue;
            }

            $length = mb_strlen(
                $value
            );

            if (
                ($definition['min'] ?? 0) > 0
                && $length < $definition['min']
            ) {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): longitud {$length} menor a {$definition['min']}.";
            }

            if (
                ($definition['max'] ?? 0) > 0
                && $length > $definition['max']
            ) {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): longitud {$length} mayor a {$definition['max']}.";
            }

            if (
                ($definition['type'] ?? '') === 'F'
                && ! preg_match(
                    '/^\d{2}\/\d{2}\/\d{4}$/',
                    $value
                )
            ) {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): la fecha debe estar en DD/MM/AAAA.";
            }

            if (
                ($definition['type'] ?? '') === 'H'
                && ! preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $value
                )
            ) {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): la hora debe estar en HH:MM.";
            }

            if (
                ($definition['type'] ?? '') === 'N'
                && ! preg_match(
                    '/^\d+$/',
                    $value
                )
            ) {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): debe contener solo números.";
            }

            $allowed =
                $definition['allowed']
                ?? [];

            if (
                is_array($allowed)
                && $allowed !== []
                && ! $this->inAllowed(
                    $value,
                    $allowed
                )
            ) {
                $warnings[] =
                    "Fila {$row}, campo {$field} ({$definition['name']}): valor no permitido [{$value}].";
            }
        }

        /*
         * Área / Programa.
         */
        $area = trim(
            (string) $sheet
                ->getCell([33, $row])
                ->getValue()
        );

        $program = trim(
            (string) $sheet
                ->getCell([34, $row])
                ->getValue()
        );

        if (
            $area !== ''
            && $program !== ''
            && ! $this->validProgram(
                $area,
                $program
            )
        ) {
            $warnings[] =
                "Fila {$row}, campo 34 (Programa): [{$program}] no corresponde al área [{$area}] según la guía.";
        }

        /*
         * Reglas condicionales expresas de la guía.
         */
        if (
            $this->sameText(
                $classification,
                'Efectivo'
            )
            && $this->sameText(
                $element,
                'Llamada telefónica'
            )
        ) {
            $condition = trim(
                (string) $sheet
                    ->getCell([18, $row])
                    ->getValue()
            );

            if ($condition === '') {
                $warnings[] =
                    "Fila {$row}, campo 18 (Condición final usuario): obligatorio para llamada telefónica efectiva.";
            }
        }

        if (
            $this->sameText(
                $classification,
                'No efectivo'
            )
            && $this->sameText(
                $element,
                'Llamada telefónica'
            )
            && trim(
                (string) $sheet
                    ->getCell([25, $row])
                    ->getValue()
            ) === ''
        ) {
            $warnings[] =
                "Fila {$row}, campo 25 (Resultado de llamada): obligatorio para llamada telefónica no efectiva.";
        }
    }

    private function isRequired(
        int $field,
        array $definition,
        string $element,
        string $classification
    ): bool {
        if (($definition['required'] ?? false) === true) {
            /*
             * 35: la guía lo marca obligatorio;
             * se mantiene como obligatorio.
             */
            if ($field === 35) {
                return true;
            }

            if (in_array($field, [22,23,24], true)) {
                // Se manejan por condición más abajo.
            } else {
                return true;
            }
        }

        if (
            $this->sameText(
                $element,
                'Llamada telefónica'
            )
            && in_array(
                $field,
                [7,8,9,10,11,12,13],
                true
            )
        ) {
            return true;
        }

        if (
            $this->sameText(
                $element,
                'SMS'
            )
            && in_array(
                $field,
                [26,27,28],
                true
            )
        ) {
            return true;
        }

        if (
            $this->sameText(
                $element,
                'Visita preventiva de salud'
            )
            && in_array(
                $field,
                [29,30],
                true
            )
        ) {
            return true;
        }

        if (
            $this->sameText(
                $classification,
                'No efectivo'
            )
            && $this->sameText(
                $element,
                'Visita preventiva de salud'
            )
            && $field === 31
        ) {
            return true;
        }

        if (
            $this->sameText(
                $classification,
                'Efectivo'
            )
            && $this->sameText(
                $element,
                'Visita preventiva de salud'
            )
            && $field === 22
        ) {
            return true;
        }

        if (
            $this->sameText(
                $classification,
                'Efectivo'
            )
            && $this->sameText(
                $element,
                'SMS'
            )
            && $field === 23
        ) {
            return true;
        }

        if (
            $this->sameText(
                $classification,
                'Efectivo'
            )
            && (
                $this->sameText(
                    $element,
                    'Material Educativo'
                )
                || $this->sameText(
                    $element,
                    'Búsqueda Activa'
                )
            )
            && $field === 24
        ) {
            return true;
        }

        return false;
    }


    private function applyKnownSigiresRules(
        Worksheet $sheet,
        int $row,
        array $fields,
        array &$corrections
    ): void {
        $element = trim((string) $sheet->getCell([4, $row])->getValue());
        $classification = trim((string) $sheet->getCell([8, $row])->getValue());

        // Campo 10: catálogo oficial de relación con el usuario.
        $relationship = trim((string) $sheet->getCell([10, $row])->getValue());

        if ($relationship !== '') {
            $map = [
                'hija' => 'Hijo(a)',
                'hijo' => 'Hijo(a)',
                'amiga' => 'Amigo',
                'amigo' => 'Amigo',
                'esposo' => 'Otro. Cual',
                'esposa' => 'Otro. Cual',
                'conyuge' => 'Otro. Cual',
                'suegra' => 'Otro. Cual',
                'suegro' => 'Otro. Cual',
                'cuñada' => 'Otro. Cual',
                'cunada' => 'Otro. Cual',
                'cuñado' => 'Otro. Cual',
                'cunado' => 'Otro. Cual',
            ];

            $key = $this->comparable($relationship);

            if (isset($map[$key])) {
                $this->setKnownCorrection(
                    $sheet, $row, 10, $fields[10]['name'],
                    $map[$key], $corrections
                );
            }
        }

        // Campo 11: la guía exige la misma fecha del campo 3.
        if ($this->sameText($element, 'Llamada telefónica')) {
            $callDate = trim((string) $sheet->getCell([11, $row])->getValue());

            if ($this->normalizeDate($callDate) === null) {
                $activity = $this->normalizeDate(
                    $sheet->getCell([3, $row])->getValue()
                );

                if ($activity !== null) {
                    $this->setKnownCorrection(
                        $sheet, $row, 11, $fields[11]['name'],
                        $activity, $corrections
                    );
                }
            }
        }

        // Llamada no efectiva: campos 13 y 25 son obligatorios por regla SIGIRES.
        if (
            $this->sameText($element, 'Llamada telefónica')
            && $this->sameText($classification, 'No efectivo')
        ) {
            $callText = trim((string) $sheet->getCell([13, $row])->getValue());

            if ($callText === '') {
                $this->setKnownCorrection(
                    $sheet, $row, 13, $fields[13]['name'],
                    'NO SE LOGRO CONTACTO CON EL USUARIO',
                    $corrections
                );
            }

            $result = trim((string) $sheet->getCell([25, $row])->getValue());
            $allowed = $fields[25]['allowed'] ?? [];

            if ($result === '' || ! $this->inAllowed($result, $allowed)) {
                $this->setKnownCorrection(
                    $sheet, $row, 25, $fields[25]['name'],
                    'Otro', $corrections
                );
            }
        }
    }

    private function setKnownCorrection(
        Worksheet $sheet,
        int $row,
        int $field,
        string $name,
        string $newValue,
        array &$corrections
    ): void {
        $oldValue = $sheet->getCell([$field, $row])->getValue();

        if ((string) $oldValue === $newValue) {
            return;
        }

        $sheet->setCellValueExplicit(
            [$field, $row],
            $newValue,
            DataType::TYPE_STRING
        );

        $corrections[] = [
            'row' => $row,
            'field' => $field,
            'name' => $name,
            'old' => $oldValue,
            'new' => $newValue,
        ];
    }

    private function normalizeDate(
        mixed $value
    ): ?string {
        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format(
                    'd/m/Y'
                );
            }

            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject(
                    (float) $value
                )->format('d/m/Y');
            }

            /*
             * Limpieza fuerte:
             * - espacios normales
             * - NBSP
             * - tabulaciones/saltos de línea
             * - apóstrofe de Excel al inicio
             */
            $text = (string) $value;
            $text = str_replace(
                ["\xC2\xA0", "\t", "\r", "\n"],
                '',
                $text
            );
            $text = trim(
                $text,
                " \t\n\r\0\x0B'"
            );

            if ($text === '') {
                return null;
            }

            // DD/MM/AAAA o DD-MM-AAAA.
            if (
                preg_match(
                    '/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/',
                    $text,
                    $matches
                )
            ) {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];

                if (
                    checkdate(
                        $month,
                        $day,
                        $year
                    )
                ) {
                    return sprintf(
                        '%02d/%02d/%04d',
                        $day,
                        $month,
                        $year
                    );
                }
            }

            // AAAA/MM/DD o AAAA-MM-DD.
            if (
                preg_match(
                    '/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/',
                    $text,
                    $matches
                )
            ) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];

                if (
                    checkdate(
                        $month,
                        $day,
                        $year
                    )
                ) {
                    return sprintf(
                        '%02d/%02d/%04d',
                        $day,
                        $month,
                        $year
                    );
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function normalizeHour(
        mixed $value
    ): ?string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        if (
            is_numeric($value)
            && (float) $value >= 0
            && (float) $value < 1
        ) {
            $seconds = (int) round(
                (float) $value * 86400
            );

            $hours = intdiv(
                $seconds,
                3600
            ) % 24;

            $minutes = intdiv(
                $seconds % 3600,
                60
            );

            return sprintf(
                '%02d:%02d',
                $hours,
                $minutes
            );
        }

        $text = (string) $value;
        $text = str_replace(
            ["\xC2\xA0", "\t", "\r", "\n"],
            '',
            $text
        );
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        /*
         * Quita puntuación accidental al final:
         * 15:48. -> 15:48
         * 15:48, -> 15:48
         */
        $text = preg_replace(
            '/[.,;]+$/',
            '',
            $text
        ) ?? $text;

        /*
         * Acepta:
         * 15:48
         * 15:48:00
         * 3:48 PM
         */
        if (
            preg_match(
                '/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i',
                $text,
                $matches
            )
        ) {
            $hour = (int) $matches[1];
            $minute = (int) $matches[2];
            $ampm = strtoupper(
                $matches[3] ?? ''
            );

            if ($ampm === 'PM' && $hour < 12) {
                $hour += 12;
            }

            if ($ampm === 'AM' && $hour === 12) {
                $hour = 0;
            }

            if (
                $hour >= 0
                && $hour <= 23
                && $minute >= 0
                && $minute <= 59
            ) {
                return sprintf(
                    '%02d:%02d',
                    $hour,
                    $minute
                );
            }
        }

        return null;
    }

    private function validProgram(
        string $area,
        string $program
    ): bool {
        if (
            $this->sameText(
                $program,
                'Otro'
            )
        ) {
            return true;
        }

        $programs = config(
            'demanda_inducida.programs',
            []
        );

        foreach ($programs as $key => $values) {
            if (! $this->sameText($area, $key)) {
                continue;
            }

            return $this->inAllowed(
                $program,
                $values
            );
        }

        return false;
    }

    private function inAllowed(
        string $value,
        array $allowed
    ): bool {
        foreach ($allowed as $candidate) {
            if (
                $this->sameText(
                    $value,
                    $candidate
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function sameText(
        string $a,
        string $b
    ): bool {
        return $this->comparable($a)
            === $this->comparable($b);
    }

    private function comparable(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value)
        );

        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i',
            'ó'=>'o','ú'=>'u','ü'=>'u',
            'ñ'=>'n',
        ]);

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;
    }

    private function normalizeHeader(
        mixed $value
    ): string {
        $text = mb_strtoupper(
            trim((string) $value)
        );

        $text = strtr($text, [
            'Á'=>'A','É'=>'E','Í'=>'I',
            'Ó'=>'O','Ú'=>'U','Ü'=>'U',
            'Ñ'=>'N',
        ]);

        return preg_replace(
            '/\s+/u',
            ' ',
            $text
        ) ?? $text;
    }

    private function rowIsEmpty(
        Worksheet $sheet,
        int $row
    ): bool {
        for ($col = 1; $col <= 35; $col++) {
            if (
                trim(
                    (string) $sheet
                        ->getCell([$col, $row])
                        ->getValue()
                ) !== ''
            ) {
                return false;
            }
        }

        return true;
    }
}
