<?php

namespace App\Services\Gestantes;

use DateTimeInterface;
use RuntimeException;
use Throwable;

class GestantesTxtGenerator
{
    /**
     * Orden obligatorio de los registros dentro del TXT.
     */
    private const SHEET_ORDER = [
        'control',
        'identificacion',
        'atenciones',
        'seguimientos',
        'urgencias',
    ];

    /**
     * Genera el TXT definitivo para SIGIRES.
     */
public function generate(
    array $data,
    string $outputPath
): string {
    try {
        $lines = [];

        foreach (self::SHEET_ORDER as $sheetKey) {
            $rows = $this->resolveRows(
                data: $data,
                sheetKey: $sheetKey
            );

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $values = $this->resolveRowValues($row);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                if (
                    $this->isTemplatePlaceholderRow(
                        sheetKey: $sheetKey,
                        values: $values
                    )
                ) {
                    continue;
                }

                $lines[] = $this->buildLine($values);
            }
        }

        if ($lines === []) {
            throw new RuntimeException(
                'No existen registros válidos para generar el TXT.'
            );
        }

        /*
         * Obtiene automáticamente el código de habilitación
         * desde el registro tipo 2.
         */
        $codigoHabilitacion = $this->resolveCodigoHabilitacionIps(
            data: $data
        );

        /*
         * La fecha corresponde al día en que se genera el archivo.
         * Formato requerido: DDMMAAAA
         */
        $fechaGeneracion = now()->format('dmY');

        $fileName = sprintf(
            'GESTANTE_MSPS_%s_%s.txt',
            $codigoHabilitacion,
            $fechaGeneracion
        );

        /*
         * Conserva solamente el directorio recibido
         * y reemplaza el nombre anterior por el nombre oficial.
         */
        $directory = dirname($outputPath);

        if (! is_dir($directory)) {
            if (
                ! mkdir(
                    $directory,
                    0775,
                    true
                )
                && ! is_dir($directory)
            ) {
                throw new RuntimeException(
                    "No fue posible crear el directorio: {$directory}"
                );
            }
        }

        $finalOutputPath = $directory
            . DIRECTORY_SEPARATOR
            . $fileName;

        /*
         * CRLF para compatibilidad con Windows.
         * No se agrega una línea vacía al final.
         */
        $utf8Content = implode("\r\n", $lines);

        /*
         * SIGIRES solicita codificación ANSI.
         */
        $ansiContent = mb_convert_encoding(
            $utf8Content,
            'Windows-1252',
            'UTF-8'
        );

        $writtenBytes = file_put_contents(
            $finalOutputPath,
            $ansiContent
        );

        if ($writtenBytes === false) {
            throw new RuntimeException(
                'No fue posible guardar el archivo TXT.'
            );
        }

        return $finalOutputPath;
    } catch (Throwable $exception) {
        throw new RuntimeException(
            'No fue posible generar el TXT de gestantes: '
            . $exception->getMessage(),
            previous: $exception
        );
    }
}

/**
 * Obtiene el código de habilitación de la IPS
 * desde el registro tipo 2.
 */
