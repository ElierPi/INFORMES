<?php

namespace App\Services\Informe202;

use App\Services\Informe202\Engine\RuleDecision;
use App\Services\Informe202\Engine\RuleEngine;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;



class Informe202CorrectionService
{
    public function __construct(
        private RuleEngine $ruleEngine
    ) {
    }

    /**
     * Procesa los errores reportados por la EPS y genera
     * una copia corregida del informe 202.
     */
    public function correct(
        string $inputPath,
        string $outputPath,
        array $errors,
        array $excelResult
    ): array {
        if (! is_file($inputPath)) {
            throw new RuntimeException(
                'No se encontró el Excel original de la 202.'
            );
        }

        if ($errors === []) {
            throw new RuntimeException(
                'El reporte de la EPS no contiene errores reconocibles.'
            );
        }

        $spreadsheet = IOFactory::load($inputPath);

        $sheetName = $excelResult['sheet_name']
            ?? config(
                'resolucion202.meta.sheet_name',
                'ESTRUCTURA'
            );

        $sheet = $spreadsheet->getSheetByName(
            $sheetName
        );

        if (! $sheet instanceof Worksheet) {
            throw new RuntimeException(
                "No se encontró la hoja {$sheetName}."
            );
        }

        $records = $excelResult['records'] ?? [];

        $variableColumns =
            $excelResult['variable_columns'] ?? [];

        if ($records === []) {
            throw new RuntimeException(
                'No se encontraron registros en la hoja ESTRUCTURA.'
            );
        }

        if ($variableColumns === []) {
            throw new RuntimeException(
                'No se encontró el mapa de columnas de las variables 0 a 118.'
            );
        }

        /*
         * Índice de registros usando el consecutivo de la variable 1.
         */
$recordIndexes = $this->indexRecords(
    $records
);

        $corrections = [];
        $pending = [];
        $valid = [];

        /*
         * Guarda las celdas que ya fueron modificadas.
         */
        $processedCells = [];

        foreach ($errors as $error) {
            $recordKey = $this->findRecordKey(
    error: $error,
    recordIndexes: $recordIndexes
);

if ($recordKey === null) {
    $pending[] = [
        ...$error,
        'estado' => 'manual',
        'variable' => $error['variable'] ?? null,
        'valor_actual' => null,
        'detalle' =>
            'No se encontró un registro coincidente en el Excel '
            . 'por tipo y número de identificación, ni por consecutivo.',
    ];

    continue;
}

$record = &$recordIndexes['records'][$recordKey];
            $decision = $this->ruleEngine->resolve(
                $record,
                $error
            );

            if ($decision->status === 'automatic') {
                $result = $this->applyAutomaticDecision(
                    sheet: $sheet,
                    record: $record,
                    error: $error,
                    decision: $decision,
                    variableColumns: $variableColumns,
                    processedCells: $processedCells
                );

                if ($result['aplicado'] === true) {
                    $corrections[] = $result['detalle'];
                } elseif (
                    isset($result['pendiente'])
                    && is_array($result['pendiente'])
                ) {
                    $pending[] = $result['pendiente'];
                } elseif (
                    isset($result['valido'])
                    && is_array($result['valido'])
                ) {
                    $valid[] = $result['valido'];
                }

                unset($record);

                continue;
            }

            if ($decision->status === 'valid') {
                $valid[] = $this->buildResult(
                    record: $record,
                    error: $error,
                    decision: $decision
                );

                unset($record);

                continue;
            }

            $pending[] = $this->buildResult(
                record: $record,
                error: $error,
                decision: $decision
            );

            unset($record);
        }

$this->createAuditSheet(
    spreadsheet: $spreadsheet,
    corrections: $corrections,
    pending: $pending,
    valid: $valid
);

$this->saveWorkbook(
    spreadsheet: $spreadsheet,
    outputPath: $outputPath
);

        return [
            'correcciones' => array_values(
                $corrections
            ),

            'pendientes' => array_values(
                $pending
            ),

            'validos' => array_values(
                $valid
            ),

            'archivo_corregido' => $outputPath,

            'total_correcciones' => count(
                $corrections
            ),

            'total_pendientes' => count(
                $pending
            ),

            'total_validos' => count(
                $valid
            ),
        ];

        
    }

