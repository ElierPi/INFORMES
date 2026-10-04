<?php

namespace App\Services\Resolucion1604\FamiliarColombia;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

final class Resolucion1604FamiliarExcelReader
{
    public const FIELD_COUNT = 39;

    /** @var array<int, string> */
    public const FIELD_NAMES = [
        'NIT',
        'MUNICIPIO',
        'DEPARTAMENTO',
        'MODALIDAD_CONTRATO',
        'TIPO_DOCUMENTO',
        'NUMERO_DOCUMENTO',
        'NOMBRE_AFILIADO',
        'EDAD_AFILIADO',
        'TELEFONO_AFILIADO',
        'CODIGO_DX',
        'REGIMEN',
        'NRO_FORMULA',
        'FINANCIAMIENTO',
        'NOMBRE_TECNOLOGIA',
        'CUM',
        'REGISTRO_SANITARIO',
        'PRESENTACION_FARMACEUTICA',
        'LABORATORIO',
        'ATC',
        'DOSIS_DIARIA',
        'CANTIDAD_PRESCRITA',
        'FECHA_SOLICITUD',
        'FECHA_ENTREGA',
        'TIEMPO_ENTREGA',
        'CANTIDAD_PENDIENTE',
        'FECHA_ENTREGA_PENDIENTE',
        'DIAS_MORA',
        'COSTO_UNITARIO',
        'COSTO_TOTAL',
        'NO_DE_FACTURA',
        'FECHA_FACTURA',
        'CUOTA_MODERADORA',
        'REGISTRO_MEDICO',
        'NOMBRE_MEDICO',
        'OBSERVACION_PQRS_TUTELA',
        'TIPO_DISPENSACION',
        'NUMERO_CONTRATO',
        'LOTE',
        'OBSERVACION',
    ];

    private const DOCUMENT_TYPES = [
        'CC', 'TI', 'RC', 'CE', 'PA', 'PE', 'PT', 'MS', 'AS', 'CD', 'NV', 'SC', 'CN',
    ];

