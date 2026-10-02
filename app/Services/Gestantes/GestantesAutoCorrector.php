<?php

namespace App\Services\Gestantes;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class GestantesAutoCorrector
{
    public function correct(
        string $sourcePath,
        ?string $destinationPath = null
    ): array {
        if (! is_file($sourcePath)) {
            throw new RuntimeException(
                'No se encontró el archivo Excel que se desea corregir.'
            );
        }

        $spreadsheet = $this->loadSpreadsheet($sourcePath);
        $corrections = [];

        try {
            $configuredSheets = config('gestantes.sheets', []);

            if (! is_array($configuredSheets) || $configuredSheets === []) {
                throw new RuntimeException(
                    'No existen hojas configuradas en config/gestantes.php.'
                );
            }

            foreach ($configuredSheets as $sheetKey => $definition) {
                $sheetName = $definition['name'] ?? null;
                $columns = $definition['columns'] ?? [];

                if (! is_string($sheetName) || trim($sheetName) === '') {
                    continue;
                }

                $sheet = $spreadsheet->getSheetByName($sheetName);

                if (! $sheet instanceof Worksheet) {
                    throw new RuntimeException(
                        "No se encontró la hoja obligatoria: {$sheetName}."
                    );
                }

                $expectedColumns = is_array($columns)
                    ? count($columns)
                    : 0;

                $this->normalizeSheetValues(
                    sheet: $sheet,
                    expectedColumns: $expectedColumns,
                    sheetKey: (string) $sheetKey,
                    columns: is_array($columns) ? $columns : [],
                    recordType: (int) ($definition['record_type'] ?? 0),
                    corrections: $corrections
                );
            }

            /*
             * Consecutivo único y continuo entre hojas 2, 3, 4 y 5.
             */
            $totalDetailRecords = $this->fixDetailConsecutives(
                spreadsheet: $spreadsheet,
                configuredSheets: $configuredSheets,
                corrections: $corrections
            );

            /*
             * Total de registros reales en Control.
             */
            $this->fixControlTotal(
                spreadsheet: $spreadsheet,
                configuredSheets: $configuredSheets,
                totalDetailRecords: $totalDetailRecords,
                corrections: $corrections
            );

            $destinationPath ??= $this->buildDestinationPath($sourcePath);

            $directory = dirname($destinationPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            IOFactory::createWriter($spreadsheet, 'Xlsx')
                ->save($destinationPath);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible corregir el archivo Excel: '.
                $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return [
            'path' => $destinationPath,
            'corrections' => $corrections,
            'total_corrections' => count($corrections),
        ];
    }

    private function loadSpreadsheet(string $path): Spreadsheet
    {
        try {
            return IOFactory::load($path);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible abrir el archivo Excel: '.
                $exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * Correcciones seguras:
     * - Tipo de registro.
     * - Fechas a AAAA-MM-DD.
     * - Numéricos tipo N: elimina .0 / ,0, pero NO inventa ni trunca decimales reales.
     * - Decimales tipo D: usa punto.
     * - A/T: limpia espacios, saltos de línea y pipe; convierte a mayúsculas/sin tildes.
     * - Mapeos SI/NO -> 1/2 solamente cuando el catálogo del campo lo permite.
     */
    private function normalizeSheetValues(
        Worksheet $sheet,
        int $expectedColumns,
        string $sheetKey,
        array $columns,
        int $recordType,
        array &$corrections
    ): void {
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $this->readRowValues(
                sheet: $sheet,
                row: $row,
                expectedColumns: $expectedColumns
            );

            if ($this->isEmptyRow($values)) {
                continue;
            }

            /*
             * No tratamos filas placeholder de las hojas de detalle.
             */
            if (
                $this->isTemplatePlaceholderRow(
                    sheetKey: $sheetKey,
                    sheetName: $sheet->getTitle(),
                    values: $values
                )
            ) {
                continue;
            }

            /*
             * Columna 1: tipo de registro fijo por hoja.
             */
            if ($recordType > 0) {
                $typeCell = $sheet->getCell([1, $row]);
                $oldType = $typeCell->getValue();

                if ((string) $oldType !== (string) $recordType) {
                    $typeCell->setValue((string) $recordType);

                    $this->addCorrection(
                        corrections: $corrections,
                        sheet: $sheet->getTitle(),
                        row: $row,
                        column: 1,
                        field: 'Tipo de registro',
                        oldValue: $oldType,
                        newValue: $recordType,
                        detail: 'Se asignó el tipo de registro correspondiente a la hoja.'
                    );
                }
            }

            for ($column = 1; $column <= $expectedColumns; $column++) {
                /*
                 * Tipo de registro se corrigió arriba.
                 * Consecutivo se corrige globalmente más adelante.
                 */
                if ($column === 1 || ($sheetKey !== 'control' && $column === 2)) {
                    continue;
                }

                $definition = $columns[$column - 1] ?? [];
                $cell = $sheet->getCell([$column, $row]);
                $originalValue = $cell->getValue();

                if (
                    is_string($originalValue)
                    && str_starts_with(trim($originalValue), '=')
                ) {
                    continue;
                }

                $normalizedValue = $this->normalizeCellValue(
                    cell: $cell,
                    definition: $definition
                );

                if (! $this->valuesAreDifferent(
                    $originalValue,
                    $normalizedValue
                )) {
                    continue;
                }

                $cell->setValue($normalizedValue);

                $this->addCorrection(
                    corrections: $corrections,
                    sheet: $sheet->getTitle(),
                    row: $row,
                    column: $column,
                    field: (string) ($definition['name'] ?? 'Normalización de valor'),
                    oldValue: $originalValue,
                    newValue: $normalizedValue,
                    detail: 'Se normalizó automáticamente usando una regla segura.'
                );
            }
        }
    }

    private function normalizeCellValue(
        Cell $cell,
        array $definition
    ): mixed {
        $value = $cell->getValue();

        if ($value === null) {
            return null;
        }

        $type = strtoupper((string) ($definition['type'] ?? 'A'));
        $allowed = $definition['allowed'] ?? null;

        /*
         * Fecha reconocida por Excel.
         */
        if ($type === 'F' && Date::isDateTime($cell) && is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (Throwable) {
                return $value;
            }
        }

        if (! is_string($value)) {
            if ($type === 'N' && is_numeric($value)) {
                $numeric = (float) $value;

                if (floor($numeric) === $numeric) {
                    return (string) ((int) $numeric);
                }
            }

            if ($type === 'D' && is_numeric($value)) {
                return $this->formatDecimal((float) $value);
            }

            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if ($type === 'F') {
            $normalizedDate = $this->normalizeDateString($trimmed);

            return $normalizedDate ?? $trimmed;
        }

        if ($type === 'N') {
            return $this->normalizeIntegerString($trimmed);
        }

        if ($type === 'D') {
            return $this->normalizeDecimalString($trimmed);
        }

        /*
         * Texto/alfanumérico.
         */
        $clean = preg_replace('/[\r\n\x{2028}\x{2029}]+/u', ' ', $trimmed);
        $clean = preg_replace('/\s+/u', ' ', $clean);
        $clean = str_replace('|', ' ', $clean);
        $clean = $this->stripAccents(mb_strtoupper($clean));

        /*
         * Mapeo seguro para campos 1/2.
         */
        if (is_array($allowed) && $allowed === ['1', '2']) {
            if (in_array($clean, ['SI', 'S'], true)) {
                return '1';
            }

            if ($clean === 'NO') {
                return '2';
            }
        }

        return $clean;
    }

    private function normalizeIntegerString(string $value): string
    {
        $clean = trim($value);

        /*
         * 10,  / 10.  -> 10
         */
        $clean = preg_replace('/[,.]+$/', '', $clean);

        /*
         * 10,0 / 10.0 / 10,00 -> 10
         */
        if (preg_match('/^([+-]?\d+)[,.]0+$/', $clean, $matches)) {
            return ltrim($matches[1], '+');
        }

        /*
         * No truncamos 10,3 -> 10 porque para un campo N
         * eso sería inventar/cambiar el dato clínico.
         * Solo cambiamos coma por punto si sigue siendo decimal
         * para que el validador lo marque claramente.
         */
        if (
            str_contains($clean, ',')
            && ! str_contains($clean, '.')
        ) {
            $clean = str_replace(',', '.', $clean);
        }

        return $clean;
    }

    private function normalizeDecimalString(string $value): string
    {
        $clean = trim($value);
        $clean = preg_replace('/[,.]+$/', '', $clean);

        if (
            str_contains($clean, ',')
            && ! str_contains($clean, '.')
        ) {
            $clean = str_replace(',', '.', $clean);
        }

        if (! is_numeric($clean)) {
            return $clean;
        }

        return $this->formatDecimal((float) $clean);
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(
            rtrim(
                number_format($value, 2, '.', ''),
                '0'
            ),
            '.'
        );
    }

    private function normalizeDateString(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        /*
         * Regla SIGIRES: toda fecha debe salir AAAA-MM-DD.
         * Acepta entradas con día/mes de uno o dos dígitos:
         * 21/9/2026 -> 2026-09-21
         * 1/8/2026  -> 2026-08-01
         */
        if (
            preg_match(
                '/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/',
                $value,
                $matches
            )
        ) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }

            return null;
        }

        if (
            preg_match(
                '/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/',
                $value,
                $matches
            )
        ) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }

            return null;
        }

        return null;
    }