    /**
     * Aplica una corrección automática en una celda real del Excel.
     */
    private function applyAutomaticDecision(
        Worksheet $sheet,
        array &$record,
        array $error,
        RuleDecision $decision,
        array $variableColumns,
        array &$processedCells
    ): array {
        $variable = $decision->variable;

        if ($variable === null) {
            return [
                'aplicado' => false,

                'pendiente' => $this->buildResult(
                    record: $record,
                    error: $error,
                    decision: RuleDecision::manual(
                        variable: null,
                        currentValue:
                            $decision->currentValue,
                        reason:
                            'La regla indicó una corrección, pero no '
                            . 'informó la variable afectada.',
                        rule: self::class
                    )
                ),
            ];
        }

        if (! isset($variableColumns[$variable])) {
            return [
                'aplicado' => false,

                'pendiente' => $this->buildResult(
                    record: $record,
                    error: $error,
                    decision: RuleDecision::manual(
                        variable: $variable,
                        currentValue:
                            $decision->currentValue,
                        reason:
                            "No se encontró la columna correspondiente "
                            . "a la variable {$variable}.",
                        rule: self::class
                    )
                ),
            ];
        }

        $excelRow = (int) (
            $record['excel_row'] ?? 0
        );

        if ($excelRow <= 0) {
            return [
                'aplicado' => false,

                'pendiente' => $this->buildResult(
                    record: $record,
                    error: $error,
                    decision: RuleDecision::manual(
                        variable: $variable,
                        currentValue:
                            $decision->currentValue,
                        reason:
                            'No se encontró la fila real del registro '
                            . 'dentro del Excel.',
                        rule: self::class
                    )
                ),
            ];
        }

        $column = $variableColumns[$variable];

        $coordinate = "{$column}{$excelRow}";

        /*
         * Evita corregir dos veces exactamente la misma celda.
         */
        if (isset($processedCells[$coordinate])) {
            return [
                'aplicado' => false,

                'valido' => [
                    ...$this->buildResult(
                        record: $record,
                        error: $error,
                        decision: RuleDecision::valid(
                            variable: $variable,
                            currentValue:
                                $record['variables'][$variable]
                                ?? null,
                            reason:
                                'La celda ya fue corregida durante '
                                . 'este mismo procesamiento.',
                            rule: self::class
                        )
                    ),

                    'celda' => $coordinate,
                ],
            ];
        }

        $cell = $sheet->getCell($coordinate);

        $oldValue = $cell->getValue();

        $newValue = $decision->newValue;

        if ($this->valuesAreEqual(
            $oldValue,
            $newValue
        )) {
            $processedCells[$coordinate] = true;

            /*
             * Actualiza también el registro leído.
             */
            $record['variables'][$variable] =
                $newValue;

            return [
                'aplicado' => false,

                'valido' => [
                    ...$this->buildResult(
                        record: $record,
                        error: $error,
                        decision: RuleDecision::valid(
                            variable: $variable,
                            currentValue: $oldValue,
                            reason:
                                'La celda ya contiene el valor correcto.',
                            rule:
                                $decision->rule
                                ?? self::class
                        )
                    ),

                    'celda' => $coordinate,
                ],
            ];
        }

        /*
         * Escribe el valor según el tipo oficial del campo.
         */
        $this->writeValue(
            sheet: $sheet,
            coordinate: $coordinate,
            variable: $variable,
            value: $newValue
        );

        /*
         * Verificación inmediata de escritura.
         */
        $savedValue = $sheet
            ->getCell($coordinate)
            ->getValue();

        if (! $this->valuesAreEqual(
            $savedValue,
            $newValue
        )) {
            return [
                'aplicado' => false,

                'pendiente' => $this->buildResult(
                    record: $record,
                    error: $error,
                    decision: RuleDecision::manual(
                        variable: $variable,
                        currentValue: $oldValue,
                        reason:
                            "No fue posible escribir el nuevo valor "
                            . "en la celda {$coordinate}.",
                        rule: self::class
                    )
                ),
            ];
        }

        /*
         * Actualiza el registro en memoria para que una regla posterior
         * vea el valor recién corregido.
         */
        $record['variables'][$variable] =
            $newValue;

        $processedCells[$coordinate] = true;

        $definition = config(
            "resolucion202.fields.{$variable}",
            []
        );

        return [
            'aplicado' => true,

            'detalle' => [
                'codigo' =>
                    $error['codigo'] ?? null,

                'registro' =>
                    $record['record_number'] ?? null,

                'fila' =>
                    $record['record_number'] ?? null,

                'fila_excel' =>
                    $excelRow,

                'variable' =>
                    $variable,

                'campo' =>
                    $definition['name']
                    ?? $error['campo']
                    ?? "Variable {$variable}",

                'celda' =>
                    $coordinate,

                'valor_anterior' =>
                    $this->normalizeDisplayValue(
                        $oldValue
                    ),

                'valor_nuevo' =>
                    $newValue,

                'motivo' =>
                    $decision->reason,

                'detalle' =>
                    $decision->reason,

                'regla' =>
                    $decision->rule,

                'edad' =>
                    $record['age'] ?? null,

                'estado' =>
                    'automatic',
            ],
        ];
    }

