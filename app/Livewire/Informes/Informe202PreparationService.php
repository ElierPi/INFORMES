<?php

namespace App\Services\Informe202;

use RuntimeException;

final class Informe202PreparationService
{
    public const DESTINATION_PROTEGER = 'proteger';

    public const DESTINATION_DUSAKAWI = 'dusakawi';

    public function __construct(
        private readonly Resolution202ExcelReader $excelReader
    ) {
    }

    /**
     * Prepara un archivo de la Resolución 202 sin bloquearlo por reglas
     * clínicas. En esta fase solo se controlan errores estructurales.
     *
     * @return array{
     *     records: array<int, array<int, string>>,
     *     errors: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     detected_header: array<string, mixed>|null
     * }
     */
    public function prepare(
        string $inputPath,
        string $extension,
        string $destination,
        ?string $cutoffDate = null
    ): array {
        if (! is_file($inputPath)) {
            throw new RuntimeException(
                'No se encontró el archivo seleccionado.'
            );
        }

        $destination = $this->normalizeDestination(
            $destination
        );

        $extension = mb_strtolower(
            trim($extension)
        );

        $source = match ($extension) {
            'xlsx', 'xls' => [
                'records' => $this->readExcel(
                    $inputPath,
                    $cutoffDate
                ),
                'detected_header' => null,
            ],

            'txt' => $this->readTxt($inputPath),

            default => throw new RuntimeException(
                'Solo se permiten archivos XLSX, XLS o TXT.'
            ),
        };

        $records = $source['records'] ?? [];

        if ($records === []) {
            throw new RuntimeException(
                'El archivo no contiene registros tipo 2 para procesar.'
            );
        }

        $normalizedRecords = [];
        $errors = [];
        $warnings = [];

        foreach ($records as $index => $record) {
            $sourceLine = (int) (
                $record['source_line']
                ?? $record['excel_row']
                ?? ($index + 1)
            );

            $values = $record['values']
                ?? $record['variables']
                ?? $record;

            if (! is_array($values)) {
                $errors[] = $this->issue(
                    record: $index + 1,
                    sourceLine: $sourceLine,
                    field: 'Estructura',
                    value: null,
                    message:
                        'No fue posible interpretar el registro.'
                );

                continue;
            }

            /*
             * Conserva posiciones vacías y normaliza índices 0 a 118.
             */
            $values = array_values($values);

            if (count($values) !== 119) {
                $errors[] = $this->issue(
                    record: $index + 1,
                    sourceLine: $sourceLine,
                    field: 'Cantidad de campos',
                    value: count($values),
                    message: sprintf(
                        'El registro contiene %d campos y debe contener exactamente 119.',
                        count($values)
                    )
                );

                continue;
            }

            $normalized = [];

            for ($variable = 0; $variable <= 118; $variable++) {
                $normalized[$variable] =
                    $this->normalizeValue(
                        $values[$variable] ?? ''
                    );
            }

            /*
             * La preparación organiza automáticamente los dos primeros
             * campos del registro tipo 2.
             */
            if ($normalized[0] !== '2') {
                $warnings[] = $this->warning(
                    record: $index + 1,
                    sourceLine: $sourceLine,
                    variable: 0,
                    value: $normalized[0],
                    message:
                        'El tipo de registro se ajustó automáticamente a 2.'
                );

                $normalized[0] = '2';
            }

            $expectedSequence = (string) ($index + 1);

            if ($normalized[1] !== $expectedSequence) {
                $warnings[] = $this->warning(
                    record: $index + 1,
                    sourceLine: $sourceLine,
                    variable: 1,
                    value: $normalized[1],
                    message: sprintf(
                        'El consecutivo se organizó automáticamente como %s.',
                        $expectedSequence
                    )
                );

                $normalized[1] = $expectedSequence;
            }

            $normalizedRecords[] = $normalized;
        }

        $invalidRecordNumbers = array_unique(
            array_column($errors, 'record')
        );

        return [
            'records' => $normalizedRecords,

            'errors' => $errors,

            'warnings' => $warnings,

            'detected_header' =>
                $source['detected_header'] ?? null,

            'summary' => [
                'destination' => $destination,

                'records_count' =>
                    count($records),

                'valid_records_count' =>
                    count($normalizedRecords),

                'invalid_records_count' =>
                    count($invalidRecordNumbers),

                'errors_count' =>
                    count($errors),

                'warnings_count' =>
                    count($warnings),

                'separator' => '|',

                'encoding' => 'UTF-8 sin BOM',

                'fields_per_record' => 119,

                'includes_type_1' =>
                    $destination === self::DESTINATION_DUSAKAWI,
            ],
        ];
    }

