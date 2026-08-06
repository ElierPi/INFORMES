<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

final class Resolucion1552FamiliarExcelReader
{
    private const FIELD_COUNT = 14;

    /** @var array<int, array<int, string>> */
    private const HEADER_ALIASES = [
        0 => ['nit ips que asigna la cita', 'nit ips'],
        1 => ['codigo del prestador', 'codigo prestador'],
        2 => ['razon social de la ips', 'razon social ips'],
        3 => ['nombre del usuario', 'nombre usuario'],
        4 => ['tipo doc', 'tipo documento'],
        5 => ['documento identidad', 'numero documento identidad'],
        6 => ['codigo municipio', 'codigo del municipio'],
        7 => ['codigo cups', 'cups'],
        8 => ['consulta solicitada', 'especialidad'],
        9 => ['fecha solicitud de cita', 'fecha solicitud'],
        10 => ['fecha asignacion de cita', 'fecha asignacion'],
        11 => ['fecha de la cita', 'fecha cita'],
        12 => ['oportunidad en la asignacion de citas', 'oportunidad'],
        13 => ['horas contratadas para cada servicio', 'horas contratadas'],
    ];

    /** @return array<string, mixed> */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel que será procesado.');
        }

        $spreadsheet = IOFactory::load($path);

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $header = $this->findHeaderRow($worksheet);

            if ($header === null) {
                continue;
            }

            $headerRow = $header['row'];
            $startColumn = $header['start_column'];

            $records = [];
            $errors = [];
            $warnings = [];
            $highestRow = $worksheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $values = [];

                for ($offset = 0; $offset < self::FIELD_COUNT; $offset++) {
                    $values[] = $this->readCell($worksheet, $startColumn + $offset, $row);
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $record = $this->normalizeRecord($values, $row, $errors, $warnings);
                $records[] = $record;
            }

            if ($records === []) {
                throw new RuntimeException('La hoja reconocida no contiene registros para procesar.');
            }

            return [
                'sheet_name' => $worksheet->getTitle(),
                'header_row' => $headerRow,
                'start_column' => $startColumn,
                'records' => $records,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        throw new RuntimeException(
            'No se encontró la fila de encabezados de los 14 campos de Familiar de Colombia.'
        );
    }

    /** @return array{row:int,start_column:int}|null */
    private function findHeaderRow($worksheet): ?array
    {
        $rowLimit = min(30, $worksheet->getHighestDataRow());
        $columnLimit = min(20, Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()));

        for ($row = 1; $row <= $rowLimit; $row++) {
            for ($startColumn = 1; $startColumn <= max(1, $columnLimit - self::FIELD_COUNT + 1); $startColumn++) {
                $matches = 0;

                for ($offset = 0; $offset < self::FIELD_COUNT; $offset++) {
                    $column = $startColumn + $offset;
                    $value = $this->normalizeHeader(
                        (string) $worksheet->getCell([$column, $row])->getFormattedValue()
                    );

                    foreach (self::HEADER_ALIASES[$offset] as $alias) {
                        if ($value !== '' && str_contains($value, $alias)) {
                            $matches++;
                            break;
                        }
                    }
                }

                if ($matches >= 10) {
                    return [
                        'row' => $row,
                        'start_column' => $startColumn,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $values
     * @param array<int, array<string, mixed>> $errors
     * @param array<int, array<string, mixed>> $warnings
     * @return array<int, string>
     */
    private function normalizeRecord(array $values, int $row, array &$errors, array &$warnings): array
    {
        $normalized = [
            $this->digitsOnly($values[0] ?? null),
            $this->digitsOnly($values[1] ?? null),
            $this->cleanText($values[2] ?? null),
            $this->cleanText($values[3] ?? null),
            strtoupper($this->cleanText($values[4] ?? null)),
            $this->cleanIdentifier($values[5] ?? null),
            $this->digitsOnly($values[6] ?? null),
            strtoupper($this->cleanText($values[7] ?? null)),
            $this->cleanText($values[8] ?? null),
            $this->normalizeDate($values[9] ?? null, $row, 'Fecha de solicitud', $errors),
            $this->normalizeDate($values[10] ?? null, $row, 'Fecha de asignación', $errors),
            $this->normalizeDate($values[11] ?? null, $row, 'Fecha de la cita', $errors),
            '',
            $this->normalizeNumber($values[13] ?? null),
        ];

        $request = DateTimeImmutable::createFromFormat('!d/m/Y', $normalized[9]);
        $appointment = DateTimeImmutable::createFromFormat('!d/m/Y', $normalized[11]);

        if ($request instanceof DateTimeImmutable && $appointment instanceof DateTimeImmutable) {
            $days = (int) $request->diff($appointment)->format('%r%a');

            if ($days < 0) {
                $errors[] = $this->issue($row, 'Oportunidad', 'La fecha de la cita no puede ser anterior a la fecha de solicitud.', $normalized[11]);
            } else {
                $normalized[12] = (string) $days;

                $originalOpportunity = $this->normalizeNumber($values[12] ?? null);
                if ($originalOpportunity !== '' && $originalOpportunity !== $normalized[12]) {
                    $warnings[] = $this->issue(
                        $row,
                        'Oportunidad',
                        sprintf('Se recalculó la oportunidad de %s a %s días.', $originalOpportunity, $normalized[12]),
                        $originalOpportunity,
                        'warning'
                    );
                }
            }
        }

        $names = [
            'NIT IPS', 'Código del prestador', 'Razón social', 'Nombre del usuario',
            'Tipo de documento', 'Número de documento', 'Código de municipio', 'Código CUPS',
            'Consulta solicitada', 'Fecha de solicitud', 'Fecha de asignación', 'Fecha de la cita',
            'Oportunidad', 'Horas contratadas',
        ];

        foreach ($normalized as $index => $value) {
            if ($value === '') {
                $errors[] = $this->issue($row, $names[$index], 'El campo es obligatorio y se encuentra vacío.', $value);
            }
        }

        if ($normalized[4] !== '' && (strlen($normalized[4]) !== 2 || ! ctype_alpha($normalized[4]))) {
            $errors[] = $this->issue($row, 'Tipo de documento', 'Debe contener exactamente dos letras.', $normalized[4]);
        }

        if ($normalized[6] !== '' && strlen($normalized[6]) !== 5) {
            $errors[] = $this->issue($row, 'Código de municipio', 'Debe contener cinco dígitos DANE.', $normalized[6]);
        }

        if ($normalized[13] !== '' && (! is_numeric($normalized[13]) || (float) $normalized[13] < 0)) {
            $errors[] = $this->issue($row, 'Horas contratadas', 'Debe ser un número mayor o igual a cero.', $normalized[13]);
        }

        return $normalized;
    }

    private function readCell($worksheet, int $column, int $row): mixed
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $cell = $worksheet->getCell($coordinate);
        $value = $cell->getValue();

        return $value ?? $cell->getFormattedValue();
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return count(array_filter($values, static fn (mixed $value): bool => trim((string) $value) !== '')) === 0;
    }

    /** @param array<int, array<string, mixed>> $errors */
    private function normalizeDate(mixed $value, int $row, string $field, array &$errors): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_numeric($value) && (float) $value > 0) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y');
            } catch (Throwable) {
                // Continuar con los formatos textuales.
            }
        }

        $text = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $text);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $invalid = is_array($dateErrors)
                && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);

            if ($date instanceof DateTimeImmutable && ! $invalid) {
                return $date->format('d/m/Y');
            }
        }

        $errors[] = $this->issue($row, $field, 'La fecha no tiene un formato válido.', $text);

        return $text;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function cleanText(mixed $value): string
    {
        $text = trim((string) $value);
        $text = str_replace([';', "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function digitsOnly(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function cleanIdentifier(mixed $value): string
    {
        return preg_replace('/[^A-Za-z0-9]+/', '', (string) $value) ?? '';
    }

    private function normalizeNumber(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));

        if ($text === '' || ! is_numeric($text)) {
            return $text;
        }

        return rtrim(rtrim(number_format((float) $text, 2, '.', ''), '0'), '.');
    }

    /** @return array<string, mixed> */
    private function issue(int $row, string $field, string $message, mixed $value, string $severity = 'error'): array
    {
        return [
            'source_row' => $row,
            'field_name' => $field,
            'value' => $value,
            'message' => $message,
            'severity' => $severity,
        ];
    }
}
