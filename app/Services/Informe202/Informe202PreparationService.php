<?php

namespace App\Services\Informe202;

use RuntimeException;

final class Informe202PreparationService
{
    public const DESTINATION_PROTEGER = 'proteger';

    public const DESTINATION_DUSAKAWI = 'dusakawi';

    public const DESTINATION_FAMILIAR_COLOMBIA = 'familiar_colombia';

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

            /*
             * Proteger: el campo 22 recibe a veces una descripción
             * narrativa del tacto rectal en lugar del código numérico.
             * Se utiliza el campo 64 (fecha del tacto rectal) para no
             * registrar una exploración normal como «no evaluada».
             */
            if ($destination === self::DESTINATION_PROTEGER) {
                $tactoAnterior = $normalized[22];
                $tactoNuevo = $this->protegerTactoRectalCode(
                    $tactoAnterior,
                    $normalized[64]
                );

                if ($tactoNuevo !== null && $tactoNuevo !== $tactoAnterior) {
                    $normalized[22] = $tactoNuevo;

                    $warnings[] = $this->warning(
                        record: $index + 1,
                        sourceLine: $sourceLine,
                        variable: 22,
                        value: $tactoAnterior,
                        message: $tactoNuevo === '21'
                            ? 'Se normalizó el texto del tacto rectal a 21 '
                                . 'porque la fecha asociada está como sin dato (1800-01-01). '
                                . 'Confirmar que el examen no fue evaluado.'
                            : 'Se normalizó la descripción de próstata normal a código 5. '
                                . 'Verificar que la fecha del tacto rectal (variable 64) '
                                . 'corresponda a la exploración realizada.'
                    );
                }
            }

            /*
             * Proteger: clasificación de riesgo cardiovascular (variable 114).
             * El catálogo del proyecto no tiene una categoría separada para
             * «extremadamente alto»; el valor permitido más próximo es
             * 4 = Alto. No cambiar a 21 porque 21 = Riesgo no evaluado.
             * Registrar advertencia para confirmar la equivalencia con EPS.
             */
            if ($destination === self::DESTINATION_PROTEGER) {
                $riskAnterior = $normalized[114];
                $riskNuevo = $this->protegerRiesgoCardiovascularCode(
                    $riskAnterior
                );

                if ($riskNuevo !== null && $riskNuevo !== $riskAnterior) {
                    $normalized[114] = $riskNuevo;

                    $warnings[] = $this->warning(
                        record: $index + 1,
                        sourceLine: $sourceLine,
                        variable: 114,
                        value: $riskAnterior,
                        message: 'La descripción de riesgo extremadamente alto '
                            . '(mayor o igual al 40 %) fue normalizada '
                            . 'a 4 = Alto, valor más alto del catálogo '
                            . 'disponible. Verificar la equivalencia con '
                            . 'la EPS antes de radicar el informe. '
                            . 'No corresponde 21, que significa riesgo no evaluado.'
                    );
                }
            }