    /**
     * Genera el TXT final con la estructura propia de cada destino.
     *
     * PROTEGER:
     *   Solo registros tipo 2.
     *
     * DUSAKAWI:
     *   Registro tipo 1 + registros tipo 2.
     */
    public function generateTxt(
        array $records,
        string $outputPath,
        string $destination,
        array $headerData = []
    ): string {
        if ($records === []) {
            throw new RuntimeException(
                'No hay registros válidos para generar el TXT.'
            );
        }

        $destination = $this->normalizeDestination(
            $destination
        );

        $directory = dirname($outputPath);

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de salida.'
            );
        }

        $lines = [];

        if ($destination === self::DESTINATION_DUSAKAWI) {
            $lines[] = $this->buildDusakawiHeader(
                $headerData,
                count($records)
            );
        }

        foreach ($records as $recordIndex => $record) {
            $values = array_values($record);

            if (count($values) !== 119) {
                throw new RuntimeException(
                    sprintf(
                        'El registro %d no contiene 119 campos.',
                        $recordIndex + 1
                    )
                );
            }

            /*
             * Se reafirma el tipo y consecutivo al momento de exportar.
             */
            $values[0] = '2';
            $values[1] = (string) ($recordIndex + 1);

            $lines[] = implode(
                '|',
                array_map(
                    fn (mixed $value): string =>
                        $this->normalizeValue($value),
                    $values
                )
            );
        }

        /*
         * Los modelos entregados no tienen salto de línea adicional
         * después del último registro.
         */
        $content = implode("\r\n", $lines);

        /*
         * UTF-8 sin BOM.
         */
        $content = $this->toUtf8($content);
        $content = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $content
        ) ?? $content;

        if (
            file_put_contents(
                $outputPath,
                $content
            ) === false
        ) {
            throw new RuntimeException(
                'No fue posible generar el TXT de la Resolución 202.'
            );
        }

        return $outputPath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readExcel(
        string $path,
        ?string $cutoffDate
    ): array {
        if (
            $cutoffDate === null
            || trim($cutoffDate) === ''
        ) {
            throw new RuntimeException(
                'La fecha de corte es obligatoria para procesar un Excel.'
            );
        }

        $result = $this->excelReader->read(
            $path,
            $cutoffDate
        );

        return array_map(
            static fn (array $record): array => [
                'excel_row' =>
                    $record['excel_row'] ?? null,

                'values' =>
                    $record['variables'] ?? [],
            ],
            $result['records'] ?? []
        );
    }

    /**
     * Lee TXT de ambos formatos:
     *
     * - PROTEGER: empieza directamente con registros tipo 2.
     * - DUSAKAWI: primera línea tipo 1 con cinco campos.
     *
     * @return array{
     *     records: array<int, array<string, mixed>>,
     *     detected_header: array<string, mixed>|null
     * }
     */
    private function readTxt(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(
                'No fue posible leer el archivo TXT.'
            );
        }

        $content = $this->toUtf8($content);
        $content = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $content
        ) ?? $content;

        $lines = preg_split(
            '/\r\n|\n|\r/',
            $content
        ) ?: [];

        $records = [];
        $detectedHeader = null;

        foreach ($lines as $lineIndex => $line) {
            if (trim($line) === '') {
                continue;
            }

            $sourceLine = $lineIndex + 1;
            $values = explode('|', $line);
            $type = trim((string) ($values[0] ?? ''));

            if ($type === '1') {
                if ($detectedHeader !== null) {
                    throw new RuntimeException(
                        sprintf(
                            'Se encontró más de un registro tipo 1. Línea %d.',
                            $sourceLine
                        )
                    );
                }

                if (count($values) !== 5) {
                    throw new RuntimeException(
                        sprintf(
                            'La línea tipo 1 contiene %d campos y debe contener 5.',
                            count($values)
                        )
                    );
                }

                $detectedHeader = [
                    'type' => '1',
                    'eps_code' =>
                        trim((string) ($values[1] ?? '')),

                    'start_date' =>
                        trim((string) ($values[2] ?? '')),

                    'end_date' =>
                        trim((string) ($values[3] ?? '')),

                    'declared_records' =>
                        trim((string) ($values[4] ?? '')),

                    'source_line' => $sourceLine,
                ];

                continue;
            }

            /*
             * No se descarta una línea por no traer "2": la fase de
             * preparación la corrige automáticamente, siempre que tenga
             * los 119 campos estructurales.
             */
            $records[] = [
                'source_line' => $sourceLine,
                'values' => array_map(
                    static fn (string $value): string =>
                        trim($value),
                    $values
                ),
            ];
        }

        if (
            $detectedHeader !== null
            && is_numeric(
                $detectedHeader['declared_records']
            )
            && (int) $detectedHeader['declared_records']
                !== count($records)
        ) {
            /*
             * No bloquea: el encabezado final se reconstruye con el
             * total real de registros.
             */
            $detectedHeader['count_mismatch'] = true;
            $detectedHeader['actual_records'] =
                count($records);
        }

        return [
            'records' => $records,
            'detected_header' => $detectedHeader,
        ];
    }

    private function buildDusakawiHeader(
        array $headerData,
        int $recordsCount
    ): string {
        $epsCode = trim(
            (string) ($headerData['eps_code'] ?? '')
        );

        $startDate = trim(
            (string) ($headerData['start_date'] ?? '')
        );

        $endDate = trim(
            (string) ($headerData['end_date'] ?? '')
        );

        if ($epsCode === '') {
            throw new RuntimeException(
                'El código de la EPS es obligatorio para DUSAKAWI.'
            );
        }

        if (! $this->validDate($startDate)) {
            throw new RuntimeException(
                'La fecha inicial de DUSAKAWI debe usar AAAA-MM-DD.'
            );
        }

        if (! $this->validDate($endDate)) {
            throw new RuntimeException(
                'La fecha final de DUSAKAWI debe usar AAAA-MM-DD.'
            );
        }

        if ($startDate > $endDate) {
            throw new RuntimeException(
                'La fecha inicial no puede ser posterior a la fecha final.'
            );
        }

        return implode('|', [
            '1',
            $this->normalizeValue($epsCode),
            $startDate,
            $endDate,
            (string) $recordsCount,
        ]);
    }

    private function normalizeDestination(
        string $destination
    ): string {
        $destination = mb_strtolower(
            trim($destination)
        );

        if (! in_array(
            $destination,
            [
                self::DESTINATION_PROTEGER,
                self::DESTINATION_DUSAKAWI,
            ],
            true
        )) {
            throw new RuntimeException(
                'Selecciona PROTEGER o DUSAKAWI como entidad destino.'
            );
        }

        return $destination;
    }

    private function normalizeValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);

        /*
         * El separador no puede quedar dentro de un campo.
         */
        $value = str_replace(
            ["\r", "\n", '|'],
            [' ', ' ', ' '],
            $value
        );

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;
    }

    private function validDate(
        string $value
    ): bool {
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        return $date !== false
            && $date->format('Y-m-d') === $value;
    }

    private function toUtf8(
        string $content
    ): string {
        if (
            mb_check_encoding(
                $content,
                'UTF-8'
            )
        ) {
            return $content;
        }

        $encoding = mb_detect_encoding(
            $content,
            [
                'Windows-1252',
                'ISO-8859-1',
                'UTF-8',
            ],
            true
        );

        return mb_convert_encoding(
            $content,
            'UTF-8',
            $encoding ?: 'Windows-1252'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(
        int $record,
        int $sourceLine,
        string $field,
        mixed $value,
        string $message
    ): array {
        return [
            'record' => $record,
            'source_line' => $sourceLine,
            'field' => $field,
            'value' => $value,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function warning(
        int $record,
        int $sourceLine,
        int $variable,
        mixed $value,
        string $message
    ): array {
        return [
            'record' => $record,
            'source_line' => $sourceLine,
            'variable' => $variable,
            'value' => $value,
            'message' => $message,
        ];
    }
}