    /** @return array<string, mixed> */
    public function read(string $path, string $period): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel que será procesado.');
        }

        $periodDate = DateTimeImmutable::createFromFormat('!Y-m', $period);

        if (! $periodDate instanceof DateTimeImmutable) {
            throw new RuntimeException('El período seleccionado no es válido.');
        }

        // Carga optimizada: primero se consultan solo los nombres de las hojas y
        // después se abre únicamente la hoja del período solicitado. Esto evita
        // procesar validaciones, estilos y celdas de meses que no se usarán.
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        if (method_exists($reader, 'setIgnoreRowsWithNoCells')) {
            $reader->setIgnoreRowsWithNoCells(true);
        }

        $sheetNames = $reader->listWorksheetNames($path);
        $targetSheetName = $this->findPeriodSheetName($sheetNames, $periodDate);

        if ($targetSheetName === null) {
            throw new RuntimeException(sprintf(
                'No se encontró una hoja correspondiente a %s %s.',
                $this->monthName((int) $periodDate->format('n')),
                $periodDate->format('Y')
            ));
        }

        $reader->setLoadSheetsOnly([$targetSheetName]);
        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getSheetByName($targetSheetName);

        if ($worksheet === null) {
            throw new RuntimeException('No fue posible cargar la hoja seleccionada del período.');
        }

        $headerRow = $this->findHeaderRow($worksheet);

        if ($headerRow === null) {
            throw new RuntimeException('No se encontró la fila de encabezados de los 39 campos de la Resolución 1604.');
        }

        $records = [];
        $errors = [];
        $warnings = [];
        $highestRow = $worksheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = [];

            for ($column = 1; $column <= self::FIELD_COUNT; $column++) {
                $values[] = $this->readCell($worksheet, $column, $row);
            }

            if ($this->isEmptyRow($values) || $this->looksLikeSecondaryHeader($values)) {
                continue;
            }

            $record = $this->normalizeRecord($values, $row, $errors, $warnings);

            if ($record !== null) {
                $records[] = [
                    'source_row' => $row,
                    'values' => $record,
                ];
            }
        }

        if ($records === []) {
            throw new RuntimeException('La hoja del período no contiene registros válidos para procesar.');
        }

        return [
            'sheet_name' => $worksheet->getTitle(),
            'header_row' => $headerRow,
            'records' => $records,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @param array<int, string> $sheetNames */
    private function findPeriodSheetName(array $sheetNames, DateTimeImmutable $period): ?string
    {
        $targetMonth = (int) $period->format('n');
        $targetYear = $period->format('Y');
        $month = $this->normalizeHeader($this->monthName($targetMonth));

        foreach ($sheetNames as $sheetName) {
            $normalized = $this->normalizeHeader($sheetName);
            if (str_contains($normalized, $targetYear) && str_contains($normalized, $month)) {
                return $sheetName;
            }
        }

        return count($sheetNames) === 1 ? $sheetNames[0] : null;
    }

    private function findPeriodSheet($spreadsheet, DateTimeImmutable $period): mixed
    {
        $targetMonth = (int) $period->format('n');
        $targetYear = $period->format('Y');

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $title = $this->normalizeHeader($worksheet->getTitle());

            if (! str_contains($title, $targetYear)) {
                continue;
            }

            $month = $this->monthName($targetMonth);
            if (str_contains($title, $this->normalizeHeader($month))) {
                return $worksheet;
            }
        }

        if ($spreadsheet->getSheetCount() === 1) {
            return $spreadsheet->getSheet(0);
        }

        return null;
    }

    private function findHeaderRow($worksheet): ?int
    {
        $limit = min(30, $worksheet->getHighestDataRow());

        for ($row = 1; $row <= $limit; $row++) {
            $first = $this->normalizeHeader((string) $worksheet->getCell([1, $row])->getFormattedValue());
            $fifth = $this->normalizeHeader((string) $worksheet->getCell([5, $row])->getFormattedValue());
            $last = $this->normalizeHeader((string) $worksheet->getCell([39, $row])->getFormattedValue());

            if ($first === 'nit'
                && str_contains($fifth, 'tipo documento')
                && (str_contains($last, 'observ') || $last === 'observacion')) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<int, mixed> $values
     * @param array<int, array<string, mixed>> $errors
     * @param array<int, array<string, mixed>> $warnings
     * @return array<int, string>|null
     */
    private function normalizeRecord(array $values, int $row, array &$errors, array &$warnings): ?array
    {
        $nit = $this->digitsOnly($values[0] ?? null);

        if ($nit === '') {
            return null;
        }

        // La plantilla de JULIO 2026 trae 38 datos efectivos: falta la modalidad
        // y TIPO_DOCUMENTO quedó desplazado a la columna 4. En ese caso se inserta
        // 1 (Cápita), coherente con los demás meses del mismo archivo.
        $possibleDocumentType = strtoupper($this->cleanText($values[3] ?? null));
        if (in_array($possibleDocumentType, self::DOCUMENT_TYPES, true)) {
            $values = array_values(array_merge(
                array_slice($values, 0, 3),
                [1],
                array_slice($values, 3, 35)
            ));
            $values = array_pad($values, self::FIELD_COUNT, '');
            $warnings[] = $this->warning(
                $row,
                'MODALIDAD_CONTRATO',
                '',
                'La fila venía desplazada sin modalidad. Se asignó automáticamente 1 (Cápita).'
            );
        }

        $municipioOriginal = $this->cleanText($values[1] ?? null);
        $departamentoOriginal = $this->cleanText($values[2] ?? null);
        $municipio = $this->normalizeMunicipality($municipioOriginal);
        $departamento = $this->normalizeDepartment($departamentoOriginal);

        if ($municipio !== $this->digitsOnly($municipioOriginal) && $municipioOriginal !== $municipio) {
            $warnings[] = $this->warning($row, 'MUNICIPIO', $municipioOriginal, 'Se normalizó a código DANE '.$municipio.'.');
        }
        if ($departamento !== $this->digitsOnly($departamentoOriginal) && $departamentoOriginal !== $departamento) {
            $warnings[] = $this->warning($row, 'DEPARTAMENTO', $departamentoOriginal, 'Se normalizó a código DANE '.$departamento.'.');
        }

        $record = [
            $nit,
            $municipio,
            $departamento,
            $this->numericText($values[3] ?? null),
            strtoupper($this->cleanText($values[4] ?? null)),
            $this->cleanIdentifier($values[5] ?? null),
            $this->cleanText($values[6] ?? null),
            $this->numericText($values[7] ?? null),
            $this->digitsOnly($values[8] ?? null),
            strtoupper($this->cleanText($values[9] ?? null)),
            $this->numericText($values[10] ?? null),
            $this->cleanText($values[11] ?? null),
            $this->numericText($values[12] ?? null),
            $this->cleanTechnology($values[13] ?? null),
            $this->cleanText($values[14] ?? null),
            $this->cleanText($values[15] ?? null),
            $this->cleanText($values[16] ?? null),
            $this->cleanText($values[17] ?? null),
            strtoupper($this->cleanText($values[18] ?? null)),
            $this->numericText($values[19] ?? null),
            $this->numericText($values[20] ?? null),
            $this->dateTimeText($values[21] ?? null),
            $this->dateTimeText($values[22] ?? null),
            $this->numericText($values[23] ?? null),
            $this->numericText($values[24] ?? null),
            $this->pendingDateText($values[25] ?? null),
            $this->numericText($values[26] ?? null),
            $this->numericText($values[27] ?? null),
            $this->numericText($values[28] ?? null),
            $this->cleanText($values[29] ?? null),
            $this->dateText($values[30] ?? null),
            $this->numericText($values[31] ?? null),
            $this->cleanText($values[32] ?? null),
            $this->cleanText($values[33] ?? null),
            $this->numericText($values[34] ?? null),
            $this->numericText($values[35] ?? null),
            $this->cleanText($values[36] ?? null),
            $this->cleanText($values[37] ?? null),
            $this->cleanText($values[38] ?? null),
        ];

        // Reglas confirmadas por la plataforma de Familiar Colombia para CIDSMA.
        // 1) El contrato válido de CIDSMA es 2026-170-S.
        if ($record[36] !== '2026-170-S') {
            $originalContract = $record[36];
            $record[36] = '2026-170-S';
            $warnings[] = $this->warning(
                $row,
                'NUMERO_CONTRATO',
                $originalContract,
                'Se reemplazó automáticamente por el contrato válido de CIDSMA: 2026-170-S.'
            );
        }

        // 2) Si no existe cantidad pendiente, Familiar exige FECHA_ENTREGA_PENDIENTE vacía.
        $pendingQuantity = $this->toFloat($record[24]);
        if ($pendingQuantity !== null && abs($pendingQuantity) < 0.000001) {
            if ($record[25] !== '') {
                $warnings[] = $this->warning(
                    $row,
                    'FECHA_ENTREGA_PENDIENTE',
                    $record[25],
                    'CANTIDAD_PENDIENTE es 0; se dejó FECHA_ENTREGA_PENDIENTE vacía automáticamente.'
                );
            }
            $record[25] = '';
            $record[26] = '0';
        }

        // 3) COSTO_TOTAL debe corresponder a cantidad entregada × costo unitario.
        // Cantidad entregada = cantidad prescrita - cantidad pendiente.
        $prescribedQuantity = $this->toFloat($record[20]);
        $unitCost = $this->toFloat($record[27]);
        if ($prescribedQuantity !== null && $pendingQuantity !== null && $unitCost !== null) {
            $deliveredQuantity = max(0.0, $prescribedQuantity - $pendingQuantity);
            $expectedTotal = $deliveredQuantity * $unitCost;
            $expectedTotalText = $this->formatNumber($expectedTotal);

            if ($record[28] !== $expectedTotalText) {
                $originalTotal = $record[28];
                $record[28] = $expectedTotalText;
                $warnings[] = $this->warning(
                    $row,
                    'COSTO_TOTAL',
                    $originalTotal,
                    'Se recalculó automáticamente como cantidad entregada × costo unitario: '.$expectedTotalText.'.'
                );
            }
        }

        $this->validateRecord($record, $row, $errors, $warnings);

        return $record;
    }

    /**
     * @param array<int, string> $record
     * @param array<int, array<string, mixed>> $errors
     * @param array<int, array<string, mixed>> $warnings
     */
    private function validateRecord(array $record, int $row, array &$errors, array &$warnings): void
    {
        $this->requiredNumeric($record, 0, $row, $errors);

        if (! preg_match('/^\d{5}$/', $record[1])) {
            $errors[] = $this->error($row, 1, $record[1], 'Debe contener el código DANE de municipio de 5 dígitos.');
        }
        if (! preg_match('/^\d{2}$/', $record[2])) {
            $errors[] = $this->error($row, 2, $record[2], 'Debe contener el código DANE de departamento de 2 dígitos.');
        }
        if (! in_array($record[3], ['1', '2', '3', '4', '5'], true)) {
            $errors[] = $this->error($row, 3, $record[3], 'La modalidad debe ser 1, 2, 3, 4 o 5.');
        }
        if ($record[4] === '' || strlen($record[4]) !== 2) {
            $errors[] = $this->error($row, 4, $record[4], 'El tipo de documento debe tener 2 caracteres.');
        }
        foreach ([5, 6, 7, 9, 11, 13] as $index) {
            if ($record[$index] === '') {
                $errors[] = $this->error($row, $index, '', 'El campo obligatorio está vacío.');
            }
        }
        if (! in_array($record[10], ['1', '2'], true)) {
            $errors[] = $this->error($row, 10, $record[10], 'El régimen debe ser 1 (Contributivo) o 2 (Subsidiado).');
        }
        if (! in_array($record[12], ['1', '2'], true)) {
            $errors[] = $this->error($row, 12, $record[12], 'El financiamiento debe ser 1 (PBSUPC) o 2 (NOPBSUPC).');
        }
        if (! in_array($record[34], ['1', '2', '3'], true)) {
            $errors[] = $this->error($row, 34, $record[34], 'Debe ser 1 (PQRD), 2 (Tutela) o 3 (No aplica).');
        }
        if (! in_array($record[35], ['1', '2', '3'], true)) {
            $errors[] = $this->error($row, 35, $record[35], 'Debe ser 1 (Hospitalaria), 2 (Intramural) o 3 (Extramural).');
        }

        foreach ([19, 20, 23, 24, 26, 27, 28, 31] as $index) {
            if ($record[$index] !== '' && ! is_numeric(str_replace(',', '.', $record[$index]))) {
                $errors[] = $this->error($row, $index, $record[$index], 'Debe contener un valor numérico.');
            }
        }

        if ($record[8] === '') {
            $warnings[] = $this->warning($row, 'TELEFONO_AFILIADO', '', 'El teléfono está vacío; se conserva para validar contra la plataforma.');
        }
        foreach ([36, 37, 38] as $index) {
            if ($record[$index] === '') {
                $warnings[] = $this->warning(
                    $row,
                    self::FIELD_NAMES[$index],
                    '',
                    'El portal actual incluye este campo adicional, pero el instructivo entregado no define su obligatoriedad.'
                );
            }
        }
    }

    private function requiredNumeric(array $record, int $index, int $row, array &$errors): void
    {
        if ($record[$index] === '' || ! ctype_digit($record[$index])) {
            $errors[] = $this->error($row, $index, $record[$index], 'Debe contener un valor numérico obligatorio.');
        }
    }

    /** @return array<string, mixed> */
    private function error(int $row, int $fieldIndex, string $value, string $message): array
    {
        return [
            'source_row' => $row,
            'field_number' => $fieldIndex + 1,
            'field_name' => self::FIELD_NAMES[$fieldIndex],
            'value' => $value,
            'message' => $message,
            'severity' => 'error',
        ];
    }

    /** @return array<string, mixed> */
    private function warning(int $row, string $field, string $value, string $message): array
    {
        return [
            'source_row' => $row,
            'field_name' => $field,
            'value' => $value,
            'message' => $message,
            'severity' => 'warning',
        ];
    }

    private function readCell($worksheet, int $column, int $row): mixed
    {
        $cell = $worksheet->getCell([$column, $row]);
        $value = $cell->getValue();

        if (is_string($value) && str_starts_with($value, '=')) {
            try {
                return $cell->getCalculatedValue();
            } catch (Throwable) {
                return $cell->getFormattedValue();
            }
        }

        return $value;
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param array<int, mixed> $values */
    private function looksLikeSecondaryHeader(array $values): bool
    {
        $first = $this->normalizeHeader((string) ($values[0] ?? ''));
        $second = $this->normalizeHeader((string) ($values[1] ?? ''));
        $fourth = $this->normalizeHeader((string) ($values[3] ?? ''));

        return $first === 'nit'
            || $first === 'municipio'
            || $second === 'municipio'
            || str_contains($fourth, 'tipo documento');
    }

    private function normalizeMunicipality(mixed $value): string
    {
        $text = $this->cleanText($value);
        $digits = $this->digitsOnly($text);
        if (strlen($digits) === 5) {
            return $digits;
        }

        return match ($this->normalizeHeader($text)) {
            'maicao' => '44430',
            default => $digits,
        };
    }

    private function normalizeDepartment(mixed $value): string
    {
        $text = $this->cleanText($value);
        $digits = $this->digitsOnly($text);
        if (strlen($digits) === 2) {
            return $digits;
        }

        return match ($this->normalizeHeader($text)) {
            'la guajira', 'guajira' => '44',
            default => $digits,
        };
    }

    private function pendingDateText(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '0' || $text === '0.0') {
            return '';
        }
        return $this->dateTimeText($value);
    }

    private function dateTimeText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }

        if (is_numeric($value) && (float) $value > 1000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y H:i');
            } catch (Throwable) {
                // Continue with textual parsing.
            }
        }

        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '0') {
            return $text;
        }
        $text = preg_replace_callback(
            '/\s+(a\.?\s*m\.?|p\.?\s*m\.?)$/iu',
            static fn (array $matches): string => str_starts_with(strtolower($matches[1]), 'p') ? ' PM' : ' AM',
            $text
        ) ?? $text;

        foreach (['d-m-Y h:i A', 'd/m/Y h:i A', 'd-m-Y H:i', 'd/m/Y H:i', 'd-m-Y G:i', 'd/m/Y G:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('d/m/Y H:i');
            }
        }

        try {
            return (new DateTimeImmutable($text))->format('d/m/Y H:i');
        } catch (Throwable) {
            return $this->cleanText($text);
        }
    }

    private function dateText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if (is_numeric($value) && (float) $value > 1000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y');
            } catch (Throwable) {
                // Continue.
            }
        }
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '0') {
            return $text;
        }
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, substr($text, 0, 10));
            if ($date instanceof DateTimeImmutable) {
                return $date->format('d/m/Y');
            }
        }
        try {
            return (new DateTimeImmutable($text))->format('d/m/Y');
        } catch (Throwable) {
            return $this->cleanText($text);
        }
    }

    private function cleanTechnology(mixed $value): string
    {
        $text = $this->cleanText($value);
        return trim(str_replace([',', ';'], ' ', $text));
    }

    private function cleanText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        $text = trim((string) $value);
        $text = str_replace(["\r", "\n", "\t", ';'], [' ', ' ', ' ', ','], $text);
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function cleanIdentifier(mixed $value): string
    {
        $text = $this->cleanText($value);
        return preg_replace('/[^A-Za-z0-9-]/', '', $text) ?? $text;
    }

    private function digitsOnly(mixed $value): string
    {
        $text = $this->numericText($value);
        return preg_replace('/\D+/', '', $text) ?? '';
    }

    private function numericText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (floor($value) === $value) {
                return (string) ((int) $value);
            }
            return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }
        $text = trim((string) $value);
        if (preg_match('/^-?\d+\.0+$/', $text)) {
            return strstr($text, '.', true) ?: '0';
        }
        return $text;
    }

    private function toFloat(string $value): ?float
    {
        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.000001) {
            return (string) ((int) round($value));
        }

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated)) {
            $value = $transliterated;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ][$month];
    }
}