            /*
             * PROTEGER: en el TXT de septiembre, el texto "Extremadamente
             * alto, mayor o igual al 40 %" llegó a la posición 117,
             * mientras la variable 114 ya tenía el código clínico 4.
             *
             * No convertir riesgo documentado a 21 (no evaluado).
             * Únicamente reemplazar el texto de 117 por el código 4
             * existente cuando ambos coincidan. Otros casos son manuales.
             */
            if ($destination === self::DESTINATION_PROTEGER) {
                $ultimoRiesgoAnterior = $normalized[117];
                $esExtremadamenteAlto =
                    $this->protegerRiesgoCardiovascularCode(
                        $ultimoRiesgoAnterior
                    ) === '4';

                if ($esExtremadamenteAlto) {
                    if ($normalized[114] === '4') {
                        $normalized[117] = '4';

                        $warnings[] = $this->warning(
                            record: $index + 1,
                            sourceLine: $sourceLine,
                            variable: 117,
                            value: $ultimoRiesgoAnterior,
                            message: 'La descripción extremadamente alto ' 
                                . 'estaba en la posición 117 y la 114 ya ' 
                                . 'indicaba riesgo alto (4). Se sincronizó ' 
                                . 'la posición 117 a 4. El código 21 ' 
                                . 'significa no evaluado y no describe ' 
                                . 'este resultado.'
                        );
                    } else {
                        $warnings[] = $this->warning(
                            record: $index + 1,
                            sourceLine: $sourceLine,
                            variable: 117,
                            value: $ultimoRiesgoAnterior,
                            message: 'Revisar clasificación clínica: ' 
                                . 'la descripción en posición 117 indica ' 
                                . 'riesgo extremadamente alto, pero el ' 
                                . 'campo 114 contiene otro código. No ' 
                                . 'se convierte automáticamente a 21.'
                        );
                    }
                }
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

                'encoding' => $destination === self::DESTINATION_FAMILIAR_COLOMBIA
                    ? 'ANSI / Windows-1252'
                    : 'UTF-8 sin BOM',

                'fields_per_record' => 119,

                'includes_type_1' =>
                    in_array(
                        $destination,
                        [
                            self::DESTINATION_DUSAKAWI,
                            self::DESTINATION_FAMILIAR_COLOMBIA,
                        ],
                        true
                    ),

                'output_type' =>
                    $destination === self::DESTINATION_FAMILIAR_COLOMBIA
                        ? 'ZIP con TXT'
                        : 'TXT',
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

            if ($destination === self::DESTINATION_PROTEGER) {
                $tactoNuevo = $this->protegerTactoRectalCode(
                    (string) ($values[22] ?? ''),
                    (string) ($values[64] ?? '')
                );

                if ($tactoNuevo !== null) {
                    $values[22] = $tactoNuevo;
                }

                $riskNuevo = $this->protegerRiesgoCardiovascularCode(
                    (string) ($values[114] ?? '')
                );

                if ($riskNuevo !== null) {
                    $values[114] = $riskNuevo;
                }

                // También normaliza el TXT cuando se exporta sin pasar
                // de nuevo por el paso Preparar informe.
                if (
                    $this->protegerRiesgoCardiovascularCode(
                        (string) ($values[117] ?? '')
                    ) === '4'
                ) {
                    if ((string) $values[114] !== '4') {
                        throw new RuntimeException(
                            sprintf(
                                'Registro %d: la posición 117 dice ' 
                                . 'extremadamente alto, pero la 114 no ' 
                                . 'contiene el código 4. Revisar antes ' 
                                . 'de exportar, sin sustituir por 21.',
                                $recordIndex + 1
                            )
                        );
                    }

                    $values[117] = '4';
                }
            }

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
     * Genera el TXT ANSI y lo comprime en ZIP para Familiar de Colombia.
     *
     * @return array{txt_path:string, zip_path:string}
     */
    public function generateFamiliarColombiaZip(
        array $records,
        string $txtPath,
        string $zipPath,
        string $cutoffDate
    ): array {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException(
                'La extensión ZIP de PHP no está habilitada.'
            );
        }

        if ($records === []) {
            throw new RuntimeException(
                'No hay registros válidos para generar el archivo.'
            );
        }

        $periodEnd = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $cutoffDate
        );

        if ($periodEnd === false) {
            throw new RuntimeException(
                'La fecha final del periodo no es válida.'
            );
        }

        $firstRecord = array_values($records[0]);
        $providerCode = trim(
            (string) ($firstRecord[2] ?? '')
        );

        if ($providerCode === '') {
            throw new RuntimeException(
                'No fue posible determinar el código de habilitación de la IPS para la línea de control.'
            );
        }

        foreach ($records as $recordIndex => $record) {
            $values = array_values($record);
            $recordProviderCode = trim(
                (string) ($values[2] ?? '')
            );

            if ($recordProviderCode !== $providerCode) {
                throw new RuntimeException(
                    sprintf(
                        'El registro %d tiene un código de IPS diferente al de la línea de control.',
                        $recordIndex + 1
                    )
                );
            }
        }

        $periodStart = $periodEnd->modify(
            'first day of this month'
        );