    /**
     * Escribe respetando el tipo definido en la Resolución 202.
     */
    private function writeValue(
        Worksheet $sheet,
        string $coordinate,
        int $variable,
        mixed $value
    ): void {
        $definition = config(
            "resolucion202.fields.{$variable}",
            []
        );

        $type = $definition['type'] ?? null;

        /*
         * La variable 90 contiene el código de habilitación REPS
         * de la IPS donde se realiza el tamizaje de cuello uterino.
         *
         * Aunque está formado solo por números, no debe escribirse
         * como valor numérico porque Excel puede convertirlo a
         * notación científica, por ejemplo:
         *
         * 444300063502 -> 4,443E+11
         *
         * Se fuerza siempre como texto para conservar exactamente
         * los 12 dígitos.
         */
        if ($variable === 90) {
            $sheet->setCellValueExplicit(
                $coordinate,
                (string) $value,
                DataType::TYPE_STRING
            );

            $sheet->getStyle($coordinate)
                ->getNumberFormat()
                ->setFormatCode('@');

            return;
        }

        /*
         * Fechas y campos alfanuméricos deben conservarse como texto.
         */
        if (in_array($type, ['F', 'A', 'T'], true)) {
            $sheet->setCellValueExplicit(
                $coordinate,
                (string) $value,
                DataType::TYPE_STRING
            );

            return;
        }

        /*
         * Numéricos enteros.
         */
        if (
            $type === 'N'
            && is_numeric($value)
        ) {
            $sheet->setCellValueExplicit(
                $coordinate,
                (int) $value,
                DataType::TYPE_NUMERIC
            );

            return;
        }

        /*
         * Decimales.
         */
        if (
            $type === 'D'
            && is_numeric($value)
        ) {
            $sheet->setCellValueExplicit(
                $coordinate,
                (float) $value,
                DataType::TYPE_NUMERIC
            );

            return;
        }

        /*
         * Respaldo para cualquier otro valor.
         */
        $sheet->setCellValueExplicit(
            $coordinate,
            (string) $value,
            DataType::TYPE_STRING
        );
    }

    private function buildResult(
        array $record,
        array $error,
        RuleDecision $decision
    ): array {
        $variable = $decision->variable;

        $definition = $variable !== null
            ? config(
                "resolucion202.fields.{$variable}",
                []
            )
            : [];

        return [
            'codigo' =>
                $error['codigo'] ?? null,

            'registro' =>
                $record['record_number']
                ?? $error['fila']
                ?? null,

            'fila' =>
                $record['record_number']
                ?? $error['fila']
                ?? null,

            'fila_excel' =>
                $record['excel_row'] ?? null,

            'variable' =>
                $variable,

            'campo' =>
                $definition['name']
                ?? $error['campo']
                ?? null,

            'mensaje_eps' =>
                $error['mensaje'] ?? null,

            'valor_actual' =>
                $decision->currentValue,

            'valor_nuevo' =>
                $decision->newValue,

            'detalle' =>
                $decision->reason,

            'motivo' =>
                $decision->reason,

            'regla' =>
                $decision->rule,

            'edad' =>
                $record['age'] ?? null,

            'estado' =>
                $decision->status,
        ];
    }

