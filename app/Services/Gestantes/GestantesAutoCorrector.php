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
    /**
     * Corrige el archivo y devuelve la ruta del nuevo Excel
     * junto con el detalle de las correcciones realizadas.
     */
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

            /*
             * Primero normalizamos texto y fechas.
             */
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
                    : $sheet->getHighestDataColumn();

                $this->normalizeSheetValues(
                    sheet: $sheet,
                    expectedColumns: (int) $expectedColumns,
                    sheetKey: (string) $sheetKey,
                    corrections: $corrections
                );
            }

            /*
             * Después asignamos consecutivos globales.
             */
            $totalDetailRecords = $this->fixDetailConsecutives(
                spreadsheet: $spreadsheet,
                configuredSheets: $configuredSheets,
                corrections: $corrections
            );

            /*
             * Finalmente actualizamos el total de registros de detalle.
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
     * Normaliza textos, espacios y fechas.
     */
    private function normalizeSheetValues(
        Worksheet $sheet,
        int $expectedColumns,
        string $sheetKey,
        array &$corrections
    ): void {
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $expectedColumns; $column++) {
                $cell = $sheet->getCell([$column, $row]);
                $originalValue = $cell->getValue();

                /*
                 * No modificamos fórmulas.
                 */
                if (
                    is_string($originalValue)
                    && str_starts_with(trim($originalValue), '=')
                ) {
                    continue;
                }

                $normalizedValue = $this->normalizeCellValue($cell);

                if (! $this->valuesAreDifferent(
                    $originalValue,
                    $normalizedValue
                )) {
                    continue;
                }

                $cell->setValue($normalizedValue);

                $corrections[] = [
                    'sheet' => $sheet->getTitle(),
                    'row' => $row,
                    'column' => $column,
                    'field' => 'Normalización de valor',
                    'old_value' => $originalValue,
                    'new_value' => $normalizedValue,
                    'detail' => 'Se normalizó automáticamente el contenido de la celda.',
                ];
            }
        }
    }

    private function normalizeCellValue(Cell $cell): mixed
    {
        $value = $cell->getValue();

        if ($value === null) {
            return null;
        }

        /*
         * Si Excel reconoce la celda como fecha, conservamos
         * la fecha como valor serial y solo cambiamos el formato.
         */
        if (Date::isDateTime($cell) && is_numeric($value)) {
            $cell->getStyle()
                ->getNumberFormat()
                ->setFormatCode('yyyy-mm-dd');

            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $normalizedDate = $this->normalizeDateString($trimmed);

        if ($normalizedDate !== null) {
            return $normalizedDate;
        }

        return $trimmed;
    }

    private function normalizeDateString(string $value): ?string
    {
        $formats = [
            'Y/m/d',
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat(
                '!'.$format,
                $value
            );

            if (! $date instanceof DateTimeImmutable) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            ) {
                continue;
            }

            if ($date->format($format) !== $value) {
                continue;
            }

            return $date->format('Y-m-d');
        }

        return null;
    }

    /**
     * Asigna consecutivos globales a las hojas de detalle.
     *
     * El consecutivo empieza en 1 y continúa entre todas las hojas:
     *
     * ID gestantes: 1
     * Atenciones: 2
     * Seguimientos: 3
     * Urgencias: 4
     */
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

                /*
                 * La columna 2 contiene el consecutivo.
                 */
                $cell = $sheet->getCell([2, $row]);
                $oldValue = $cell->getValue();

                if ((string) $oldValue !== (string) $consecutive) {
                    $cell->setValue($consecutive);

                    $corrections[] = [
                        'sheet' => $sheetName,
                        'row' => $row,
                        'column' => 2,
                        'field' => 'Consecutivo de registro',
                        'old_value' => $oldValue,
                        'new_value' => $consecutive,
                        'detail' => 'Se asignó el consecutivo global correcto.',
                    ];
                }

                $consecutive++;
            }
        }

        return $consecutive - 1;
    }

    /**
     * Corrige el total de registros de detalle en Control.
     */
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

        /*
         * Según la estructura actual:
         * fila 2, columna 7 = total de registros de detalle.
         */
        $cell = $sheet->getCell([7, 2]);
        $oldValue = $cell->getValue();

        if ((string) $oldValue === (string) $totalDetailRecords) {
            return;
        }

        $cell->setValue($totalDetailRecords);

        $corrections[] = [
            'sheet' => $sheetName,
            'row' => 2,
            'column' => 7,
            'field' => 'Total de registros de detalle',
            'old_value' => $oldValue,
            'new_value' => $totalDetailRecords,
            'detail' => 'Se actualizó el total de registros reales del archivo.',
        ];
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

    private function buildDestinationPath(string $sourcePath): string
    {
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $timestamp = now()->format('Ymd_His');

        return storage_path(
            "app/private/gestantes/corregidos/{$filename}_corregido_{$timestamp}.xlsx"
        );
    }
}