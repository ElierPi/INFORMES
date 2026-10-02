<?php

namespace App\Services\Informe202;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class Resolution202TxtExporter
{
    private const TOTAL_VARIABLES = 119;

    private const DEFAULT_RECORD_TYPE = '2';

    private const DEFAULT_PROVIDER_CODE = '444300063502';


    public function __construct(
        private readonly Resolution202ExcelReader $reader
    ) {
    }

    /**
     * Genera el TXT final de Proteger a partir del Excel YA CORREGIDO.
     *
     * Formato:
     * - UTF-8 sin BOM.
     * - 119 campos por registro (variables 0 a 118).
     * - Separador pipe: |
     * - Sin encabezado.
     * - Sin registro de control.
     * - CRLF entre registros.
     * - Sin salto de línea adicional al final.
     *
     * @return array{
     *     path:string,
     *     filename:string,
     *     records:int,
     *     fields_per_record:int
     * }
     */
    public function export(
        string $correctedExcelPath,
        string $outputTxtPath,
        string $cutoffDate,
        string $nit
    ): array {
        if (! is_file($correctedExcelPath)) {
            throw new RuntimeException(
                'No se encontró el Excel corregido para generar el TXT de Proteger.'
            );
        }

        $excelResult = $this->reader->read(
            $correctedExcelPath,
            $cutoffDate
        );

        $records = $excelResult['records'] ?? [];

        if ($records === []) {
            throw new RuntimeException(
                'El Excel corregido no contiene registros para generar el TXT.'
            );
        }

        $spreadsheet = IOFactory::load(
            $correctedExcelPath
        );

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
                "No se encontró la hoja {$sheetName} para generar el TXT."
            );
        }

        $variableColumns =
            $excelResult['variable_columns'] ?? [];

        $lines = [];

        foreach (
            array_values($records)
            as $index => $record
        ) {
            $fields = [];

            for (
                $variable = 0;
                $variable < self::TOTAL_VARIABLES;
                $variable++
            ) {
                /*
                 * Proteger requiere consecutivo continuo en el TXT.
                 */
                if ($variable === 0) {
                    $fields[] = self::DEFAULT_RECORD_TYPE;
                    continue;
                }

                if ($variable === 1) {
                    $fields[] = (string) ($index + 1);
                    continue;
                }

                if ($variable === 2) {
                    $value = $record['variables'][2]
                        ?? self::DEFAULT_PROVIDER_CODE;

                    $value = trim((string) $value);

                    $fields[] = $value !== ''
                        ? $this->sanitize($value)
                        : self::DEFAULT_PROVIDER_CODE;

                    continue;
                }

                $normalizedValue =
                    $record['variables'][$variable]
                    ?? null;

                $cell = null;

                if (isset($variableColumns[$variable])) {
                    $coordinate =
                        $variableColumns[$variable]
                        . (int) $record['excel_row'];

                    $cell = $sheet->getCell(
                        $coordinate
                    );
                }

                $fields[] = $this->formatVariable(
                    variable: $variable,
                    normalizedValue: $normalizedValue,
                    cell: $cell
                );
            }

            if (count($fields) !== self::TOTAL_VARIABLES) {
                throw new RuntimeException(
                    sprintf(
                        'El registro %d produjo %d campos; Proteger exige exactamente %d.',
                        $index + 1,
                        count($fields),
                        self::TOTAL_VARIABLES
                    )
                );
            }

            $lines[] = implode('|', $fields);
        }

        $directory = dirname($outputTxtPath);

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0775, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'No fue posible crear la carpeta para el TXT de Proteger.'
            );
        }

        /*
         * El ejemplo oficial entregado usa CRLF y no termina
         * con un salto de línea adicional.
         */
        $content = implode("\r\n", $lines);

        if (file_put_contents(
            $outputTxtPath,
            $content
        ) === false) {
            throw new RuntimeException(
                'No fue posible guardar el TXT final de Proteger.'
            );
        }

        if (
            ! is_file($outputTxtPath)
            || filesize($outputTxtPath) === 0
        ) {
            throw new RuntimeException(
                'El TXT de Proteger fue generado vacío.'
            );
        }

        return [
            'path' => $outputTxtPath,
            'filename' => $this->filenameFor(
                cutoffDate: $cutoffDate,
                nit: $nit
            ),
            'records' => count($lines),
            'fields_per_record' =>
                self::TOTAL_VARIABLES,
        ];
    }

    public function filenameFor(
        string $cutoffDate,
        string $nit
    ): string {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            trim($cutoffDate)
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            ! $date instanceof DateTimeImmutable
            || (
                is_array($errors)
                && (
                    ($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0
                )
            )
        ) {
            throw new RuntimeException(
                'La fecha de corte no es válida para construir el nombre del TXT.'
            );
        }

        $nit = preg_replace(
            '/\D+/',
            '',
            trim($nit)
        ) ?? '';

        if (
            strlen($nit) < 9
            || strlen($nit) > 12
        ) {
            throw new RuntimeException(
                'El NIT de Proteger debe contener entre 9 y 12 dígitos.'
            );
        }

        return $nit
            . '_'
            . $date->format('mY')
            . '.txt';
    }

    private function formatVariable(
        int $variable,
        mixed $normalizedValue,
        ?Cell $cell
    ): string {
        $definition = config(
            "resolucion202.fields.{$variable}",
            []
        );

        $type = strtoupper(
            trim(
                (string) ($definition['type'] ?? '')
            )
        );

        if (
            $normalizedValue === null
            || trim((string) $normalizedValue) === ''
        ) {
            return '';
        }

        /*
         * Fechas: siempre AAAA-MM-DD.
         */
        if ($type === 'F') {
            return $this->formatDate(
                $normalizedValue,
                $cell
            );
        }

        /*
         * Campos decimales observados en el formato de Proteger.
         */
        if ($type === 'D') {
            return $this->formatDecimal(
                $variable,
                $normalizedValue
            );
        }

        /*
         * En códigos numéricos con máscara (por ejemplo 0004)
         * se conserva el valor formateado de Excel cuando este
         * representa únicamente dígitos y contiene ceros iniciales.
         */
        if (
            $type === 'N'
            && $cell instanceof Cell
        ) {
            $formatted = trim(
                (string) $cell->getFormattedValue()
            );

            if (
                preg_match('/^0\d+$/', $formatted) === 1
                && preg_match('/^\d+$/', $formatted) === 1
            ) {
                return $this->sanitize(
                    $formatted
                );
            }
        }

        if (
            $type === 'N'
            && is_numeric($normalizedValue)
        ) {
            $number = (float) $normalizedValue;

            if (floor($number) === $number) {
                return (string) (int) $number;
            }

            return $this->sanitize(
                rtrim(
                    rtrim(
                        number_format(
                            $number,
                            10,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                )
            );
        }

        return $this->sanitize(
            trim((string) $normalizedValue)
        );
    }

    private function formatDate(
        mixed $value,
        ?Cell $cell
    ): string {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $text
            ) === 1
        ) {
            return $text;
        }

        if (
            $cell instanceof Cell
            && is_numeric($cell->getValue())
            && (float) $cell->getValue() > 0
        ) {
            try {
                return ExcelDate::excelToDateTimeObject(
                    (float) $cell->getValue()
                )->format('Y-m-d');
            } catch (Throwable) {
                // Continúa con los formatos de texto.
            }
        }

        foreach (
            ['d/m/Y', 'd-m-Y', 'Y/m/d']
            as $format
        ) {
            $date = DateTimeImmutable::createFromFormat(
                '!' . $format,
                $text
            );

            $errors = DateTimeImmutable::getLastErrors();

            if (
                $date instanceof DateTimeImmutable
                && (
                    $errors === false
                    || (
                        ($errors['warning_count'] ?? 0) === 0
                        && ($errors['error_count'] ?? 0) === 0
                    )
                )
            ) {
                return $date->format('Y-m-d');
            }
        }

        /*
         * No se inventan fechas. Si el motor dejó un valor textual
         * especial, se conserva exactamente.
         */
        return $this->sanitize($text);
    }

    private function formatDecimal(
        int $variable,
        mixed $value
    ): string {
        $text = str_replace(
            ',',
            '.',
            trim((string) $value)
        );

        if (! is_numeric($text)) {
            return $this->sanitize($text);
        }

        $number = (float) $text;

        /*
         * PROTEGER valida los valores especiales/comodines como códigos,
         * no como resultados decimales.
         *
         * Por tanto:
         *   0.00   -> 0
         *   998.00 -> 998
         *   999.00 -> 999
         *
         * Esto aplica especialmente a:
         * - 104 Resultado de Hemoglobina
         * - 107 Resultado de Creatinina
         * - 109 Resultado de PSA
         *
         * Los resultados clínicos reales conservan dos decimales.
         */
        foreach ([0, 998, 999] as $specialValue) {
            if (
                abs(
                    $number - (float) $specialValue
                ) < 0.0000001
            ) {
                return (string) $specialValue;
            }
        }

        return number_format(
            $number,
            2,
            '.',
            ''
        );
    }

    private function sanitize(
        string $value
    ): string {
        $value = str_replace(
            ["\r\n", "\r", "\n", "\t"],
            ' ',
            $value
        );

        /*
         * Un pipe dentro del dato rompería la estructura de 119 campos.
         */
        $value = str_replace(
            '|',
            ' ',
            $value
        );

        return trim($value);
    }
}