        /*
         * Familiar de Colombia exige una línea de control tipo 1:
         * 1|código IPS|fecha inicial|fecha final|total registros
         */
        $lines = [
            implode(
                '|',
                [
                    '1',
                    $providerCode,
                    $periodStart->format('Y-m-d'),
                    $periodEnd->format('Y-m-d'),
                    (string) count($records),
                ]
            ),
        ];

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

        $utf8Content = implode("\r\n", $lines);

        /*
         * SIGIRES exige ANSI. En Windows/PHP esto se representa
         * como Windows-1252.
         */
        $ansiContent = mb_convert_encoding(
            $utf8Content,
            'Windows-1252',
            'UTF-8'
        );

        $directory = dirname($txtPath);

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0755, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de salida.'
            );
        }

        if (file_put_contents($txtPath, $ansiContent) === false) {
            throw new RuntimeException(
                'No fue posible generar el TXT ANSI.'
            );
        }

        $zip = new \ZipArchive();

        $openResult = $zip->open(
            $zipPath,
            \ZipArchive::CREATE
            | \ZipArchive::OVERWRITE
        );

        if ($openResult !== true) {
            throw new RuntimeException(
                'No fue posible crear el archivo ZIP.'
            );
        }

        $zip->addFile(
            $txtPath,
            basename($txtPath)
        );

        if (! $zip->close()) {
            throw new RuntimeException(
                'No fue posible finalizar el archivo ZIP.'
            );
        }

        return [
            'txt_path' => $txtPath,
            'zip_path' => $zipPath,
        ];
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
                self::DESTINATION_FAMILIAR_COLOMBIA,
            ],
            true
        )) {
            throw new RuntimeException(
                'Selecciona PROTEGER o DUSAKAWI como entidad destino.'
            );
        }

        return $destination;
    }

    /**
     * La fuente a veces escribe una descripción narrativa en el campo 22.
     * Catálogo del propio proyecto: 5=próstata normal; 21=riesgo no evaluado.
     *
     * Solo se normaliza la descripción concreta del hallazgo normal.
     * Una fecha real demuestra que hubo una evaluación: código 5.
     * Si el reporte trae explícitamente la fecha sin dato (1800-01-01),
     * se conserva el 21 solicitado, pero se muestra una advertencia en
     * preparación porque el texto narrativo contradice ese estado.
     * No se modifica la fecha ni se inventa el resultado de otro examen.
     */
    private function protegerTactoRectalCode(
        mixed $result,
        mixed $date
    ): ?string {
        $result = trim((string) $result);

        if ($result === '') {
            return null;
        }

        $text = mb_strtoupper($result, 'UTF-8');
        $text = strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $text = preg_replace('/[^A-Z0-9]+/', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        // Firma semántica de la descripción compartida por la IPS.
        if (
            ! str_contains($text, 'PROSTATA DE TAMANO ACORDE A LA EDAD')
            || ! str_contains($text, 'SIMETRICA')
            || ! str_contains($text, 'SIN NODULOS')
            || ! str_contains($text, 'SURCO MEDIO CONSERVADO')
        ) {
            return null;
        }

        return trim((string) $date) === '1800-01-01'
            ? '21'
            : '5';
    }

    /**
     * Variable 114 (Proteger): only the stated risk category is recognized.
     * Allowed codes: 0 No aplica, 4 Alto, 5 Bajo, 6 Moderado,
     * 21 Riesgo no evaluado. Extremely high must never be silently
     * converted into 21 because the risk has been evaluated.
     */
    private function protegerRiesgoCardiovascularCode(
        mixed $result
    ): ?string {
        $result = trim((string) $result);

        if ($result === '') {
            return null;
        }

        $text = mb_strtoupper($result, 'UTF-8');
        $text = strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $text = preg_replace('/[^A-Z0-9]+/', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (
            str_contains($text, 'EXTREMADAMENTE ALTO')
            && str_contains($text, 'MAYOR O IGUAL AL 40')
        ) {
            return '4';
        }

        return null;
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