private function resolveCodigoHabilitacionIps(
    array $data
): string {
    $rows = $this->resolveRows(
        data: $data,
        sheetKey: 'identificacion'
    );

    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }

        $values = $this->resolveRowValues($row);

        /*
         * Estructura del registro tipo 2:
         *
         * índice 0 = tipo de registro
         * índice 5 = código de habilitación de la IPS
         */
        $tipoRegistro = trim(
            (string) ($values[0] ?? '')
        );

        if ($tipoRegistro !== '2') {
            continue;
        }

        $codigoHabilitacion = trim(
            (string) ($values[5] ?? '')
        );

        if ($codigoHabilitacion === '') {
            continue;
        }

        /*
         * El código solo debe contener números.
         */
        if (! preg_match('/^\d+$/', $codigoHabilitacion)) {
            throw new RuntimeException(
                'El código de habilitación de la IPS no es válido: '
                . $codigoHabilitacion
            );
        }

        return $codigoHabilitacion;
    }

    throw new RuntimeException(
        'No fue posible obtener el código de habilitación de la IPS '
        . 'desde el registro tipo 2.'
    );
}
    /**
     * Busca los registros dentro del arreglo entregado por el Reader.
     *
     * Soporta varias estructuras posibles:
     *
     * $data['control']
     * $data['sheets']['control']
     * $data['1 - Control']
     * $data['sheets']['1 - Control']
     */
    private function resolveRows(
        array $data,
        string $sheetKey
    ): array {
        $aliases = $this->sheetAliases($sheetKey);

        foreach ($aliases as $alias) {
            if (
                isset($data[$alias])
                && is_array($data[$alias])
            ) {
                return $this->extractRows($data[$alias]);
            }
        }

        $containers = [
            'sheets',
            'hojas',
            'data',
            'datos',
            'records',
            'registros',
        ];

        foreach ($containers as $container) {
            if (
                ! isset($data[$container])
                || ! is_array($data[$container])
            ) {
                continue;
            }

            foreach ($aliases as $alias) {
                if (
                    isset($data[$container][$alias])
                    && is_array($data[$container][$alias])
                ) {
                    return $this->extractRows(
                        $data[$container][$alias]
                    );
                }
            }

            /*
             * También busca por nombres normalizados.
             */
            foreach (
                $data[$container] as $candidateName => $candidateData
            ) {
                if (! is_array($candidateData)) {
                    continue;
                }

                if (
                    $this->matchesSheet(
                        candidateName: (string) $candidateName,
                        sheetKey: $sheetKey
                    )
                ) {
                    return $this->extractRows($candidateData);
                }
            }
        }

        /*
         * Último intento: buscar directamente en todas las claves.
         */
        foreach ($data as $candidateName => $candidateData) {
            if (! is_array($candidateData)) {
                continue;
            }

            if (
                $this->matchesSheet(
                    candidateName: (string) $candidateName,
                    sheetKey: $sheetKey
                )
            ) {
                return $this->extractRows($candidateData);
            }
        }

        return [];
    }

    /**
     * Extrae filas cuando la hoja tiene una estructura como:
     *
     * ['rows' => [...]]
     * ['records' => [...]]
     * ['registros' => [...]]
     * o directamente [...]
     */
    private function extractRows(array $sheetData): array
    {
        $rowContainers = [
            'rows',
            'records',
            'registros',
            'data',
            'datos',
        ];

        foreach ($rowContainers as $container) {
            if (
                isset($sheetData[$container])
                && is_array($sheetData[$container])
            ) {
                return array_values(
                    $sheetData[$container]
                );
            }
        }

        /*
         * Si el arreglo ya es una lista de filas.
         */
        if (array_is_list($sheetData)) {
            return $sheetData;
        }

        /*
         * Si representa una sola fila asociativa.
         */
        if ($this->looksLikeSingleRow($sheetData)) {
            return [$sheetData];
        }

        return [];
    }

    /**
     * Obtiene los valores de la fila conservando el orden.
     */
    private function resolveRowValues(array $row): array
    {
        $containers = [
            'values',
            'valores',
            'columns',
            'columnas',
            'data',
            'datos',
        ];

        foreach ($containers as $container) {
            if (
                isset($row[$container])
                && is_array($row[$container])
            ) {
                return array_values(
                    $row[$container]
                );
            }
        }

        /*
         * El Reader puede agregar metadatos que no deben salir al TXT.
         */
        $metadataKeys = [
            '_row',
            '_sheet',
            '_errors',
            '_valid',
            'excel_row',
            'row_number',
            'sheet',
            'fila',
            'hoja',
            'errors',
            'errores',
        ];

        foreach ($metadataKeys as $metadataKey) {
            unset($row[$metadataKey]);
        }

        return array_values($row);
    }

    /**
     * Construye una línea del TXT.
     */
    private function buildLine(array $values): string
    {
        $normalized = array_map(
            fn (mixed $value): string =>
                $this->normalizeValue($value),
            $values
        );

        return implode('|', $normalized);
    }

    /**
     * Normaliza cada valor antes de escribirlo.
     */
    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            return $this->normalizeDecimal($value);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return trim((string) $value);
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        /*
         * El separador pipe no puede aparecer dentro de un campo.
         */
        $value = str_replace('|', '', $value);

        /*
         * No se permiten saltos de línea dentro de un campo.
         */
        $value = str_replace(
            ["\r\n", "\r", "\n"],
            ' ',
            $value
        );

        /*
         * El archivo no debe incluir valores entre comillas.
         */
        $value = trim($value, "\"'");

        /*
         * Evita espacios duplicados.
         */
        $value = preg_replace(
            '/[ \t]+/u',
            ' ',
            $value
        ) ?? $value;

        return trim($value);
    }

    private function normalizeDecimal(float $value): string
    {
        /*
         * Genera el número sin separador de miles
         * y usando punto decimal.
         */
        $formatted = number_format(
            $value,
            10,
            '.',
            ''
        );

        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');

        return $formatted === '-0'
            ? '0'
            : $formatted;
    }

    private function sheetAliases(string $sheetKey): array
    {
        return match ($sheetKey) {
            'control' => [
                'control',
                '1 - Control',
                '1-Control',
                '1_control',
            ],

            'identificacion' => [
                'identificacion',
                'identificación',
                'id_gestantes',
                'id gestantes',
                '2 - ID gestantes',
                '2 - Identificación',
                '2 - Identificacion',
            ],

            'atenciones' => [
                'atenciones',
                'atención',
                'atencion',
                '3 - Atenciones',
                '3 - Atención',
                '3 - Atencion',
            ],

            'seguimientos' => [
                'seguimientos',
                'seguimiento',
                '4 - Seguimientos',
                '4 - Seguimiento',
            ],

            'urgencias' => [
                'urgencias',
                'urgencia',
                '5 - Urgencias',
                '5 - Urgencia',
            ],

            default => [$sheetKey],
        };
    }

    private function matchesSheet(
        string $candidateName,
        string $sheetKey
    ): bool {
        $candidate = $this->normalizeIdentifier(
            $candidateName
        );

        foreach ($this->sheetAliases($sheetKey) as $alias) {
            $normalizedAlias = $this->normalizeIdentifier(
                $alias
            );

            if (
                $candidate === $normalizedAlias
                || str_contains(
                    $candidate,
                    $normalizedAlias
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower(trim($value));

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
            '/[^a-z0-9]+/',
            ' ',
            $value
        ) ?? $value;
    }

    private function looksLikeSingleRow(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (
                $value !== null
                && (! is_string($value) || trim($value) !== '')
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evita incluir filas de ejemplo de la plantilla.
     */
    private function isTemplatePlaceholderRow(
        string $sheetKey,
        array $values
    ): bool {
        /*
         * El registro de control nunca se considera una fila de ejemplo.
         */
        if ($sheetKey === 'control') {
            return false;
        }

        /*
         * Para cualquier registro de detalle:
         * índice 0 = tipo de registro
         * índice 1 = consecutivo
         *
         * Si únicamente existen esos datos, la fila no es real.
         */
        $businessValues = array_slice(
            $values,
            2
        );

        return $this->isEmptyRow($businessValues);
    }
/**
 * Obtiene el código de habilitación de la IPS
 * desde el registro tipo 2.
 */

}