<?php

namespace App\Services\Resolucion1552;

use App\Services\Reports\Files\ZipReportExtractor;
use RuntimeException;

class Resolucion1552FileConsistencyService
{
    public function __construct(
        private readonly ZipReportExtractor $zipExtractor
    ) {
    }

    /**
     * Valida la Consistencia del Archivo (CA).
     *
     * @return array{
     *     valid: bool,
     *     status: string,
     *     errors: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     extracted: array<string, mixed>|null
     * }
     */
    public function validate(
        string $zipPath,
        string $originalName
    ): array {
        $errors = [];
        $extracted = null;

        $config = config('resolucion1552.file', []);

        /*
        |--------------------------------------------------------------------------
        | 1. Validar archivo físico
        |--------------------------------------------------------------------------
        */

        if (! is_file($zipPath)) {
            $errors[] = $this->error(
                rule: 'file_exists',
                message: 'No se encontró el archivo seleccionado.',
                help: 'Seleccione nuevamente el archivo ZIP.'
            );

            return $this->result(
                errors: $errors,
                originalName: $originalName
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Validar extensión ZIP
        |--------------------------------------------------------------------------
        */

        $extension = strtolower(
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        if ($extension !== 'zip') {
            $errors[] = $this->error(
                rule: 'zip_extension',
                message: 'El archivo debe estar comprimido en formato ZIP.',
                value: $extension !== '' ? $extension : '(sin extensión)',
                help: 'Comprima el archivo TXT en formato ZIP antes de cargarlo.'
            );

            return $this->result(
                errors: $errors,
                originalName: $originalName
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Validar nombre del ZIP
        |--------------------------------------------------------------------------
        */

        $filenamePattern = $config['filename_pattern']
            ?? '/^RESOLUCION_1552_\d{2,12}_\d{8}\.zip$/i';

        if (! preg_match($filenamePattern, $originalName)) {
            $errors[] = $this->error(
                rule: 'zip_filename',
                message: 'El nombre del archivo ZIP no cumple la estructura requerida.',
                value: $originalName,
                help: 'Use el formato RESOLUCION_1552_CODIGOHABILITACIONIPS_DDMMAAAA.zip.',
                expected: $config['filename_example']
                    ?? 'RESOLUCION_1552_123456789012_31012026.zip'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Validar fecha incluida en el nombre
        |--------------------------------------------------------------------------
        */

        $this->validateFilenameDate(
            filename: $originalName,
            errors: $errors
        );

        /*
        |--------------------------------------------------------------------------
        | 5. Extraer TXT
        |--------------------------------------------------------------------------
        */

        try {
            $extracted = $this->zipExtractor->extractTxt($zipPath);
        } catch (RuntimeException $exception) {
            $errors[] = $this->error(
                rule: 'zip_content',
                message: $exception->getMessage(),
                help: 'El ZIP debe contener un único archivo TXT.'
            );

            return $this->result(
                errors: $errors,
                originalName: $originalName
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Validar nombre del TXT
        |--------------------------------------------------------------------------
        */

        $txtName = $extracted['txt_name'];

        $txtPattern = $config['txt_filename_pattern']
            ?? '/^RESOLUCION_1552_\d{2,12}_\d{8}\.txt$/i';

        if (! preg_match($txtPattern, $txtName)) {
            $errors[] = $this->error(
                rule: 'txt_filename',
                message: 'El nombre del archivo TXT no cumple la estructura requerida.',
                value: $txtName,
                help: 'El TXT debe llamarse RESOLUCION_1552_CODIGOHABILITACIONIPS_DDMMAAAA.txt.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Comparar nombre del ZIP y del TXT
        |--------------------------------------------------------------------------
        */

        $zipBaseName = pathinfo($originalName, PATHINFO_FILENAME);
        $txtBaseName = pathinfo($txtName, PATHINFO_FILENAME);

        if (strcasecmp($zipBaseName, $txtBaseName) !== 0) {
            $errors[] = $this->error(
                rule: 'matching_filenames',
                message: 'El ZIP y el TXT deben tener el mismo nombre base.',
                value: "ZIP: {$zipBaseName} | TXT: {$txtBaseName}",
                help: 'Cambie el nombre de ambos archivos para que coincidan.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Leer contenido del TXT
        |--------------------------------------------------------------------------
        */

        $contents = file_get_contents($extracted['txt_path']);

        if ($contents === false) {
            $errors[] = $this->error(
                rule: 'txt_readable',
                message: 'No fue posible leer el archivo TXT.',
                help: 'Verifique que el archivo no esté dañado.'
            );

            return $this->result(
                errors: $errors,
                originalName: $originalName,
                extracted: $extracted
            );
        }

        if (trim($contents) === '') {
            $errors[] = $this->error(
                rule: 'txt_not_empty',
                message: 'El archivo TXT se encuentra vacío.',
                help: 'Incluya el encabezado y los registros correspondientes.'
            );

            return $this->result(
                errors: $errors,
                originalName: $originalName,
                extracted: $extracted
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 9. Validar codificación
        |--------------------------------------------------------------------------
        */

        $encoding = $this->detectEncoding($contents);

        if ($encoding === null) {
            $errors[] = $this->error(
                rule: 'encoding',
                message: 'No fue posible reconocer la codificación del archivo.',
                help: 'Guarde el TXT utilizando codificación ANSI o Windows-1252.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Convertir temporalmente a UTF-8 para validar estructura
        |--------------------------------------------------------------------------
        */

        $utf8Contents = $this->convertToUtf8(
            contents: $contents,
            detectedEncoding: $encoding
        );

        $utf8Contents = $this->removeBom($utf8Contents);

        /*
        |--------------------------------------------------------------------------
        | 11. Validar separador TAB
        |--------------------------------------------------------------------------
        */

        $firstLine = $this->firstNonEmptyLine($utf8Contents);

        if ($firstLine === null) {
            $errors[] = $this->error(
                rule: 'first_line',
                message: 'El archivo no contiene líneas válidas.',
                help: 'Incluya el encabezado y al menos un registro.'
            );

            return $this->result(
                errors: $errors,
                originalName: $originalName,
                extracted: $extracted,
                encoding: $encoding
            );
        }

        if (! str_contains($firstLine, "\t")) {
            $errors[] = $this->error(
                rule: 'delimiter',
                message: 'El archivo no está separado por tabulaciones.',
                help: 'Use el carácter TAB como separador entre columnas.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 12. Validar encabezado y número de columnas
        |--------------------------------------------------------------------------
        */

        $headers = array_map(
            static fn ($value): string => trim((string) $value),
            str_getcsv(
                string: $firstLine,
                separator: "\t",
                enclosure: '"',
                escape: '\\'
            )
        );

        $expectedColumns = config('resolucion1552.columns', []);
        $expectedColumnCount = count($expectedColumns);
        $actualColumnCount = count($headers);

        if ($actualColumnCount !== $expectedColumnCount) {
            $errors[] = $this->error(
                rule: 'column_count',
                message: "El encabezado debe contener {$expectedColumnCount} columnas.",
                value: "Columnas encontradas: {$actualColumnCount}",
                help: 'Revise la estructura del archivo y el separador utilizado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 13. Validar nombres de encabezados
        |--------------------------------------------------------------------------
        */

        foreach ($expectedColumns as $index => $column) {
            $expectedHeader = trim(
                (string) ($column['header'] ?? $column['name'] ?? '')
            );

            $actualHeader = trim(
                (string) ($headers[$index] ?? '')
            );

            if (
                $expectedHeader !== ''
                && $this->normalizeHeader($actualHeader)
                    !== $this->normalizeHeader($expectedHeader)
            ) {
                $errors[] = $this->error(
                    rule: 'header_name',
                    message: 'El nombre de una columna no coincide con la estructura.',
                    value: $actualHeader !== '' ? $actualHeader : '(vacío)',
                    help: "La columna " . ($index + 1)
                        . " debe llamarse: {$expectedHeader}.",
                    column: $index + 1,
                    field: $expectedHeader
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 14. Validar registros
        |--------------------------------------------------------------------------
        */

        $lines = preg_split('/\r\n|\r|\n/', $utf8Contents) ?: [];

        $nonEmptyLines = array_values(array_filter(
            $lines,
            static fn ($line): bool => trim((string) $line) !== ''
        ));

        $totalRecords = max(count($nonEmptyLines) - 1, 0);

        if ($totalRecords === 0) {
            $errors[] = $this->error(
                rule: 'records',
                message: 'El archivo contiene encabezado, pero no tiene registros.',
                help: 'Agregue los registros correspondientes al periodo reportado.'
            );
        }

        return $this->result(
            errors: $errors,
            originalName: $originalName,
            extracted: $extracted,
            encoding: $encoding,
            headers: $headers,
            totalRecords: $totalRecords
        );
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function validateFilenameDate(
        string $filename,
        array &$errors
    ): void {
        if (
            ! preg_match(
                '/RESOLUCION_1552_\d{2,12}_(\d{2})(\d{2})(\d{4})/i',
                $filename,
                $matches
            )
        ) {
            return;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];

        if (! checkdate($month, $day, $year)) {
            $errors[] = $this->error(
                rule: 'filename_date',
                message: 'La fecha incluida en el nombre del archivo no es válida.',
                value: "{$matches[1]}{$matches[2]}{$matches[3]}",
                help: 'Use una fecha válida en formato DDMMAAAA.'
            );

            return;
        }

        $lastDay = (int) date(
            't',
            strtotime(sprintf('%04d-%02d-01', $year, $month))
        );

        if ($day !== $lastDay) {
            $errors[] = $this->error(
                rule: 'period_end_date',
                message: 'La fecha del nombre debe corresponder al último día del mes.',
                value: sprintf('%02d/%02d/%04d', $day, $month, $year),
                help: sprintf(
                    'Para ese periodo la fecha correcta sería %02d/%02d/%04d.',
                    $lastDay,
                    $month,
                    $year
                )
            );
        }
    }

    private function detectEncoding(string $contents): ?string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return 'UTF-8-BOM';
        }

        if (mb_check_encoding($contents, 'UTF-8')) {
            /*
             * Un archivo compuesto solamente por caracteres ASCII también es
             * compatible con ANSI y UTF-8. No debe marcarse como error.
             */
            return 'UTF-8/ASCII compatible';
        }

        $detected = mb_detect_encoding(
            $contents,
            [
                'Windows-1252',
                'ISO-8859-1',
                'UTF-8',
            ],
            true
        );

        return $detected ?: null;
    }

    private function convertToUtf8(
        string $contents,
        ?string $detectedEncoding
    ): string {
        if (
            $detectedEncoding === 'UTF-8-BOM'
            || $detectedEncoding === 'UTF-8/ASCII compatible'
        ) {
            return $contents;
        }

        return mb_convert_encoding(
            $contents,
            'UTF-8',
            $detectedEncoding ?? 'Windows-1252'
        );
    }

    private function removeBom(string $contents): string
    {
        return preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $contents
        ) ?? $contents;
    }

    private function firstNonEmptyLine(string $contents): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        foreach ($lines as $line) {
            if (trim((string) $line) !== '') {
                return (string) $line;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));

        $header = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $header
        );

        return preg_replace('/\s+/', ' ', $header) ?? $header;
    }

    /**
     * @return array<string, mixed>
     */
    private function error(
        string $rule,
        string $message,
        ?string $value = null,
        ?string $help = null,
        ?string $expected = null,
        ?int $column = null,
        ?string $field = null
    ): array {
        return [
            'category' => 'CA',
            'severity' => 'error',
            'rule' => $rule,
            'message' => $message,
            'value' => $value,
            'display_value' => $value ?? '(no disponible)',
            'help' => $help,
            'expected' => $expected,
            'column' => $column,
            'field' => $field ?? 'Consistencia del archivo',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @param array<string, mixed>|null $extracted
     * @param array<int, string> $headers
     *
     * @return array<string, mixed>
     */
    private function result(
        array $errors,
        string $originalName,
        ?array $extracted = null,
        ?string $encoding = null,
        array $headers = [],
        int $totalRecords = 0
    ): array {
        return [
            'valid' => $errors === [],
            'status' => $errors === [] ? 'Exitoso' : 'Rechazado',
            'errors' => $errors,
            'summary' => [
                'category' => 'CA',
                'file_name' => $originalName,
                'encoding' => $encoding,
                'column_count' => count($headers),
                'total_records' => $totalRecords,
                'total_errors' => count($errors),
            ],
            'extracted' => $extracted,
        ];
    }
}