    private function fixDetailConsecutives(
        Spreadsheet $spreadsheet,
        array $configuredSheets,
        array &$corrections
    ): int {
        $consecutive = 1;

        foreach ($configuredSheets as $sheetKey => $definition) {
            if ($this->isControlSheet((string) $sheetKey, $definition)) {
                continue;
            }

            $sheetName = $definition['name'] ?? null;
            $columns = $definition['columns'] ?? [];

            if (! is_string($sheetName)) {
                continue;
            }

            $sheet = $spreadsheet->getSheetByName($sheetName);

            if (! $sheet instanceof Worksheet) {
                continue;
            }

            $expectedColumns = is_array($columns)
                ? count($columns)
                : 0;

            $highestRow = $sheet->getHighestDataRow();

            for ($row = 2; $row <= $highestRow; $row++) {
                $values = $this->readRowValues(
                    sheet: $sheet,
                    row: $row,
                    expectedColumns: $expectedColumns
                );

                if (
                    $this->isEmptyRow($values)
                    || $this->isTemplatePlaceholderRow(
                        sheetKey: (string) $sheetKey,
                        sheetName: $sheetName,
                        values: $values
                    )
                ) {
                    continue;
                }

                $cell = $sheet->getCell([2, $row]);
                $oldValue = $cell->getValue();

                if ((string) $oldValue !== (string) $consecutive) {
                    $cell->setValue((string) $consecutive);

                    $this->addCorrection(
                        corrections: $corrections,
                        sheet: $sheetName,
                        row: $row,
                        column: 2,
                        field: 'Consecutivo de registro',
                        oldValue: $oldValue,
                        newValue: $consecutive,
                        detail: 'Se asignó el consecutivo global correcto.'
                    );
                }

                $consecutive++;
            }
        }

        return $consecutive - 1;
    }

