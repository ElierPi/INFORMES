<?php

namespace App\Services\Resolucion1604\Dusakawi;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

final class Resolucion1604DusakawiExcelReader
{
    public const DETAIL_FIELD_COUNT = 25;

    /** @var array<int, string> */
    public const DETAIL_FIELDS = [
        'NIT', 'RAZON_SOCIAL', 'CODIGO_MUNICIPIO', 'CODIGO_DEPARTAMENTO',
        'TIPO_IDENTIFICACION', 'NUMERO_IDENTIFICACION', 'DIRECCION_RESIDENCIA',
        'TELEFONO_MOVIL', 'DIAGNOSTICO_CIE10', 'NUMERO_FORMULA', 'CUM',
        'FECHA_VENCIMIENTO', 'FRECUENCIA_MEDICAMENTO', 'DURACION_TRATAMIENTO',
        'CANTIDAD_PRESCRITA', 'CANTIDAD_ENTREGADA_INMEDIATAMENTE',
        'CANTIDAD_PENDIENTE', 'FECHA_SOLICITUD', 'FECHA_PRIMERA_ENTREGA',
        'FECHA_ENTREGA_PENDIENTE', 'AUTORIZACION_DOMICILIARIA',
        'DIRECCION_DOMICILIARIA', 'MEDICAMENTO_ENTREGADO',
        'CANTIDAD_ENTREGADA_DOMICILIARIA', 'MODALIDAD_ENTREGA',
    ];

    private const NIT = '900144397';
    private const RAZON_SOCIAL = 'Wayuu Anashii';
    private const MUNICIPIO = '44430';
    private const DEPARTAMENTO = '44';

    /** @return array<string, mixed> */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel de origen.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $headerRow = $this->findHeaderRow($sheet);

            if ($headerRow === null) {
                throw new RuntimeException('No se encontró la fila de encabezados del archivo DSK.');
            }

            $headers = $this->buildHeaderMap($sheet, $headerRow);
            $required = [
                'identificacion', 'de identificacion', 'direccion', 'telefono',
                'n formula', 'codigo com', 'vencimiento', 'frecuencia',
                'duracion de tt', 'cantidad prescrita', 'cantidad entregada',
                'diagnostico', 'fecha de autorizacion de entrega', 'fecha de entrega y hora',
            ];

            foreach ($required as $key) {
                if (! isset($headers[$key])) {
                    throw new RuntimeException('Falta la columna obligatoria del Excel: '.$key.'.');
                }
            }

            $records = [];
            $warnings = [];
            $errors = [];
            $highestRow = $sheet->getHighestDataRow();

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $document = $this->cleanIdentifier($this->cell($sheet, $headers['de identificacion'], $row));
                if ($document === '') {
                    continue;
                }

                $documentType = strtoupper($this->cleanText($this->cell($sheet, $headers['identificacion'], $row)));
                $address = $this->cleanText($this->cell($sheet, $headers['direccion'], $row));
                $phone = $this->digitsOnly($this->cell($sheet, $headers['telefono'], $row));
                $diagnosis = $this->normalizeDiagnosis($this->cell($sheet, $headers['diagnostico'], $row));
                $formula = $this->cleanText($this->cell($sheet, $headers['n formula'], $row));
                $cum = $this->cleanText($this->cell($sheet, $headers['codigo com'], $row));

                $expiration = $this->dateText(
                    $this->cellRaw($sheet, $headers['vencimiento'], $row),
                    $row,
                    'FECHA_VENCIMIENTO',
                    $warnings
                );

                // IMPORTANTE: se conserva la frecuencia como viene (6, 8, 12, 24, etc.),
                // igual al TXT real de junio. No se convierte a tomas/día.
                $frequency = strtoupper($this->cleanText($this->cell($sheet, $headers['frecuencia'], $row)));
                $duration = strtoupper($this->cleanText($this->cell($sheet, $headers['duracion de tt'], $row)));
                if (ctype_digit($duration) && (int) $duration > 30) {
                    $duration = '30';
                    $warnings[] = $this->warning($row, 'DURACION_TRATAMIENTO', '>30', 'DUSAKAWI solo admite duración de 1 a 30 días; se normalizó a 30.');
                }
                $prescribed = $this->integerText($this->cellRaw($sheet, $headers['cantidad prescrita'], $row));
                $delivered = $this->integerText($this->cellRaw($sheet, $headers['cantidad entregada'], $row));

                $requestDate = $this->dateText(
                    $this->cellRaw($sheet, $headers['fecha de autorizacion de entrega'], $row),
                    $row,
                    'FECHA_SOLICITUD',
                    $warnings
                );

