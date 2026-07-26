<?php

namespace App\Services\Gestantes;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class GestantesErrorCorrectionService
{
    private const SENTINEL_DATE = '1845-01-01';

    public function correct(
        string $inputPath,
        string $outputPath,
        array $errors
    ): array {
        if (! is_file($inputPath)) {
            throw new RuntimeException(
                'No se encontró el Excel original.'
            );
        }

        if ($errors === []) {
            throw new RuntimeException(
                'No existen errores analizados para corregir.'
            );
        }

        try {
            $spreadsheet = IOFactory::load($inputPath);

            /*
             * Relaciona cada línea del TXT con:
             *
             * - tipo de registro
             * - hoja del Excel
             * - fila física del Excel
             */
            $txtRecordMap = $this->buildTxtRecordMap(
                $spreadsheet
            );

            $changes = [];
            $manual = [];

            foreach ($errors as $error) {
                $result = $this->processError(
                    spreadsheet: $spreadsheet,
                    txtRecordMap: $txtRecordMap,
                    error: $error
                );

                if (($result['status'] ?? null) === 'corrected') {
                    /*
                     * Una regla puede modificar más de una celda.
                     */
                    foreach ($result['changes'] as $change) {
                        $changes[] = $change;
                    }

                    continue;
                }

                $manual[] = $result;
            }

            $directory = dirname($outputPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            IOFactory::createWriter(
                $spreadsheet,
                'Xlsx'
            )->save($outputPath);

            return [
                'output_path' => $outputPath,
                'changes' => $changes,
                'manual' => $manual,
                'total' => count($errors),
                'corrected' => count($changes),
                'pending' => count($manual),
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible corregir el Excel: '
                . $exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * Construye la equivalencia entre la línea del TXT
     * y la fila real del Excel.
     */
    private function buildTxtRecordMap(
        Spreadsheet $spreadsheet
    ): array {
        $map = [];
        $txtRow = 1;

        $sheets = config(
            'gestantes_sigires.sheets',
            []
        );

        foreach ($sheets as $recordType => $sheetName) {
            $worksheet = $spreadsheet->getSheetByName(
                $sheetName
            );

            if (! $worksheet instanceof Worksheet) {
                continue;
            }

            $highestRow = $worksheet->getHighestDataRow();

            for ($excelRow = 2; $excelRow <= $highestRow; $excelRow++) {
                if ($this->isEmptyExcelRow($worksheet, $excelRow)) {
                    continue;
                }

                $value = $this->plainValue(
                    $worksheet->getCell([1, $excelRow])->getValue()
                );

                if (
                    $value === ''
                    || ! is_numeric($value)
                    || (int) $value !== (int) $recordType
                ) {
                    continue;
                }

                $map[$txtRow] = [
                    'txt_row' => $txtRow,
                    'record_type' => (int) $recordType,
                    'sheet' => $sheetName,
                    'excel_row' => $excelRow,
                ];

                $txtRow++;
            }
        }

        if ($map === []) {
            throw new RuntimeException(
                'No fue posible identificar los registros del informe.'
            );
        }

        return $map;
    }

    private function processError(
        Spreadsheet $spreadsheet,
        array $txtRecordMap,
        array $error
    ): array {
        $txtRow = $this->nullableInteger(
            $error['row'] ?? null
        );

        $reportedVariable = $this->nullableInteger(
            $error['column'] ?? null
        );

        if ($txtRow === null || $txtRow < 1) {
            return $this->manualResult(
                error: $error,
                reason: 'SIGIRES no informó una fila válida.'
            );
        }

        if ($reportedVariable === null || $reportedVariable < 1) {
            return $this->manualResult(
                error: $error,
                reason: 'SIGIRES no informó una variable válida.'
            );
        }

        $record = $txtRecordMap[$txtRow] ?? null;

        if ($record === null) {
            return $this->manualResult(
                error: $error,
                reason:
                    "La línea {$txtRow} del TXT no fue encontrada en el Excel."
            );
        }

        $recordType = (int) $record['record_type'];

        $field = config(
            "gestantes_sigires.fields.{$recordType}.{$reportedVariable}"
        );

        if (! is_array($field)) {
            return $this->manualResult(
                error: $error,
                reason:
                    "La variable {$reportedVariable} del registro "
                    . "tipo {$recordType} no está configurada.",
                record: $record
            );
        }

        $worksheet = $spreadsheet->getSheetByName(
            $record['sheet']
        );

        if (! $worksheet instanceof Worksheet) {
            return $this->manualResult(
                error: $error,
                reason:
                    "No se encontró la hoja {$record['sheet']}.",
                record: $record
            );
        }

        /*
         * Ya no usamos la columna física reportada directamente.
         * Buscamos el campo mediante su encabezado oficial.
         */
        $excelColumn = $this->findColumnByHeader(
            worksheet: $worksheet,
            expectedHeader: $field['header']
        );

        if ($excelColumn === null) {
            return $this->manualResult(
                error: $error,
                reason:
                    'No se encontró el encabezado: '
                    . $field['header'],
                record: $record
            );
        }

        $excelRow = (int) $record['excel_row'];

        return $this->applyFieldRule(
            worksheet: $worksheet,
            record: $record,
            excelRow: $excelRow,
            excelColumn: $excelColumn,
            reportedVariable: $reportedVariable,
            field: $field,
            error: $error
        );
    }

    private function applyFieldRule(
        Worksheet $worksheet,
        array $record,
        int $excelRow,
        int $excelColumn,
        int $reportedVariable,
        array $field,
        array $error
    ): array {
        $fieldKey = (string) $field['key'];

        return match ($fieldKey) {
            'direccion_residencia' =>
                $this->correctAddress(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error
                ),

            'fecha_anticonceptivo' =>
                $this->correctSingleValue(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error,
                    newValue: self::SENTINEL_DATE,
                    valueType: 'date',
                    reason:
                        'Se asignó la fecha centinela de anticoncepción.'
                ),

            'suministro_anticonceptivo' =>
                $this->correctContraceptiveSupply(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error
                ),

            'fecha_terminacion_gestacion' =>
                $this->correctSingleValue(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error,
                    newValue: self::SENTINEL_DATE,
                    valueType: 'date',
                    reason:
                        'Se asignó 1845-01-01 como fecha centinela de terminación.'
                ),

            'tipo_terminacion_gestacion' =>
                $this->correctSingleValue(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error,
                    newValue: 0,
                    valueType: 'number',
                    reason:
                        'Se asignó 0 como tipo de terminación.'
                ),

            default => $this->manualResult(
                error: $error,
                reason:
                    "No existe una regla para el campo {$fieldKey}.",
                record: $record,
                excelColumn: $excelColumn
            ),
        };
    }

    /**
     * Corrige conjuntamente:
     *
     * - Fecha de suministro
     * - Código de suministro
     */
    private function correctContraceptiveSupply(
        Worksheet $worksheet,
        array $record,
        int $excelRow,
        int $excelColumn,
        int $reportedVariable,
        array $field,
        array $error
    ): array {
        $changes = [];

        /*
         * Campo reportado: suministro de anticonceptivo.
         */
        $changes[] = $this->changeCell(
            worksheet: $worksheet,
            record: $record,
            excelRow: $excelRow,
            excelColumn: $excelColumn,
            reportedVariable: $reportedVariable,
            field: $field,
            error: $error,
            newValue: 0,
            valueType: 'number',
            reason:
                'Se asignó 0 al suministro de anticonceptivo.'
        );

        /*
         * Campo relacionado: fecha de suministro.
         */
        $dateField = config(
            'gestantes_sigires.fields.3.14'
        );

        if (is_array($dateField)) {
            $dateColumn = $this->findColumnByHeader(
                worksheet: $worksheet,
                expectedHeader: $dateField['header']
            );

            if ($dateColumn !== null) {
                $changes[] = $this->changeCell(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $dateColumn,
                    reportedVariable: 14,
                    field: $dateField,
                    error: $error,
                    newValue: self::SENTINEL_DATE,
                    valueType: 'date',
                    reason:
                        'Se asignó 1845-01-01 a la fecha relacionada.'
                );
            }
        }

        return [
            'status' => 'corrected',
            'changes' => $changes,
        ];
    }

    private function correctAddress(
        Worksheet $worksheet,
        array $record,
        int $excelRow,
        int $excelColumn,
        int $reportedVariable,
        array $field,
        array $error
    ): array {
        $oldValue = $this->plainValue(
            $worksheet->getCell([
                $excelColumn,
                $excelRow,
            ])->getValue()
        );

        $suggestedValue = trim(
            (string) ($error['suggested_value'] ?? '')
        );

        $newValue = $this->normalizeAddress(
            currentValue: $oldValue,
            suggestedValue: $suggestedValue
        );

        if ($newValue === '') {
            return $this->manualResult(
                error: $error,
                reason:
                    'La dirección está vacía y no puede corregirse automáticamente.',
                record: $record,
                excelColumn: $excelColumn
            );
        }

        return [
            'status' => 'corrected',
            'changes' => [
                $this->changeCell(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error,
                    newValue: $newValue,
                    valueType: 'text',
                    reason:
                        'Se normalizó la nomenclatura de la dirección.'
                ),
            ],
        ];
    }

    private function correctSingleValue(
        Worksheet $worksheet,
        array $record,
        int $excelRow,
        int $excelColumn,
        int $reportedVariable,
        array $field,
        array $error,
        mixed $newValue,
        string $valueType,
        string $reason
    ): array {
        return [
            'status' => 'corrected',
            'changes' => [
                $this->changeCell(
                    worksheet: $worksheet,
                    record: $record,
                    excelRow: $excelRow,
                    excelColumn: $excelColumn,
                    reportedVariable: $reportedVariable,
                    field: $field,
                    error: $error,
                    newValue: $newValue,
                    valueType: $valueType,
                    reason: $reason
                ),
            ],
        ];
    }

    private function changeCell(
        Worksheet $worksheet,
        array $record,
        int $excelRow,
        int $excelColumn,
        int $reportedVariable,
        array $field,
        array $error,
        mixed $newValue,
        string $valueType,
        string $reason
    ): array {
        $cell = $worksheet->getCell([
            $excelColumn,
            $excelRow,
        ]);

        $oldValue = $this->plainValue(
            $cell->getValue()
        );

        $this->writeValue(
            worksheet: $worksheet,
            excelRow: $excelRow,
            excelColumn: $excelColumn,
            value: $newValue,
            type: $valueType
        );

        return [
            'status' => 'corrected',
            'source_row' => $error['source_row'] ?? null,
            'report_row' => $error['row'] ?? null,
            'report_column' => $reportedVariable,
            'record_type' => $record['record_type'],
            'sheet' => $record['sheet'],
            'excel_row' => $excelRow,
            'excel_column' => $excelColumn,
            'cell' => Coordinate::stringFromColumnIndex(
                $excelColumn
            ) . $excelRow,
            'field_key' => $field['key'],
            'field' => $field['header'],
            'old_value' => $oldValue,
            'new_value' => (string) $newValue,
            'rule' => $error['rule'] ?? null,
            'description' => $error['description'] ?? '',
            'reason' => $reason,
        ];
    }

    /**
     * Encuentra la columna comparando el encabezado normalizado.
     */
    private function findColumnByHeader(
        Worksheet $worksheet,
        string $expectedHeader
    ): ?int {
        $expected = $this->normalizeHeader(
            $expectedHeader
        );

        $highestColumn = Coordinate::columnIndexFromString(
            $worksheet->getHighestDataColumn()
        );

        for ($column = 1; $column <= $highestColumn; $column++) {
            $header = $this->plainValue(
                $worksheet->getCell([
                    $column,
                    1,
                ])->getValue()
            );

            if (
                $this->normalizeHeader($header)
                === $expected
            ) {
                return $column;
            }
        }

        return null;
    }

    private function writeValue(
        Worksheet $worksheet,
        int $excelRow,
        int $excelColumn,
        mixed $value,
        string $type
    ): void {
        $cell = $worksheet->getCell([
            $excelColumn,
            $excelRow,
        ]);

        /*
         * Se escribe como texto ISO para impedir que Excel,
         * PhpSpreadsheet o la zona horaria cambien 1845-01-01
         * por 1845-01-02.
         */
        if ($type === 'date') {
            $cell->setValueExplicit(
                (string) $value,
                DataType::TYPE_STRING
            );

            $cell->getStyle()
                ->getNumberFormat()
                ->setFormatCode('@');

            return;
        }

        if ($type === 'number') {
            $cell->setValue(
                (int) $value
            );

            $cell->getStyle()
                ->getNumberFormat()
                ->setFormatCode('0');

            return;
        }

        $cell->setValueExplicit(
            (string) $value,
            DataType::TYPE_STRING
        );
    }

    private function normalizeAddress(
        string $currentValue,
        string $suggestedValue = ''
    ): string {
        $address = trim($currentValue);

        if ($address === '') {
            $address = trim($suggestedValue);
        }

        if ($address === '') {
            return '';
        }

        $address = mb_strtoupper($address);

        $address = strtr($address, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        $address = preg_replace(
            '/[^A-Z0-9#\-\s]/u',
            ' ',
            $address
        ) ?? $address;

        $address = preg_replace(
            '/\s+/u',
            ' ',
            $address
        ) ?? $address;

        $address = preg_replace(
            '/\b(CALLE|CARRERA|CRA|CL|AVENIDA|AV|TRANSVERSAL|TV|DIAGONAL|DG)'
            . '\s+(\d+[A-Z]?)\s+(\d+[A-Z]?\s*-\s*\d+[A-Z]?)/u',
            '$1 $2 # $3',
            $address
        ) ?? $address;

        $address = preg_replace(
            '/\s*#\s*/u',
            ' # ',
            $address
        ) ?? $address;

        $address = preg_replace(
            '/\s*-\s*/u',
            '-',
            $address
        ) ?? $address;

        $address = preg_replace(
            '/\s+/u',
            ' ',
            $address
        ) ?? $address;

        return trim($address);
    }

    private function normalizeHeader(
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

        $value = preg_replace(
            '/[^a-z0-9]+/u',
            ' ',
            $value
        ) ?? $value;

        return trim(
            preg_replace('/\s+/u', ' ', $value) ?? $value
        );
    }

    private function isEmptyExcelRow(
        Worksheet $worksheet,
        int $row
    ): bool {
        $highestColumn = Coordinate::columnIndexFromString(
            $worksheet->getHighestDataColumn()
        );

        for ($column = 1; $column <= $highestColumn; $column++) {
            if (
                $this->plainValue(
                    $worksheet->getCell([
                        $column,
                        $row,
                    ])->getValue()
                ) !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    private function plainValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function nullableInteger(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
            || ! is_numeric($value)
        ) {
            return null;
        }

        return (int) $value;
    }

    private function manualResult(
        array $error,
        string $reason,
        ?array $record = null,
        ?int $excelColumn = null
    ): array {
        $cell = null;

        if (
            $record !== null
            && $excelColumn !== null
        ) {
            $cell = Coordinate::stringFromColumnIndex(
                $excelColumn
            ) . $record['excel_row'];
        }

        return [
            'status' => 'manual',
            'source_row' => $error['source_row'] ?? null,
            'report_row' => $error['row'] ?? null,
            'report_column' => $error['column'] ?? null,
            'record_type' => $record['record_type'] ?? null,
            'sheet' => $record['sheet'] ?? null,
            'excel_row' => $record['excel_row'] ?? null,
            'excel_column' => $excelColumn,
            'cell' => $cell,
            'old_value' => (string) ($error['old_value'] ?? ''),
            'new_value' => null,
            'rule' => $error['rule'] ?? null,
            'description' => $error['description'] ?? '',
            'reason' => $reason,
        ];
    }
}