    private function fixControlTotal(
        Spreadsheet $spreadsheet,
        array $configuredSheets,
        int $totalDetailRecords,
        array &$corrections
    ): void {
        $controlDefinition = null;

        foreach ($configuredSheets as $sheetKey => $definition) {
            if ($this->isControlSheet((string) $sheetKey, $definition)) {
                $controlDefinition = $definition;
                break;
            }
        }

        if (! is_array($controlDefinition)) {
            throw new RuntimeException(
                'No se encontró la configuración de la hoja Control.'
            );
        }

        $sheetName = $controlDefinition['name'] ?? null;

        if (! is_string($sheetName)) {
            throw new RuntimeException(
                'El nombre de la hoja Control no es válido.'
            );
        }

        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (! $sheet instanceof Worksheet) {
            throw new RuntimeException(
                "No se encontró la hoja de control: {$sheetName}."
            );
        }

        $cell = $sheet->getCell([7, 2]);
        $oldValue = $cell->getValue();

        if ((string) $oldValue === (string) $totalDetailRecords) {
            return;
        }

        $cell->setValue((string) $totalDetailRecords);

        $this->addCorrection(
            corrections: $corrections,
            sheet: $sheetName,
            row: 2,
            column: 7,
            field: 'Total de registros de detalle',
            oldValue: $oldValue,
            newValue: $totalDetailRecords,
            detail: 'Se actualizó el total de registros reales del archivo.'
        );
    }