                $deliveryDate = $this->dateText(
                    $this->cellRaw($sheet, $headers['fecha de entrega y hora'], $row),
                    $row,
                    'FECHA_PRIMERA_ENTREGA',
                    $warnings,
                    false
                );

                // JULIO trae varios 30/07/206. Si la entrega no se puede interpretar,
                // usamos la fecha de solicitud (en junio ambas fechas coinciden).
                if ($deliveryDate === '' && $requestDate !== '') {
                    $deliveryDate = $requestDate;
                    $warnings[] = $this->warning(
                        $row,
                        'FECHA_PRIMERA_ENTREGA',
                        'Fecha inválida en el origen',
                        'Se utilizó FECHA_SOLICITUD como fecha de primera entrega.'
                    );
                }

                $pending = '';
                if ($prescribed !== '' && $delivered !== '') {
                    $pending = (string) max(0, ((int) $prescribed) - ((int) $delivered));
                }

                $record = [
                    self::NIT,
                    self::RAZON_SOCIAL,
                    self::MUNICIPIO,
                    self::DEPARTAMENTO,
                    $documentType,
                    $document,
                    $address,
                    $phone,
                    $diagnosis,
                    $formula,
                    $cum,
                    $expiration,
                    $frequency,
                    $duration,
                    $prescribed,
                    $delivered,
                    $pending,
                    $requestDate,
                    $deliveryDate,
                    $deliveryDate, // En junio se repite incluso con pendiente = 0.
                    '0',
                    '0',
                    $delivered !== '' && (int) $delivered > 0 ? '1' : '0',
                    '0',
                    '0', // Se replica exactamente el patrón real del TXT de junio.
                ];

                $this->validateRecord($record, $row, $errors, $warnings);

