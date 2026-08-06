<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

final class Resolucion1552ProtegerExcelReader
{
    private const FIELD_COUNT = 14;

    /** @var array<int, array<int, string>> */
    private const HEADER_ALIASES = [
        0 => ['dane', 'codigo prestador', 'codigo de habilitacion'],
        1 => ['ciudad', 'municipio', 'codigo dane municipio'],
        2 => ['tp', 'tipo documento', 'tipo de documento'],
        3 => ['documento', 'numero documento', 'identificacion'],
        4 => ['direccion', 'direccion residencia'],
        5 => ['telefono', 'celular', 'movil'],
        6 => ['correo', 'email', 'correo electronico'],
        7 => ['fecha solictud', 'fecha solicitud', 'fecha de solicitud'],
        8 => ['fecha asignacion', 'fecha de asignacion'],
        9 => ['fecha requerida', 'fecha deseada'],
        10 => ['cups', 'codigo cups'],
        11 => ['horas contratadas', 'horas'],
        12 => ['2'],
        13 => ['4'],
    ];

    /** @return array<string, mixed> */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel que será procesado.');
        }

        $spreadsheet = IOFactory::load($path);

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $headerRow = $this->findHeaderRow($worksheet);

            if ($headerRow === null) {
                continue;
            }

            $records = [];
            $errors = [];
            $warnings = [];
            $sourceRows = [];
            $skippedRows = 0;
            $highestRow = $worksheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $values = [];

                for ($column = 1; $column <= self::FIELD_COUNT; $column++) {
                    $values[] = $this->readCell($worksheet, $column, $row);
                }

                $nonEmpty = count(array_filter(
                    $values,
                    static fn (mixed $value): bool => trim((string) $value) !== ''
                ));

                if ($nonEmpty === 0) {
                    continue;
                }

                if ($nonEmpty <= 1) {
                    $skippedRows++;
                    $warnings[] = $this->issue(
                        $row,
                        'Fila incompleta',
                        'La fila fue omitida porque solo contiene un campo diligenciado.',
                        trim((string) ($values[0] ?? '')),
                        'warning'
                    );
                    continue;
                }

                $records[] = $this->normalizeRecord($values, $row, $errors, $warnings);
                $sourceRows[] = $row;
            }

            if ($records === []) {
                throw new RuntimeException('La hoja reconocida no contiene registros completos para procesar.');
            }

            $duplicateCount = $this->appendDuplicateWarnings($records, $sourceRows, $warnings);

            return [
                'sheet_name' => $worksheet->getTitle(),
                'header_row' => $headerRow,
                'records' => $records,
                'source_rows' => $sourceRows,
                'errors' => $errors,
                'warnings' => $warnings,
                'duplicate_count' => $duplicateCount,
                'skipped_rows' => $skippedRows,
            ];
        }

        throw new RuntimeException(
            'No se encontró la fila de encabezados de los 14 campos del formato 1552 de Proteger.'
        );
    }

    private function findHeaderRow($worksheet): ?int
    {
        $limit = min(30, $worksheet->getHighestDataRow());

        for ($row = 1; $row <= $limit; $row++) {
            $matches = 0;

            for ($column = 1; $column <= self::FIELD_COUNT; $column++) {
                $value = $this->normalizeHeader(
                    (string) $worksheet->getCell([$column, $row])->getFormattedValue()
                );

                foreach (self::HEADER_ALIASES[$column - 1] as $alias) {
                    if ($value === $alias || ($alias !== '2' && $alias !== '4' && str_contains($value, $alias))) {
                        $matches++;
                        break;
                    }
                }
            }

            if ($matches >= 12) {
                return $row;
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
        $phoneOriginal = trim((string) ($values[5] ?? ''));
        $phone = $this->cleanPhone($values[5] ?? null);

        if ($phone === '') {
            $phone = '3000000000';
            $warnings[] = $this->issue(
                $row,
                'Teléfono',
                'El campo venía vacío y fue reemplazado automáticamente por 3000000000.',
                $phoneOriginal,
                'warning'
            );
        }

        $record = [
            $this->digitsOnly($values[0] ?? null),
            $this->digitsOnly($values[1] ?? null),
            strtoupper($this->cleanText($values[2] ?? null)),
            $this->cleanIdentifier($values[3] ?? null),
            $this->cleanText($values[4] ?? null),
            $phone,
            $this->cleanEmail($values[6] ?? null),
            $this->normalizeDate($values[7] ?? null, $row, 'Fecha de solicitud', $errors),
            $this->normalizeDate($values[8] ?? null, $row, 'Fecha de asignación', $errors),
            $this->normalizeDate($values[9] ?? null, $row, 'Fecha requerida', $errors),
            strtoupper($this->cleanIdentifier($values[10] ?? null)),
            $this->normalizeNumber($values[11] ?? null),
            $this->normalizeNumber($values[12] ?? null),
            $this->normalizeNumber($values[13] ?? null),
        ];

        $names = [
            'Código del prestador', 'Código DANE del municipio', 'Tipo de documento',
            'Número de documento', 'Dirección', 'Teléfono', 'Correo',
            'Fecha de solicitud', 'Fecha de asignación', 'Fecha requerida',
            'Código CUPS', 'Horas contratadas', 'Campo 13', 'Campo 14',
        ];

        foreach ($record as $index => $value) {
            if ($value === '') {
                $errors[] = $this->issue(
                    $row,
                    $names[$index],
                    'El campo es obligatorio y se encuentra vacío.',
                    $value
                );
            }
        }

        if ($record[0] !== '' && strlen($record[0]) !== 12) {
            $errors[] = $this->issue($row, 'Código del prestador', 'Debe contener 12 dígitos.', $record[0]);
        }

        if ($record[1] !== '' && strlen($record[1]) !== 5) {
            $errors[] = $this->issue($row, 'Código DANE del municipio', 'Debe contener 5 dígitos.', $record[1]);
        }

        if ($record[2] !== '' && (strlen($record[2]) !== 2 || ! ctype_alpha($record[2]))) {
            $errors[] = $this->issue($row, 'Tipo de documento', 'Debe contener exactamente dos letras.', $record[2]);
        }

        if ($record[6] !== '' && ! filter_var($record[6], FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->issue($row, 'Correo', 'El correo electrónico no tiene un formato válido.', $record[6]);
        }

        foreach ([11 => 'Horas contratadas', 12 => 'Campo 13', 13 => 'Campo 14'] as $index => $field) {
            if ($record[$index] !== '' && ! is_numeric($record[$index])) {
                $errors[] = $this->issue($row, $field, 'Debe contener un valor numérico.', $record[$index]);
            }
        }

        return $record;
    }

    /**
     * @param array<int, array<int, string>> $records
     * @param array<int, int> $sourceRows
     * @param array<int, array<string, mixed>> $warnings
     */
    private function appendDuplicateWarnings(array $records, array $sourceRows, array &$warnings): int
    {
        $firstByKey = [];
        $duplicates = 0;

        foreach ($records as $index => $record) {
            $key = implode("\x1F", $record);
            $sourceRow = $sourceRows[$index] ?? null;

            if (isset($firstByKey[$key])) {
                $duplicates++;
                $warnings[] = $this->issue(
                    $sourceRow ?? 0,
                    'Registro duplicado',
                    sprintf(
                        'El registro es idéntico al de la fila %d. Se conserva porque el archivo aceptado de referencia permite duplicados.',
                        $firstByKey[$key]
                    ),
                    ($record[2] ?? '') . ' ' . ($record[3] ?? ''),
                    'warning'
                );
                continue;
            }

            $firstByKey[$key] = $sourceRow ?? 0;
        }

        return $duplicates;
    }

    private function readCell($worksheet, int $column, int $row): mixed
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $cell = $worksheet->getCell($coordinate);
        $value = $cell->getValue();

        return $value ?? $cell->getFormattedValue();
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
                // Continuar con formatos de texto.
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
        $text = str_replace([',', ';', "\r", "\n", "\t"], ' ', $text);

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

    private function cleanPhone(mixed $value): string
    {
        return preg_replace('/[^0-9]+/', '', (string) $value) ?? '';
    }

    private function cleanEmail(mixed $value): string
    {
        return mb_strtolower(trim(str_replace([',', ';', "\r", "\n", "\t", ' '], '', (string) $value)), 'UTF-8');
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