    private function readRowValues(
        Worksheet $sheet,
        int $row,
        int $expectedColumns
    ): array {
        $values = [];

        for ($column = 1; $column <= $expectedColumns; $column++) {
            $values[] = $sheet->getCell([$column, $row])->getValue();
        }

        return $values;
    }

    private function isControlSheet(
        string $sheetKey,
        array $definition
    ): bool {
        $sheetName = (string) ($definition['name'] ?? '');

        $identifier = $this->normalizeIdentifier(
            $sheetKey.' '.$sheetName
        );

        return str_contains($identifier, 'control');
    }

    private function isTemplatePlaceholderRow(
        string $sheetKey,
        string $sheetName,
        array $values
    ): bool {
        $identifier = $this->normalizeIdentifier(
            $sheetKey.' '.$sheetName
        );

        if (str_contains($identifier, 'control')) {
            return false;
        }

        if (
            str_contains($identifier, 'id gestantes')
            || str_contains($identifier, 'identificacion')
            || str_contains($identifier, 'identificaciones')
        ) {
            return $this->areValuesEmpty([
                $values[6] ?? null,
                $values[7] ?? null,
            ]);
        }

        if (str_contains($identifier, 'atenciones')) {
            return $this->areValuesEmpty([
                $values[2] ?? null,
                $values[3] ?? null,
                $values[4] ?? null,
            ]);
        }

        if (str_contains($identifier, 'seguimientos')) {
            return $this->areValuesEmpty([
                $values[2] ?? null,
                $values[3] ?? null,
                $values[5] ?? null,
            ]);
        }

        if (str_contains($identifier, 'urgencias')) {
            return $this->areValuesEmpty([
                $values[2] ?? null,
                $values[3] ?? null,
                $values[4] ?? null,
            ]);
        }

        return false;
    }

    private function isEmptyRow(array $values): bool
    {
        return $this->areValuesEmpty($values);
    }

    private function areValuesEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (! $this->isEmptyValue($value)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    private function valuesAreDifferent(
        mixed $original,
        mixed $normalized
    ): bool {
        if ($original === null && $normalized === null) {
            return false;
        }

        return (string) $original !== (string) $normalized;
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    private function stripAccents(string $value): string
    {
        return strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
            'á' => 'A',
            'é' => 'E',
            'í' => 'I',
            'ó' => 'O',
            'ú' => 'U',
            'ü' => 'U',
            'ñ' => 'N',
        ]);
    }

    private function addCorrection(
        array &$corrections,
        string $sheet,
        int $row,
        int $column,
        string $field,
        mixed $oldValue,
        mixed $newValue,
        string $detail
    ): void {
        $corrections[] = [
            'sheet' => $sheet,
            'row' => $row,
            'column' => $column,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'detail' => $detail,
        ];
    }

    private function buildDestinationPath(string $sourcePath): string
    {
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $timestamp = now()->format('Ymd_His');

        return storage_path(
            "app/private/gestantes/corregidos/{$filename}_corregido_{$timestamp}.xlsx"
        );
    }
}
