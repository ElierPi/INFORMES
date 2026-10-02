<?php

namespace App\Services\Informe202\Dusakawi;

use App\Services\Informe202\AgeCalculator;
use App\Services\Informe202\Engine\RuleEngine;
use App\Services\Informe202\Parsers\ErrorParserManager;
use RuntimeException;
use ZipArchive;

final class DusakawiZipCorrectionService
{
    /**
     * Solicitud administrativa específica para los siete registros
     * de septiembre de 2026. Esta tabla NO es una regla general
     * TI=>CC / RC=>TI aplicable a todos los afiliados.
     *
     * La EPS debe validar los tipos de documento reales.
     */
    private const ID_TYPE_CHANGES_202609 = [
        '45|TI|1134170174' => 'CC',
        '141|RC|1124077768' => 'TI',
        '157|RC|1175718220' => 'TI',
        '170|RC|1175718544' => 'TI',
        '205|RC|1121557251' => 'TI',
        '214|RC|1121557662' => 'TI',
        '236|RC|1175717859' => 'TI',
    ];

    public function __construct(
        private readonly ErrorParserManager $parserManager,
        private readonly RuleEngine $ruleEngine,
        private readonly AgeCalculator $ageCalculator
    ) {
    }

    /**
     * Corrige el TXT de la Resolución 202 contenido dentro del ZIP
     * y genera un nuevo ZIP conservando el nombre y la ruta interna.
     *
     * @return array{
     *     output_zip:string,
     *     internal_file:string,
     *     corrections:array,
     *     pending:array,
     *     valid:array,
     *     total_records:int,
     *     total_errors:int
     * }
     */
    public function correct(
        string $inputZip,
        string $errorFile,
        string $cutoffDate,
        string $outputDirectory
    ): array {
        if (! is_file($inputZip)) {
            throw new RuntimeException(
                'No se encontró el ZIP original de Dusakawi.'
            );
        }

        if (! is_file($errorFile)) {
            throw new RuntimeException(
                'No se encontró el archivo de errores de Dusakawi.'
            );
        }

        $this->ensureDirectory($outputDirectory);

        $workDirectory = $outputDirectory
            . DIRECTORY_SEPARATOR
            . 'extraido';

        $this->ensureDirectory($workDirectory);

        $this->extractZip(
            inputZip: $inputZip,
            outputDirectory: $workDirectory
        );

        $reportPath = $this->findTxtReport(
            $workDirectory
        );

        $internalFile = $this->relativePath(
            $workDirectory,
            $reportPath
        );

        $txt = $this->readTxt(
            $reportPath
        );

        /*
         * Si la línea de control trae fecha de corte, se prioriza.
         * Estructura esperada:
         * 1|EPS|fecha inicial|fecha corte|cantidad
         */
        $effectiveCutoffDate =
            $txt['control']['cutoff_date']
            ?? $cutoffDate;

        $records = $this->buildRecords(
            dataLines: $txt['records'],
            cutoffDate: $effectiveCutoffDate
        );

        $errors = $this->parserManager->parse(
            'dusakawi',
            $errorFile
        );

        if ($errors === []) {
            throw new RuntimeException(
                'No se encontraron errores reconocibles '
                . 'en el archivo de Dusakawi.'
            );
        }

        $indexed = $this->indexRecords(
            $records
        );

        $corrections = [];
        $pending = [];
        $valid = [];

        foreach ($errors as $error) {
            $recordKey = $this->findRecordKey(
                error: $error,
                indexes: $indexed
            );

            if ($recordKey === null) {
                $pending[] = [
                    ...$error,
                    'estado' => 'manual',
                    'detalle' =>
                        'No se encontró el registro correspondiente '
                        . 'dentro del TXT.',
                ];

                continue;
            }

            $record = &$indexed['records'][$recordKey];

            /*
             * En los siete rechazos administrativos expresamente
             * identificados por el usuario, se registra una propuesta
             * de cambio de tipo, sin tocar el número de documento.
             * Nunca se ejecuta sobre un mensaje clínico ni sobre otro
             * registro con el mismo tipo pero documento diferente.
             */
            $administrativeError = str_contains(
                strtolower((string) ($error['mensaje'] ?? '')),
                'no fue identificado en el sistema'
            );

            if ($administrativeError) {
                $recordNumber = trim((string) (
                    $record['record_number'] ?? ''
                ));
                $type = strtoupper(trim((string) (
                    $record['variables'][3] ?? ''
                )));
                $number = $this->normalizeIdentification(
                    $record['variables'][4] ?? null
                );
                $sourceMatches = $recordNumber === trim((string) (
                    $error['linea'] ?? $error['fila'] ?? ''
                ))
                    && $type === strtoupper(trim((string) (
                        $error['tipo_identificacion'] ?? ''
                    )))
                    && $number === $this->normalizeIdentification(
                        $error['identificacion'] ?? null
                    );
                $lookup = "{$recordNumber}|{$type}|{$number}";
                $target = $sourceMatches
                    ? (self::ID_TYPE_CHANGES_202609[$lookup] ?? null)
                    : null;

                if ($target !== null) {
                    $record['variables'][3] = $target;
                    $corrections[] = $this->buildResult(
                        record: $record,
                        error: $error,
                        variable: 3,
                        oldValue: $type,
                        newValue: $target,
                        status: 'automatic',
                        reason: 'Tipo de identificación ajustado solo para '
                            . 'este rechazo administrativo de septiembre 2026, '
                            . 'según instrucción del usuario. '
                            . 'Requiere comprobación ante la EPS.',
                        rule: 'DusakawiSpecificIdTypeCorrection202609'
                    );
                } else {
                    $pending[] = $this->buildResult(
                        record: $record,
                        error: $error,
                        variable: 3,
                        oldValue: $type,
                        newValue: null,
                        status: 'manual',
                        reason: 'Afiliado no encontrado; este registro no está '
                            . 'en la lista de los siete cambios solicitados '
                            . 'o no coincide con el TXT y error cargados. '
                            . 'Verificar tipo y número de identidad con la EPS.',
                        rule: 'DusakawiSpecificIdTypeCorrection202609'
                    );
                }

                unset($record);
                continue;
            }

            $decision = $this->ruleEngine->resolve(
                $record,
                $error
            );

            if ($decision->status === 'automatic') {
                $changes = $decision->automaticChanges();

                /*
                 * Las reglas de coherencia pueden modificar un bloque
                 * completo del registro. Antes de escribir, se validan
                 * todas las variables para evitar correcciones parciales.
                 */
                $normalizedChanges = [];
                $invalidVariable = null;

                foreach ($changes as $variable => $newValue) {
                    $variable = (int) $variable;

                    if ($variable < 0 || $variable > 118) {
                        $invalidVariable = $variable;
                        break;
                    }

                    $normalizedChanges[$variable] =
                        $this->normalizeTxtValue($newValue);
                }

                if ($normalizedChanges === [] || $invalidVariable !== null) {
                    $pending[] = [
                        ...$error,
                        'estado' => 'manual',
                        'detalle' =>
                            'La regla automática no informó un bloque '
                            . 'de variables válido.',
                    ];

                    unset($record);
                    continue;
                }

                foreach ($normalizedChanges as $variable => $newValue) {
                    $oldValue =
                        $record['variables'][$variable]
                        ?? null;

                    if (
                        trim((string) $oldValue)
                        === trim((string) $newValue)
                    ) {
                        $valid[] = $this->buildResult(
                            record: $record,
                            error: $error,
                            variable: $variable,
                            oldValue: $oldValue,
                            newValue: $newValue,
                            status: 'valid',
                            reason:
                                $decision->reason
                                . ' El campo ya contenía el valor correcto.',
                            rule: $decision->rule
                        );

                        continue;
                    }

                    $record['variables'][$variable] =
                        $newValue;

                    $corrections[] = $this->buildResult(
                        record: $record,
                        error: $error,
                        variable: $variable,
                        oldValue: $oldValue,
                        newValue: $newValue,
                        status: 'automatic',
                        reason: $decision->reason,
                        rule: $decision->rule
                    );
                }

                unset($record);
                continue;
            }

            if ($decision->status === 'valid') {
                $valid[] = $this->buildResult(
                    record: $record,
                    error: $error,
                    variable: $decision->variable,
                    oldValue: $decision->currentValue,
                    newValue: $decision->newValue,
                    status: 'valid',
                    reason: $decision->reason,
                    rule: $decision->rule
                );

                unset($record);
                continue;
            }

            $pending[] = $this->buildResult(
                record: $record,
                error: $error,
                variable: $decision->variable,
                oldValue: $decision->currentValue,
                newValue: $decision->newValue,
                status: 'manual',
                reason: $decision->reason,
                rule: $decision->rule
            );

            unset($record);
        }

        /*
         * Reconstruye las líneas manteniendo:
         * - línea de control original;
         * - separador pipe;
         * - 119 variables por registro;
         * - mismo orden del TXT.
         */
        $correctedContent = $this->buildTxtContent(
            controlLine: $txt['control_line'],
            orderedRecords: $indexed['records'],
            lineEnding: $txt['line_ending'],
            encoding: $txt['encoding']
        );

        if (
            file_put_contents(
                $reportPath,
                $correctedContent
            ) === false
        ) {
            throw new RuntimeException(
                'No fue posible guardar el TXT corregido.'
            );
        }

        $outputZip = $outputDirectory
            . DIRECTORY_SEPARATOR
            . $this->buildOutputZipName(
                $inputZip
            );

        $this->buildZip(
            sourceDirectory: $workDirectory,
            outputZip: $outputZip
        );

        return [
            'output_zip' => $outputZip,
            'internal_file' => str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $internalFile
            ),
            'corrections' => $corrections,
            'pending' => $pending,
            'valid' => $valid,
            'total_records' => count($records),
            'total_errors' => count($errors),
        ];
    }

    private function readTxt(
        string $path
    ): array {
        $raw = file_get_contents($path);

        if ($raw === false || $raw === '') {
            throw new RuntimeException(
                'El TXT de Dusakawi está vacío o no se pudo leer.'
            );
        }

        $encoding = mb_detect_encoding(
            $raw,
            ['UTF-8', 'Windows-1252', 'ISO-8859-1'],
            true
        ) ?: 'Windows-1252';

        $utf8 = $encoding === 'UTF-8'
            ? $raw
            : mb_convert_encoding(
                $raw,
                'UTF-8',
                $encoding
            );

        $lineEnding = str_contains($utf8, "\r\n")
            ? "\r\n"
            : "\n";

        $lines = preg_split(
            '/\r\n|\n|\r/',
            $utf8
        ) ?: [];

        $lines = array_values(
            array_filter(
                $lines,
                static fn (string $line): bool =>
                    trim($line) !== ''
            )
        );

        if ($lines === []) {
            throw new RuntimeException(
                'El TXT no contiene líneas válidas.'
            );
        }

        $controlLine = array_shift($lines);
        $controlFields = explode('|', $controlLine);

        if (($controlFields[0] ?? null) !== '1') {
            throw new RuntimeException(
                'La primera línea del TXT no es una línea de control válida.'
            );
        }

        $records = [];

        foreach ($lines as $position => $line) {
            $fields = explode('|', $line);

            if (($fields[0] ?? null) !== '2') {
                continue;
            }

            if (count($fields) < 119) {
                $fields = array_pad(
                    $fields,
                    119,
                    ''
                );
            }

            if (count($fields) > 119) {
                $fields = array_slice(
                    $fields,
                    0,
                    119
                );
            }

            $records[] = [
                'position' => $position,
                'variables' => $fields,
            ];
        }

        return [
            'control_line' => $controlLine,
            'control' => [
                'eps' => $controlFields[1] ?? null,
                'start_date' => $controlFields[2] ?? null,
                'cutoff_date' => $controlFields[3] ?? null,
                'declared_records' => $controlFields[4] ?? null,
            ],
            'records' => $records,
            'line_ending' => $lineEnding,
            'encoding' => $encoding,
        ];
    }

    private function buildRecords(
        array $dataLines,
        string $cutoffDate
    ): array {
        $records = [];

        foreach ($dataLines as $item) {
            $variables = $item['variables'];

            $birthDate = $variables[9] ?? null;

            try {
                $age = $this->ageCalculator->calculate(
                    $birthDate,
                    $cutoffDate
                );

                $ageError = null;
            } catch (\Throwable $exception) {
                $age = null;
                $ageError = $exception->getMessage();
            }

            $records[] = [
                'txt_position' => $item['position'],
                'record_number' =>
                    $variables[1] ?? null,
                'birth_date' =>
                    $birthDate,
                'sex' => strtoupper(
                    trim((string) (
                        $variables[10]
                        ?? ''
                    ))
                ),
                'cutoff_date' => $cutoffDate,
                'age' => $age,
                'age_error' => $ageError,
                'variables' => $variables,
            ];
        }

        return $records;
    }

    private function indexRecords(
        array $records
    ): array {
        $indexed = [];
        $byIdentification = [];
        $byRecordNumber = [];

        foreach ($records as $key => $record) {
            $recordKey = (string) $key;

            $indexed[$recordKey] = $record;

            $type = strtoupper(
                trim((string) (
                    $record['variables'][3]
                    ?? ''
                ))
            );

            $number = $this->normalizeIdentification(
                $record['variables'][4]
                ?? null
            );

            if ($type !== '' && $number !== '') {
                $byIdentification[
                    "{$type}|{$number}"
                ] = $recordKey;
            }

            $recordNumber = trim(
                (string) (
                    $record['record_number']
                    ?? ''
                )
            );

            if ($recordNumber !== '') {
                $byRecordNumber[$recordNumber] =
                    $recordKey;
            }
        }

        return [
            'records' => $indexed,
            'by_identification' => $byIdentification,
            'by_record_number' => $byRecordNumber,
        ];
    }

    private function findRecordKey(
        array $error,
        array $indexes
    ): ?string {
        $type = strtoupper(
            trim((string) (
                $error['tipo_identificacion']
                ?? ''
            ))
        );

        $number = $this->normalizeIdentification(
            $error['identificacion']
            ?? null
        );

        if ($type !== '' && $number !== '') {
            $key = "{$type}|{$number}";

            if (
                isset(
                    $indexes['by_identification'][$key]
                )
            ) {
                return $indexes[
                    'by_identification'
                ][$key];
            }
        }

        $recordNumber = $error['registro']
            ?? $error['fila']
            ?? $error['linea']
            ?? $error['consecutivo']
            ?? null;

        if (
            $recordNumber !== null
            && isset(
                $indexes['by_record_number'][
                    trim((string) $recordNumber)
                ]
            )
        ) {
            return $indexes['by_record_number'][
                trim((string) $recordNumber)
            ];
        }

        return null;
    }

    private function buildTxtContent(
        string $controlLine,
        array $orderedRecords,
        string $lineEnding,
        string $encoding
    ): string {
        uasort(
            $orderedRecords,
            static fn (array $first, array $second): int =>
                ($first['txt_position'] ?? 0)
                <=>
                ($second['txt_position'] ?? 0)
        );

        $lines = [$controlLine];

        foreach ($orderedRecords as $record) {
            $variables = $record['variables'] ?? [];

            $variables = array_pad(
                array_slice($variables, 0, 119),
                119,
                ''
            );

            $lines[] = implode(
                '|',
                array_map(
                    static fn (mixed $value): string =>
                        trim((string) $value),
                    $variables
                )
            );
        }

        $utf8 = implode(
            $lineEnding,
            $lines
        ) . $lineEnding;

        return $encoding === 'UTF-8'
            ? $utf8
            : mb_convert_encoding(
                $utf8,
                $encoding,
                'UTF-8'
            );
    }

    private function buildResult(
        array $record,
        array $error,
        ?int $variable,
        mixed $oldValue,
        mixed $newValue,
        string $status,
        string $reason,
        ?string $rule
    ): array {
        $definition = $variable !== null
            ? config(
                "resolucion202.fields.{$variable}",
                []
            )
            : [];

        return [
            'codigo' => $error['codigo'] ?? null,
            'registro' =>
                $record['record_number'] ?? null,
            'fila' =>
                $record['record_number'] ?? null,
            'variable' => $variable,
            'campo' =>
                $definition['name']
                ?? $error['campo']
                ?? (
                    $variable !== null
                        ? "Variable {$variable}"
                        : null
                ),
            'valor_anterior' => $oldValue,
            'valor_nuevo' => $newValue,
            'motivo' => $reason,
            'detalle' => $reason,
            'regla' => $rule,
            'edad' => $record['age'] ?? null,
            'mensaje_eps' =>
                $error['mensaje'] ?? null,
            'estado' => $status,
        ];
    }

    private function normalizeTxtValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_float($value)) {
            return rtrim(
                rtrim(
                    number_format(
                        $value,
                        6,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            );
        }

        return trim((string) $value);
    }

    private function normalizeIdentification(
        mixed $value
    ): string {
        $value = trim((string) $value);

        if (preg_match('/^\d+\.0+$/', $value)) {
            $value = preg_replace(
                '/\.0+$/',
                '',
                $value
            ) ?? $value;
        }

        return strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $value
            ) ?? ''
        );
    }

    private function findTxtReport(
        string $directory
    ): string {
        $candidates = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (
                strtolower(
                    $file->getExtension()
                ) !== 'txt'
            ) {
                continue;
            }

            $candidates[] =
                $file->getPathname();
        }

        if ($candidates === []) {
            throw new RuntimeException(
                'El ZIP no contiene un archivo TXT '
                . 'de la Resolución 202.'
            );
        }

        if (count($candidates) > 1) {
            usort(
                $candidates,
                static fn (
                    string $first,
                    string $second
                ): int =>
                    filesize($second)
                    <=>
                    filesize($first)
            );
        }

        return $candidates[0];
    }

    private function extractZip(
        string $inputZip,
        string $outputDirectory
    ): void {
        $zip = new ZipArchive();

        if ($zip->open($inputZip) !== true) {
            throw new RuntimeException(
                'No fue posible abrir el ZIP original de Dusakawi.'
            );
        }

        if (! $zip->extractTo($outputDirectory)) {
            $zip->close();

            throw new RuntimeException(
                'No fue posible extraer el ZIP de Dusakawi.'
            );
        }

        $zip->close();
    }

    private function buildZip(
        string $sourceDirectory,
        string $outputZip
    ): void {
        $zip = new ZipArchive();

        if (
            $zip->open(
                $outputZip,
                ZipArchive::CREATE
                | ZipArchive::OVERWRITE
            ) !== true
        ) {
            throw new RuntimeException(
                'No fue posible crear el ZIP corregido.'
            );
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $sourceDirectory,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $localName = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                $this->relativePath(
                    $sourceDirectory,
                    $item->getPathname()
                )
            );

            if ($item->isDir()) {
                $zip->addEmptyDir($localName);
                continue;
            }

            $zip->addFile(
                $item->getPathname(),
                $localName
            );
        }

        $zip->close();

        if (
            ! is_file($outputZip)
            || filesize($outputZip) === 0
        ) {
            throw new RuntimeException(
                'El ZIP corregido no fue generado correctamente.'
            );
        }
    }

    private function ensureDirectory(
        string $directory
    ): void {
        if (
            ! is_dir($directory)
            && ! mkdir(
                $directory,
                0755,
                true
            )
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta temporal.'
            );
        }
    }

    private function relativePath(
        string $base,
        string $path
    ): string {
        $base = rtrim(
            realpath($base) ?: $base,
            DIRECTORY_SEPARATOR
        );

        $realPath = realpath($path) ?: $path;

        return ltrim(
            substr(
                $realPath,
                strlen($base)
            ),
            DIRECTORY_SEPARATOR
        );
    }

private function buildOutputZipName(
    string $inputZip
): string {
    return basename($inputZip);
}
}