    /**
     * Crea un índice por consecutivo del registro.
     */
private function indexRecords(
    array $records
): array {
    $indexedRecords = [];
    $byIdentification = [];
    $byRecordNumber = [];

    foreach ($records as $index => $record) {
        $recordKey = (string) $index;

        $indexedRecords[$recordKey] = $record;

        /*
         * Variable 3 = tipo de identificación.
         * Variable 4 = número de identificación.
         */
        $identificationType = $this->normalizeIdentificationType(
            $record['variables'][3] ?? null
        );

        $identificationNumber = $this->normalizeIdentificationNumber(
            $record['variables'][4] ?? null
        );

        if (
            $identificationType !== ''
            && $identificationNumber !== ''
        ) {
            $identificationKey =
                "{$identificationType}|{$identificationNumber}";

            $byIdentification[$identificationKey] =
                $recordKey;
        }

        /*
         * Variable 1 = consecutivo del registro.
         */
        $recordNumber = $record['record_number']
            ?? $record['variables'][1]
            ?? null;

        if (
            $recordNumber !== null
            && trim((string) $recordNumber) !== ''
        ) {
            $byRecordNumber[
                trim((string) $recordNumber)
            ] = $recordKey;
        }
    }

    return [
        'records' => $indexedRecords,
        'by_identification' => $byIdentification,
        'by_record_number' => $byRecordNumber,
    ];

    
}

private function extractRecordNumber(
    array $error
): ?string {
    $value = $error['registro']
        ?? $error['fila']
        ?? $error['linea']
        ?? $error['consecutivo']
        ?? null;

    if (
        $value === null
        || trim((string) $value) === ''
    ) {
        return null;
    }

    return trim((string) $value);
}

    /**
     * Guarda el libro y comprueba que realmente fue creado.
     */
    private function saveWorkbook(
        Spreadsheet $spreadsheet,
        string $outputPath
    ): void {
        $directory = dirname($outputPath);

        if (! is_dir($directory)) {
            $created = mkdir(
                $directory,
                0755,
                true
            );

            if (! $created && ! is_dir($directory)) {
                throw new RuntimeException(
                    'No fue posible crear la carpeta del archivo corregido.'
                );
            }
        }

        $spreadsheet->setActiveSheetIndex(
            $spreadsheet->getIndex(
                $spreadsheet->getSheetByName(
                    config(
                        'resolucion202.meta.sheet_name',
                        'ESTRUCTURA'
                    )
                )
            )
        );

        $writer = IOFactory::createWriter(
            $spreadsheet,
            'Xlsx'
        );

        $writer->setPreCalculateFormulas(
            false
        );

        $writer->save($outputPath);

        if (
            ! is_file($outputPath)
            || filesize($outputPath) === 0
        ) {
            throw new RuntimeException(
                'El archivo corregido no fue guardado correctamente.'
            );
        }
    }

    private function valuesAreEqual(
        mixed $first,
        mixed $second
    ): bool {
        return trim((string) $first)
            === trim((string) $second);
    }

