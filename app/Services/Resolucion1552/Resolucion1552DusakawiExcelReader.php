<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class Resolucion1552DusakawiExcelReader
{
    private const CONTROL_COLUMNS = 6;
    private const DETAIL_COLUMNS = 11;

    /**
     * Detecta y lee el formato oficial de DUSAKAWI:
     *
     * - Primera fila: registro tipo 1 de control, sin encabezados.
     * - Filas siguientes: registros tipo 2 de detalle.
     *
     * @return array<string, mixed>|null
     */
    public function read(string $path): ?array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel que será procesado.');
        }

        $spreadsheet = IOFactory::load($path);

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if (! $this->looksLikeDusakawiFormat($worksheet)) {
                continue;
            }

            return $this->readWorksheet($worksheet);
        }

        return null;
    }

    private function looksLikeDusakawiFormat(Worksheet $worksheet): bool
    {
        if ($worksheet->getHighestDataRow() < 2) {
            return false;
        }

        $firstType = trim((string) $worksheet->getCell('A1')->getFormattedValue());
        $secondType = trim((string) $worksheet->getCell('A2')->getFormattedValue());

        return $firstType === '1' && $secondType === '2';
    }

    /** @return array<string, mixed> */
    private function readWorksheet(Worksheet $worksheet): array
    {
        $warnings = [];
        $errors = [];

        $control = [];
        for ($column = 1; $column <= self::CONTROL_COLUMNS; $column++) {
            $control[] = $this->readCell($worksheet, $column, 1);
        }

        $providerCode = $this->digitsOnly($control[2] ?? null);
        if ($providerCode === '') {
            $errors[] = $this->issue(
                row: 1,
                field: 'Código de habilitación',
                message: 'El registro tipo 1 no contiene un código de habilitación válido.'
            );
        }

        $startDate = $this->normalizeDate(
            value: $control[3] ?? null,
            row: 1,
            field: 'Fecha inicial',
            warnings: $warnings,
            errors: $errors,
            clampInvalidDay: false,
        );

        $endDate = $this->normalizeDate(
            value: $control[4] ?? null,
            row: 1,
            field: 'Fecha final',
            warnings: $warnings,
            errors: $errors,
            clampInvalidDay: true,
        );

        $rows = [];
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $recordType = trim((string) $worksheet->getCell("A{$row}")->getFormattedValue());

            if ($recordType === '') {
                continue;
            }

            if ($recordType !== '2') {
                $errors[] = $this->issue(
                    row: $row,
                    field: 'Tipo de registro',
                    message: sprintf(
                        'Se esperaba un registro tipo 2 y se encontró "%s".',
                        $recordType
                    )
                );
                continue;
            }

            $values = [];
            for ($column = 1; $column <= self::DETAIL_COLUMNS; $column++) {
                $values[] = $this->readCell($worksheet, $column, $row);
            }

            $expectedConsecutive = count($rows) + 1;
            $currentConsecutive = (int) preg_replace('/\D+/', '', (string) ($values[1] ?? ''));

            if ($currentConsecutive !== $expectedConsecutive) {
                $warnings[] = $this->issue(
                    row: $row,
                    field: 'Consecutivo',
                    message: sprintf(
                        'El consecutivo se organizó automáticamente de %s a %d.',
                        (string) ($values[1] ?? ''),
                        $expectedConsecutive
                    ),
                    severity: 'warning'
                );
            }

            $values[0] = '2';
            $values[1] = (string) $expectedConsecutive;
            $values[2] = strtoupper(trim((string) ($values[2] ?? '')));
            $values[3] = trim((string) ($values[3] ?? ''));
            $values[4] = trim((string) ($values[4] ?? ''));
            $values[5] = $this->normalizePhone($values[5] ?? null);

            foreach ([6 => 'Fecha de solicitud', 7 => 'Fecha de asignación', 8 => 'Fecha de cita'] as $index => $field) {
                $values[$index] = $this->normalizeDate(
                    value: $values[$index] ?? null,
                    row: $row,
                    field: $field,
                    warnings: $warnings,
                    errors: $errors,
                    clampInvalidDay: false,
                );
            }

            $values[9] = trim((string) ($values[9] ?? ''));
            $values[10] = trim((string) ($values[10] ?? ''));

            if ($values[2] === '') {
                $errors[] = $this->issue(
                    row: $row,
                    field: 'Tipo de identificación',
                    message: 'El tipo de identificación se encuentra vacío.'
                );
            }

            if ($values[3] === '') {
                $errors[] = $this->issue(
                    row: $row,
                    field: 'Número de identificación',
                    message: 'El número de identificación se encuentra vacío.'
                );
            }

            if ($values[9] === '') {
                $errors[] = $this->issue(
                    row: $row,
                    field: 'Especialidad',
                    message: 'El código de especialidad se encuentra vacío.'
                );
            }

            if ($values[10] === '') {
                $errors[] = $this->issue(
                    row: $row,
                    field: 'CUPS',
                    message: 'El código CUPS se encuentra vacío.'
                );
            }

            $rows[] = $values;
        }

        if ($rows === []) {
            $errors[] = $this->issue(
                row: 2,
                field: 'Registros de detalle',
                message: 'No se encontraron registros tipo 2 para procesar.'
            );
        }

        $this->appendPeriodWarning(
            startDate: $startDate,
            endDate: $endDate,
            rows: $rows,
            warnings: $warnings,
        );

        $reportedCount = (int) preg_replace('/\D+/', '', (string) ($control[5] ?? ''));
        $actualCount = count($rows);

        if ($reportedCount !== $actualCount) {
            $warnings[] = $this->issue(
                row: 1,
                field: 'Cantidad de registros',
                message: sprintf(
                    'La línea de control indicaba %d registros y el Excel contiene %d. Se actualizará automáticamente.',
                    $reportedCount,
                    $actualCount
                ),
                severity: 'warning'
            );
        }

        $normalizedControl = [
            '1',
            trim((string) ($control[1] ?? '')),
            $providerCode,
            $startDate,
            $endDate,
            (string) $actualCount,
        ];

        return [
            'recognized' => true,
            'sheet_name' => $worksheet->getTitle(),
            'control' => $normalizedControl,
            'rows' => $rows,
            'records_count' => $actualCount,
            'provider_code' => $providerCode,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function readCell(Worksheet $worksheet, int $column, int $row): mixed
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        $cell = $worksheet->getCell($coordinate);
        $value = $cell->getValue();

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        return $value ?? $cell->getFormattedValue();
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    private function normalizeDate(
        mixed $value,
        int $row,
        string $field,
        array &$warnings,
        array &$errors,
        bool $clampInvalidDay,
    ): string {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value) && (float) $value > 0) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                // Continuar con el análisis textual.
            }
        }

        $original = trim((string) $value);
        if ($original === '') {
            $errors[] = $this->issue(
                row: $row,
                field: $field,
                message: "El campo {$field} se encuentra vacío."
            );
            return '';
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $original);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $hasErrors = is_array($dateErrors)
                && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);

            if ($date instanceof DateTimeImmutable && ! $hasErrors) {
                return $date->format('Y-m-d');
            }
        }

        if ($clampInvalidDay && preg_match('/^(\d{1,2})[-\/]([0-1]?\d)[-\/](\d{4})$/', $original, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            if ($month >= 1 && $month <= 12) {
                $lastDay = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
                    ->modify('last day of this month')
                    ->format('d');

                if ($day > $lastDay) {
                    $corrected = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
                    $warnings[] = $this->issue(
                        row: $row,
                        field: $field,
                        message: sprintf(
                            'La fecha inválida "%s" se ajustó al último día válido del mes: %s.',
                            $original,
                            $corrected
                        ),
                        severity: 'warning'
                    );
                    return $corrected;
                }
            }
        }

        $errors[] = $this->issue(
            row: $row,
            field: $field,
            message: sprintf('La fecha "%s" no es válida.', $original)
        );

        return $original;
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<int, array<string, mixed>> $warnings
     */
    private function appendPeriodWarning(
        string $startDate,
        string $endDate,
        array $rows,
        array &$warnings,
    ): void {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

        if (! $start instanceof DateTimeImmutable || ! $end instanceof DateTimeImmutable) {
            return;
        }

        $affectedRecords = 0;
        $minimum = null;
        $maximum = null;

        foreach ($rows as $row) {
            $outside = false;

            foreach ([6, 7, 8] as $index) {
                $value = trim((string) ($row[$index] ?? ''));
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

                if (! $date instanceof DateTimeImmutable) {
                    continue;
                }

                if ($minimum === null || $date < $minimum) {
                    $minimum = $date;
                }

                if ($maximum === null || $date > $maximum) {
                    $maximum = $date;
                }

                if ($date < $start || $date > $end) {
                    $outside = true;
                }
            }

            if ($outside) {
                $affectedRecords++;
            }
        }

        if ($affectedRecords === 0 || $minimum === null || $maximum === null) {
            return;
        }

        $warnings[] = $this->issue(
            row: 1,
            field: 'Período reportado',
            message: sprintf(
                '%d registros contienen fechas fuera del período de control %s a %s. El rango real detectado en los detalles es %s a %s. Revise el período antes del cargue.',
                $affectedRecords,
                $startDate,
                $endDate,
                $minimum->format('Y-m-d'),
                $maximum->format('Y-m-d')
            ),
            severity: 'warning'
        );
    }

    private function normalizePhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        if ($digits === '' || (int) $digits === 0) {
            return '0000000000';
        }

        return $digits;
    }

    private function digitsOnly(mixed $value): string
    {
        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }

    /** @return array<string, mixed> */
    private function issue(
        int $row,
        string $field,
        string $message,
        string $severity = 'error',
    ): array {
        return [
            'type' => 'dusakawi_preformatted_excel',
            'severity' => $severity,
            'source_row' => $row,
            'field' => $field,
            'message' => $message,
        ];
    }
}
