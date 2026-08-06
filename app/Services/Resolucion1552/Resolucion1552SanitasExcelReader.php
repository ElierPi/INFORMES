<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

final class Resolucion1552SanitasExcelReader
{
    public const FIELD_COUNT = 10;

    public const HEADERS = [
        'Código de habilitación',
        'Tipo de identificación del usuario',
        'Identificación del usuario',
        'Régimen',
        'Datos de contacto del usuario',
        'Especialidad',
        'Fecha en que el usuario solicita la cita',
        'Fecha en que el usuario solicita le sea asignada la cita',
        'Fecha para la cual se asigna la cita',
        'Número de horas-especialista, contratadas o disponibles para cada especialidad en el mes anterior a la cuantificación.',
    ];

    private const HEADER_ALIASES = [
        ['codigo de habilitacion', 'codigo habilitacion'],
        ['tipo de identificacion del usuario', 'tipo de identificacion', 'tipo de documento'],
        ['identificacion del usuario', 'numero de identificacion', 'documento'],
        ['regimen', 'regimen de afiliacion'],
        ['datos de contacto del usuario', 'numero telefonico del contacto', 'telefono', 'contacto'],
        ['especialidad', 'codigo especialidad'],
        ['fecha en que el usuario solicita la cita', 'fecha solicitud'],
        ['fecha en que el usuario solicita le sea asignada la cita', 'fecha solicitada'],
        ['fecha para la cual se asigna la cita', 'fecha asignada'],
        ['numero de horas especialista', 'horas especialista', 'horas contratadas'],
    ];

    /** @return array<string, mixed> */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel que será procesado.');
        }

        $spreadsheet = IOFactory::load($path);

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $header = $this->findHeader($worksheet);

            if ($header === null) {
                continue;
            }

            [$headerRow, $startColumn] = $header;
            $records = [];
            $sourceRows = [];
            $warnings = [];
            $highestRow = $worksheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $values = [];

                for ($offset = 0; $offset < self::FIELD_COUNT; $offset++) {
                    $values[] = $worksheet->getCell([$startColumn + $offset, $row])->getValue();
                }

                if (! array_filter($values, static fn (mixed $value): bool => trim((string) $value) !== '')) {
                    continue;
                }

                $nonEmpty = count(array_filter($values, static fn (mixed $value): bool => trim((string) $value) !== ''));

                if ($nonEmpty < 3) {
                    $warnings[] = $this->issue($row, 'Fila incompleta', 'La fila fue omitida porque no contiene información suficiente.', '', 'warning');
                    continue;
                }

                $records[] = [
                    $this->digits($values[0]),
                    strtoupper($this->clean($values[1])),
                    $this->identifier($values[2]),
                    strtoupper($this->clean($values[3])),
                    $this->phone($values[4]),
                    strtoupper($this->clean($values[5])),
                    $this->date($values[6]),
                    $this->date($values[7]),
                    $this->date($values[8]),
                    $this->number($values[9]),
                ];
                $sourceRows[] = $row;
            }

            if ($records === []) {
                throw new RuntimeException('La hoja reconocida no contiene registros para procesar.');
            }

            return [
                'sheet_name' => $worksheet->getTitle(),
                'header_row' => $headerRow,
                'start_column' => $startColumn,
                'records' => $records,
                'source_rows' => $sourceRows,
                'warnings' => $warnings,
            ];
        }

        throw new RuntimeException('No se encontró la fila de encabezados de los 10 campos de la 1552 de Sanitas.');
    }

    /** @return array{0:int,1:int}|null */
    private function findHeader($worksheet): ?array
    {
        $maxRow = min(30, $worksheet->getHighestDataRow());
        $maxColumn = min(30, $worksheet->getHighestDataColumn(1) ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()) : 30);

        for ($row = 1; $row <= $maxRow; $row++) {
            for ($start = 1; $start <= max(1, $maxColumn - self::FIELD_COUNT + 1); $start++) {
                $matches = 0;
                $matchedOffsets = [];

                for ($offset = 0; $offset < self::FIELD_COUNT; $offset++) {
                    $cell = $worksheet->getCell([$start + $offset, $row]);
                    $rawHeader = $cell->getValue();
                    $header = $this->normalizeHeader((string) ($rawHeader ?? $cell->getFormattedValue()));
                    $aliases = self::HEADER_ALIASES[$offset];

                    if ($header !== '' && $this->matchesAny($header, $aliases)) {
                        $matches++;
                        $matchedOffsets[] = $offset;
                    }
                }

                // Se aceptan los encabezados oficiales largos y sus variantes.
                // Exigimos las columnas de identificación y al menos dos de las
                // tres fechas para evitar detectar como encabezado una fila de datos.
                $hasIdentityAnchors = in_array(0, $matchedOffsets, true)
                    && in_array(1, $matchedOffsets, true)
                    && in_array(2, $matchedOffsets, true);
                $dateMatches = count(array_intersect([6, 7, 8], $matchedOffsets));

                if ($matches >= 7 && $hasIdentityAnchors && $dateMatches >= 2) {
                    return [$row, $start];
                }
            }
        }

        return null;
    }

    private function matchesAny(string $header, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if ($header === $alias || str_contains($header, $alias)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeader(string $value): string
    {
        // En Windows, iconv puede convertir caracteres acentuados en signos de
        // interrogación y romper encabezados oficiales como "Código" o
        // "Régimen". Primero normalizamos explícitamente los caracteres más
        // comunes del formato y después usamos iconv solo como respaldo.
        $value = strtr(trim($value), [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            "\u{00A0}" => ' ',
        ]);

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower($converted !== false ? $converted : $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function clean(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        $text = str_replace(["\t", "\r", "\n"], ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', $this->number($value)) ?? '';
    }

    private function identifier(mixed $value): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $this->number($value)) ?? '';
    }

    private function phone(mixed $value): string
    {
        $text = $this->clean($value);

        if ($text === '') {
            return '9999999999';
        }

        // Se admiten hasta dos teléfonos separados por coma.
        $parts = array_values(array_filter(array_map(
            static fn (string $part): string => preg_replace('/\D+/', '', $part) ?? '',
            explode(',', $text)
        )));

        return $parts === [] ? '9999999999' : implode(',', array_slice($parts, 0, 2));
    }

    private function number(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        return $this->clean($value);
    }

    private function date(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y');
            } catch (Throwable) {
                return $this->clean($value);
            }
        }

        $text = $this->clean($value);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, substr($text, 0, 10));
            if ($date instanceof DateTimeImmutable) {
                return $date->format('d/m/Y');
            }
        }

        return $text;
    }

    /** @return array<string, mixed> */
    private function issue(int $row, string $field, string $message, string $value, string $severity): array
    {
        return [
            'source_row' => $row,
            'field_name' => $field,
            'message' => $message,
            'value' => $value,
            'severity' => $severity,
        ];
    }
}