    private function normalizeDisplayValue(
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }
    private function createAuditSheet(
    Spreadsheet $spreadsheet,
    array $corrections,
    array $pending,
    array $valid
): void {
    $existingSheet = $spreadsheet->getSheetByName(
        'AUDITORIA'
    );

    if ($existingSheet) {
        $spreadsheet->removeSheetByIndex(
            $spreadsheet->getIndex($existingSheet)
        );
    }

    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('AUDITORIA');

    $headers = [
        'A1' => 'ESTADO',
        'B1' => 'CÓDIGO EPS',
        'C1' => 'REGISTRO',
        'D1' => 'FILA EXCEL',
        'E1' => 'VARIABLE',
        'F1' => 'CAMPO',
        'G1' => 'CELDA',
        'H1' => 'VALOR ANTERIOR',
        'I1' => 'VALOR NUEVO',
        'J1' => 'MOTIVO',
        'K1' => 'REGLA APLICADA',
        'L1' => 'EDAD EN AÑOS',
        'M1' => 'EDAD EN MESES',
        'N1' => 'MENSAJE DE LA EPS',
    ];

    foreach ($headers as $coordinate => $value) {
        $sheet->setCellValue($coordinate, $value);
    }

    $sheet->getStyle('A1:N1')->getFont()->setBold(true);

    $rows = [];

    foreach ($corrections as $item) {
        $rows[] = [
            'AUTOMÁTICO',
            $item['codigo'] ?? null,
            $item['registro'] ?? null,
            $item['fila_excel'] ?? null,
            $item['variable'] ?? null,
            $item['campo'] ?? null,
            $item['celda'] ?? null,
            $item['valor_anterior'] ?? null,
            $item['valor_nuevo'] ?? null,
            $item['motivo'] ?? null,
            $item['regla'] ?? null,
            $item['edad']['years'] ?? null,
            $item['edad']['months'] ?? null,
            $item['mensaje_eps'] ?? null,
        ];
    }

    foreach ($pending as $item) {
        $rows[] = [
            'MANUAL',
            $item['codigo'] ?? null,
            $item['registro'] ?? $item['fila'] ?? null,
            $item['fila_excel'] ?? null,
            $item['variable'] ?? null,
            $item['campo'] ?? null,
            $item['celda'] ?? null,
            $item['valor_actual'] ?? null,
            null,
            $item['detalle'] ?? $item['motivo'] ?? null,
            $item['regla'] ?? null,
            $item['edad']['years'] ?? null,
            $item['edad']['months'] ?? null,
            $item['mensaje_eps'] ?? $item['mensaje'] ?? null,
        ];
    }

    foreach ($valid as $item) {
        $rows[] = [
            'YA VÁLIDO',
            $item['codigo'] ?? null,
            $item['registro'] ?? $item['fila'] ?? null,
            $item['fila_excel'] ?? null,
            $item['variable'] ?? null,
            $item['campo'] ?? null,
            $item['celda'] ?? null,
            $item['valor_actual'] ?? null,
            null,
            $item['detalle'] ?? $item['motivo'] ?? null,
            $item['regla'] ?? null,
            $item['edad']['years'] ?? null,
            $item['edad']['months'] ?? null,
            $item['mensaje_eps'] ?? null,
        ];
    }

    $rowNumber = 2;

    foreach ($rows as $row) {
        $columnNumber = 1;

        foreach ($row as $value) {
            $sheet->setCellValue(
                [$columnNumber, $rowNumber],
                $value
            );

            $columnNumber++;
        }

        $rowNumber++;
    }

    $sheet->freezePane('A2');
    $sheet->setAutoFilter(
        "A1:N" . max(1, $rowNumber - 1)
    );

    foreach (range('A', 'N') as $column) {
        $sheet->getColumnDimension($column)
            ->setAutoSize(true);
    }

    $sheet->getStyle(
        "J2:J" . max(2, $rowNumber - 1)
    )->getAlignment()->setWrapText(true);

    $sheet->getStyle(
        "N2:N" . max(2, $rowNumber - 1)
    )->getAlignment()->setWrapText(true);
}

private function findRecordKey(
    array $error,
    array $recordIndexes
): ?string {
    /*
     * Primera opción:
     * tipo y número de identificación.
     */
    $identificationType =
        $this->normalizeIdentificationType(
            $error['tipo_identificacion'] ?? null
        );

    $identificationNumber =
        $this->normalizeIdentificationNumber(
            $error['identificacion'] ?? null
        );

    if (
        $identificationType !== ''
        && $identificationNumber !== ''
    ) {
        $identificationKey =
            "{$identificationType}|{$identificationNumber}";

        if (
            isset(
                $recordIndexes['by_identification'][
                    $identificationKey
                ]
            )
        ) {
            return $recordIndexes['by_identification'][
                $identificationKey
            ];
        }
    }

    /*
     * Segunda opción:
     * consecutivo o número de línea reportado por la EPS.
     */
    $recordNumber = $this->extractRecordNumber(
        $error
    );

    if (
        $recordNumber !== null
        && isset(
            $recordIndexes['by_record_number'][
                $recordNumber
            ]
        )
    ) {
        return $recordIndexes['by_record_number'][
            $recordNumber
        ];
    }

    return null;
}

private function normalizeIdentificationType(
    mixed $value
): string {
    return strtoupper(
        trim((string) $value)
    );
}

private function normalizeIdentificationNumber(
    mixed $value
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    /*
     * Corrige números que Excel puede devolver como:
     * 1124026254.0
     */
    if (preg_match('/^\d+\.0+$/', $value)) {
        $value = preg_replace(
            '/\.0+$/',
            '',
            $value
        ) ?? $value;
    }

    /*
     * Conserva únicamente letras y números.
     */
    return strtoupper(
        preg_replace(
            '/[^A-Za-z0-9]/',
            '',
            $value
        ) ?? ''
    );
}

}