                $records[] = [
                    'source_row' => $row,
                    'document' => $document,
                    'values' => $record,
                ];
            }

            if ($records === []) {
                throw new RuntimeException('El Excel no contiene registros de medicamentos para procesar.');
            }

            return [
                'sheet_name' => $sheet->getTitle(),
                'header_row' => $headerRow,
                'records' => $records,
                'warnings' => $warnings,
                'errors' => $errors,
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('No fue posible leer el Excel DSK: '.$exception->getMessage(), previous: $exception);
        }
    }

    private function findHeaderRow($sheet): ?int
    {
        $limit = min(25, $sheet->getHighestDataRow());
        for ($row = 1; $row <= $limit; $row++) {
            $first = $this->normalizeHeader((string) $sheet->getCell([1, $row])->getFormattedValue());
            $second = $this->normalizeHeader((string) $sheet->getCell([2, $row])->getFormattedValue());
            $third = $this->normalizeHeader((string) $sheet->getCell([3, $row])->getFormattedValue());

            if (str_contains($first, 'nombres') && $second === 'identificacion' && str_contains($third, 'de identificacion')) {
                return $row;
            }
        }
        return null;
    }

    /** @return array<string, int> */
    private function buildHeaderMap($sheet, int $headerRow): array
    {
        $map = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $highestColumn; $column++) {
            $header = $this->normalizeHeader((string) $sheet->getCell([$column, $headerRow])->getFormattedValue());
            if ($header !== '') {
                $map[$header] = $column;
            }
        }
        return $map;
    }

    /**
     * @param array<int, string> $record
     * @param array<int, array<string, mixed>> $errors
     * @param array<int, array<string, mixed>> $warnings
     */
    private function validateRecord(array $record, int $row, array &$errors, array &$warnings): void
    {
        // Solo bloqueamos campos indispensables para construir una línea coherente.
        foreach ([4, 5, 6, 8, 14, 15, 17, 18, 19] as $index) {
            if (($record[$index] ?? '') === '') {
                $errors[] = $this->error($row, $index, '', 'Campo estructural obligatorio vacío.');
            }
        }

        if (strlen($record[6]) < 10) {
            $warnings[] = $this->warning($row, 'DIRECCION_RESIDENCIA', $record[6], 'DUSAKAWI indica que la dirección debe tener mínimo 10 caracteres.');
        }
        if (strlen($record[7]) < 10) {
            $warnings[] = $this->warning($row, 'TELEFONO_MOVIL', $record[7], 'DUSAKAWI indica que el teléfono debe tener mínimo 10 caracteres.');
        }
        if ($record[9] === '') {
            $warnings[] = $this->warning($row, 'NUMERO_FORMULA', '', 'El número de fórmula está vacío; Aryuwi puede rechazarlo.');
        }
        if ($record[10] === '') {
            $warnings[] = $this->warning($row, 'CUM', '', 'El CUM está vacío; Aryuwi puede rechazarlo.');
        }
        if (! ctype_digit($record[12])) {
            $warnings[] = $this->warning($row, 'FRECUENCIA_MEDICAMENTO', $record[12], 'La frecuencia no es numérica. Se conserva para el primer cargue.');
        }
        if (! ctype_digit($record[13])) {
            $warnings[] = $this->warning($row, 'DURACION_TRATAMIENTO', $record[13], 'La duración no es numérica. Se conserva para el primer cargue.');
        }

        if ($record[14] !== '' && $record[15] !== '' && $record[16] !== '') {
            if (((int) $record[15] + (int) $record[16]) !== (int) $record[14]) {
                $errors[] = $this->error($row, 16, $record[16], 'Cantidad entregada + pendiente debe ser igual a cantidad prescrita.');
            }
        }

        if ($record[17] !== '' && $record[18] !== '' && $record[17] > $record[18]) {
            $warnings[] = $this->warning($row, 'FECHAS_ENTREGA', $record[18], 'La primera entrega es anterior a la fecha de solicitud.');
        }
    }

    private function cell($sheet, int $column, int $row): mixed
    {
        return $sheet->getCell([$column, $row])->getFormattedValue();
    }

    private function cellRaw($sheet, int $column, int $row): mixed
    {
        return $sheet->getCell([$column, $row])->getValue();
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::of($value)->ascii()->lower()->toString();
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function cleanText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $text = (string) $value;
        $text = str_replace(["\r", "\n", '|'], ' ', $text);
        $text = str_replace("\u{00A0}", ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function cleanIdentifier(mixed $value): string
    {
        $text = $this->cleanText($value);
        if (preg_match('/^([0-9]+)\.0+$/', $text, $match)) {
            return $match[1];
        }
        return $text;
    }

    private function digitsOnly(mixed $value): string
    {
        return preg_replace('/\D+/', '', $this->cleanText($value)) ?? '';
    }

    private function integerText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value)) {
            return (string) ((int) round((float) $value));
        }
        $text = str_replace(',', '.', $this->cleanText($value));
        return is_numeric($text) ? (string) ((int) round((float) $text)) : '';
    }

    private function normalizeDiagnosis(mixed $value): string
    {
        $text = strtoupper($this->cleanText($value));
        $text = preg_replace('/\s*\/\s*/', '/', $text) ?? $text;
        $first = trim(explode('/', $text)[0] ?? '');
        $first = preg_replace('/\s+/', '', $first) ?? $first;

        // Aryuwi rechazó diagnósticos múltiples. La estructura usada por DUSAKAWI
        // maneja códigos de 4 caracteres (I10X, Z003, J00X...).
        if (preg_match('/^([A-Z][0-9]{2}[A-Z0-9])/', $first, $match)) {
            return $match[1];
        }

        return $first;
    }

    /** @param array<int, array<string, mixed>> $warnings */
    private function dateText(
        mixed $value,
        int $row,
        string $field,
        array &$warnings,
        bool $specialExpiration = true,
    ): string {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return '';
            }
        }

        $text = $this->cleanText($value);
        if ($text === '') {
            return '';
        }

        // Error real de julio: algunas fechas llegaron como 30/07/206.
        // Se corrige específicamente a 2026 para evitar generar 0206-07-30.
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-]206$/', $text, $match)) {
            $normalized = sprintf('2026-%02d-%02d', (int) $match[2], (int) $match[1]);
            $warnings[] = $this->warning($row, $field, $text, 'Año 206 corregido automáticamente a 2026: '.$normalized.'.');
            return $normalized;
        }

        if ($specialExpiration && preg_match('/^(?:agt|ago|agosto)[\/-]?(\d{2})$/i', $text, $match)) {
            $year = 2000 + (int) $match[1];
            $normalized = sprintf('%04d-08-01', $year);
            $warnings[] = $this->warning($row, $field, $text, 'Se normalizó automáticamente a '.$normalized.'.');
            return $normalized;
        }

        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d/m/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $text);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $valid = $date instanceof DateTimeImmutable
                && ($dateErrors === false || (($dateErrors['warning_count'] ?? 0) === 0 && ($dateErrors['error_count'] ?? 0) === 0));
            if ($valid) {
                return $date->format('Y-m-d');
            }
        }

        $warnings[] = $this->warning($row, $field, $text, 'No fue posible interpretar esta fecha.');
        return '';
    }

    /** @return array<string, mixed> */
    private function error(int $row, int $index, string $value, string $message): array
    {
        return [
            'source_row' => $row,
            'field' => self::DETAIL_FIELDS[$index] ?? ('CAMPO_'.($index + 1)),
            'value' => $value,
            'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function warning(int $row, string $field, string $value, string $message): array
    {
        return compact('row', 'field', 'value', 'message');
    }
}
