<?php

namespace App\Services\CronicosDusakawi;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

final class CronicosDusakawiCorrectionService
{
    public function __construct(
        private readonly CronicosDusakawiErrorParser $parser,
    ) {
    }

    /** @return array<string, mixed> */
    public function correct(
        string $reportPath,
        string $errorsPath,
        string $outputPath,
        bool $removeDuplicates = false,
    ): array {
        if (! is_file($reportPath)) {
            throw new RuntimeException('No se encontró el Excel original de crónicos.');
        }

        try {
            $errors = $this->parser->parse($errorsPath);
            $spreadsheet = IOFactory::load($reportPath);
            $sheet = $spreadsheet->getSheetByName('Hoja1') ?? $spreadsheet->getActiveSheet();

            $audit = [];
            $manual = [];
            $changedCells = [];
            $rowsToDelete = [];

            foreach ($errors as $error) {
                $row = (int) $error['excel_row'];
                $message = (string) $error['message'];
                $document = (string) $error['document'];
                $normalized = Str::of($message)->ascii()->lower()->toString();

                if ($row < 3 || $row > $sheet->getHighestDataRow()) {
                    $manual[] = $this->manual($row, $document, 'FILA', 'La fila reportada no existe en el Excel original.', $message);
                    continue;
                }

                // AY - PARCIAL DE ORINA: ALTERADO -> PATOLOGICO.
                if (str_contains($normalized, 'col ay (parcial_orina)') && str_contains($normalized, 'alterado')) {
                    $this->setValue($sheet, "AY{$row}", 'PATOLOGICO', $audit, $changedCells, $row, $document, 'AY', 'ALTERADO → PATOLOGICO', $message);
                }

                // BK - DM CONTROLADA.
                // - Sin DX de DM: NO APLICA.
                // - Con DX de DM y HbA1c válida (BI): < 7 = SI, >= 7 = NO.
                // - Sin HbA1c válida: NO APLICA para evitar conservar fechas/valores corruptos.
                if (str_contains($normalized, 'col bk (dm_controlada)')) {
                    $dxDm = strtoupper(trim((string) $sheet->getCell("AC{$row}")->getFormattedValue()));
                    $hbaRaw = $sheet->getCell("BI{$row}")->getCalculatedValue();
                    $hba = is_numeric($hbaRaw) ? (float) $hbaRaw : 0.0;

                    if ($dxDm === 'NO') {
                        $this->setValue($sheet, "BK{$row}", 'NO APLICA', $audit, $changedCells, $row, $document, 'BK', 'DX confirmado DM = NO → DM CONTROLADA = NO APLICA', $message);
                    } elseif ($dxDm === 'SI' && $hba > 0) {
                        $newValue = $hba < 7.0 ? 'SI' : 'NO';
                        $operator = $hba < 7.0 ? '< 7' : '>= 7';
                        $this->setValue(
                            $sheet,
                            "BK{$row}",
                            $newValue,
                            $audit,
                            $changedCells,
                            $row,
                            $document,
                            'BK',
                            'HbA1c '.rtrim(rtrim(number_format($hba, 2, '.', ''), '0'), '.').' '.$operator.' → DM CONTROLADA = '.$newValue,
                            $message
                        );
                    } else {
                        $this->setValue($sheet, "BK{$row}", 'NO APLICA', $audit, $changedCells, $row, $document, 'BK', 'Sin HbA1c válida → DM CONTROLADA = NO APLICA', $message);
                    }
                }

                // AE - TIPO DM. Si no hay DM confirmado, NO APLICA.
                if (str_contains($normalized, 'col ae (tipo_dm)')) {
                    $dxDm = strtoupper(trim((string) $sheet->getCell("AC{$row}")->getFormattedValue()));

                    if ($dxDm === 'NO') {
                        $this->setValue($sheet, "AE{$row}", 'NO APLICA', $audit, $changedCells, $row, $document, 'AE', 'DX confirmado DM = NO → TIPO DM = NO APLICA', $message);
                    } else {
                        $manual[] = $this->manual($row, $document, 'AE', 'El usuario tiene DX de DM; se debe confirmar el tipo de diabetes.', $message);
                    }
                }

                // Fechas imposibles/antiguas/futuras. Se elimina el valor inválido sin inventar una fecha.
                $dateColumns = [
                    'AX' => 'FECHA DE GLICEMIA BASAL',
                    'AZ' => 'FECHA PARCIAL DE ORINA',
                    'BB' => 'FECHA CREATININA SANGRE',
                    'BJ' => 'FECHA REPORTE HbA1c',
                ];

                foreach ($dateColumns as $column => $label) {
                    if (str_contains($normalized, 'col '.strtolower($column).' (')) {
                        $this->setValue($sheet, "{$column}{$row}", null, $audit, $changedCells, $row, $document, $column, "{$label}: fecha inválida eliminada", $message);
                    }
                }

                // AK - peso con fecha u otro dato no numérico: se limpia y queda pendiente de confirmación.
                if (str_contains($normalized, 'col ak (ultimo_peso)')) {
                    $this->setValue($sheet, "AK{$row}", null, $audit, $changedCells, $row, $document, 'AK', 'Valor no numérico eliminado', $message);
                    $manual[] = $this->manual($row, $document, 'AK', 'Confirmar el último peso real del usuario.', $message);
                }

                if (str_contains($normalized, 'no pertenece a su ips')) {
                    $rowsToDelete[$row] = [
                        'row' => $row,
                        'document' => $document,
                        'reason' => 'Usuario no pertenece a la IPS reportante',
                        'message' => $message,
                    ];
                }

                if (str_contains($normalized, 'documento no existe en la base (bdua)')) {
                    $rowsToDelete[$row] = [
                        'row' => $row,
                        'document' => $document,
                        'reason' => 'Documento no existe en BDUA',
                        'message' => $message,
                    ];
                }

                if (str_contains($normalized, 'salto de linea')) {
                    $changed = $this->sanitizeLineBreaksInRow($sheet, $row, $audit, $changedCells, $document, $message);

                    if (! $changed) {
                        $manual[] = $this->manual($row, $document, 'FILA', 'DUSAKAWI reportó un salto de línea, pero no se encontró CR/LF dentro de las celdas.', $message);
                    }
                }
            }

            // Limpieza preventiva global ANTES de eliminar filas.
            // PhpSpreadsheet puede invalidar referencias internas de celdas después de removeRow(),
            // por eso toda la normalización de texto se realiza mientras la hoja conserva su estructura original.
            $this->sanitizeTextCellsGlobally($sheet, $audit, $changedCells);

            // Exclusiones administrativas: se ejecutan al final para conservar las filas
            // originales utilizadas por el archivo de errores durante todas las correcciones.
            if ($rowsToDelete !== []) {
                krsort($rowsToDelete, SORT_NUMERIC);

                foreach ($rowsToDelete as $deleteRow => $deletion) {
                    $sheet->removeRow((int) $deleteRow, 1);
                    $audit[] = [
                        'row' => (int) $deletion['row'],
                        'document' => (string) $deletion['document'],
                        'column' => 'FILA',
                        'previous_value' => 'Registro completo',
                        'new_value' => '',
                        'action' => 'FILA ELIMINADA: '.(string) $deletion['reason'],
                        'message' => (string) $deletion['message'],
                    ];
                }
            }

            // Opción manual: eliminar duplicados solo cuando el usuario lo solicita.
            // DUSAKAWI suele reportarlos uno por uno, por eso esta regla no debe
            // ejecutarse automáticamente en todos los procesos.
            $duplicateRows = $removeDuplicates
                ? $this->removeDuplicateDocuments($sheet, $audit)
                : 0;

            // PhpSpreadsheet puede conservar filas vacías con estilos al final después
            // de removeRow(). DUSAKAWI las interpreta como líneas huérfanas del archivo.
            // Se eliminan físicamente todas las filas residuales posteriores al último
            // registro real antes de renumerar y guardar.
            $trimmedRows = $this->removeTrailingEmptyRows($sheet, $audit);

            // La columna A es el consecutivo del archivo. Después de cualquier exclusión
            // debe quedar continua desde 1, sin saltos.
            $this->renumberOrder($sheet);

            $directory = dirname($outputPath);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('No fue posible crear la carpeta de salida.');
            }

            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);

            return [
                'output_path' => $outputPath,
                'parsed_errors' => count($errors),
                'automatic_corrections' => count($audit),
                'updated_cells' => count($changedCells),
                'deleted_rows' => count($rowsToDelete) + $duplicateRows,
                'duplicate_rows' => $duplicateRows,
                'trimmed_rows' => $trimmedRows,
                'manual_errors' => $this->uniqueManual($manual),
                'audit' => $audit,
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('No fue posible corregir el Excel: '.$exception->getMessage(), previous: $exception);
        }
    }



    private function removeDuplicateDocuments(
        Worksheet $sheet,
        array &$audit,
    ): int {
        $seen = [];
        $duplicates = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 3; $row <= $highestRow; $row++) {
            $documentType = strtoupper(trim((string) $sheet->getCell("F{$row}")->getFormattedValue()));
            $document = trim((string) $sheet->getCell("G{$row}")->getFormattedValue());

            if ($document === '') {
                continue;
            }

            $key = $documentType.'|'.$document;

            if (! isset($seen[$key])) {
                $seen[$key] = $row;
                continue;
            }

            $duplicates[$row] = [
                'row' => $row,
                'first_row' => $seen[$key],
                'document_type' => $documentType,
                'document' => $document,
            ];
        }

        if ($duplicates === []) {
            return 0;
        }

        krsort($duplicates, SORT_NUMERIC);

        foreach ($duplicates as $row => $duplicate) {
            $sheet->removeRow((int) $row, 1);
            $audit[] = [
                'row' => (int) $duplicate['row'],
                'document' => trim($duplicate['document_type'].' '.$duplicate['document']),
                'column' => 'FILA',
                'previous_value' => 'Registro duplicado completo',
                'new_value' => '',
                'action' => 'FILA ELIMINADA: documento duplicado; se conservó la primera aparición (fila '.(int) $duplicate['first_row'].')',
                'message' => 'Regla preventiva DUSAKAWI: TIPO_DOCUMENTO + NUMERO_DOCUMENTO no debe repetirse.',
            ];
        }

        return count($duplicates);
    }

    private function removeTrailingEmptyRows(
        Worksheet $sheet,
        array &$audit,
    ): int {
        $highestRow = $sheet->getHighestRow();
        $lastDataRow = 2;

        // La columna G contiene el número de identificación y es obligatoria para
        // todo registro clínico. Buscamos desde abajo la última fila real.
        for ($row = $highestRow; $row >= 3; $row--) {
            $document = trim((string) $sheet->getCell("G{$row}")->getFormattedValue());

            if ($document !== '') {
                $lastDataRow = $row;
                break;
            }
        }

        if ($lastDataRow < 3 || $highestRow <= $lastDataRow) {
            $sheet->garbageCollect();
            return 0;
        }

        $rowsToRemove = $highestRow - $lastDataRow;
        $sheet->removeRow($lastDataRow + 1, $rowsToRemove);
        $sheet->garbageCollect();

        $audit[] = [
            'row' => $lastDataRow + 1,
            'document' => '',
            'column' => 'FILA',
            'previous_value' => $rowsToRemove.' fila(s) vacía(s) residual(es)',
            'new_value' => '',
            'action' => 'FILAS VACÍAS FINALES ELIMINADAS',
            'message' => 'Prevención DUSAKAWI: se eliminan filas vacías con estilos que pueden ser interpretadas como líneas huérfanas.',
        ];

        return $rowsToRemove;
    }

    private function renumberOrder(Worksheet $sheet): void
    {
        $consecutive = 1;

        for ($row = 3; $row <= $sheet->getHighestDataRow(); $row++) {
            if (trim((string) $sheet->getCell("G{$row}")->getFormattedValue()) === '') {
                continue;
            }

            $sheet->setCellValue("A{$row}", $consecutive++);
        }
    }

    private function sanitizeTextCellsGlobally(
        Worksheet $sheet,
        array &$audit,
        array &$changedCells,
    ): void {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
                $coordinate = $column.$row;
                $value = $sheet->getCell($coordinate)->getValue();

                // Desactivar wrap en toda celda de texto, aunque no tenga CR/LF visible.
                if (is_string($value)) {
                    $sheet->getStyle($coordinate)->getAlignment()->setWrapText(false);

                    $clean = preg_replace('/[\r\n]+/u', ' ', $value) ?? $value;
                    $clean = trim((string) preg_replace('/\s{2,}/u', ' ', $clean));

                    if ($clean !== $value) {
                        $document = $row >= 3
                            ? trim((string) $sheet->getCell("G{$row}")->getFormattedValue())
                            : '';

                        $sheet->setCellValueExplicit(
                            $coordinate,
                            $clean,
                            DataType::TYPE_STRING
                        );
                        $changedCells[$coordinate] = true;
                        $audit[] = [
                            'row' => $row,
                            'document' => $document,
                            'column' => $column,
                            'previous_value' => $value,
                            'new_value' => $clean,
                            'action' => 'Limpieza global: CR/LF y espacios normalizados; wrap_text desactivado',
                            'message' => 'Limpieza preventiva global de texto para DUSAKAWI',
                        ];
                    }
                }
            }
        }
    }


    private function sanitizeLineBreaksInRow(
        Worksheet $sheet,
        int $row,
        array &$audit,
        array &$changedCells,
        string $document,
        string $message,
    ): bool {
        $highestColumn = $sheet->getHighestDataColumn();
        $range = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false, false)[0] ?? [];
        $changed = false;

        foreach ($range as $index => $value) {
            if (! is_string($value) || (! str_contains($value, "\n") && ! str_contains($value, "\r"))) {
                continue;
            }

            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $coordinate = $column.$row;
            $clean = preg_replace('/[\r\n]+/u', ' ', $value) ?? $value;
            $clean = trim((string) preg_replace('/\s{2,}/u', ' ', $clean));

            $this->setValue(
                $sheet,
                $coordinate,
                $clean,
                $audit,
                $changedCells,
                $row,
                $document,
                $column,
                'Salto de línea reemplazado por espacio',
                $message
            );
            $changed = true;
        }

        return $changed;
    }

    private function setValue(
        Worksheet $sheet,
        string $coordinate,
        mixed $newValue,
        array &$audit,
        array &$changedCells,
        int $row,
        string $document,
        string $column,
        string $action,
        string $message,
    ): void {
        if (isset($changedCells[$coordinate])) {
            return;
        }

        $oldValue = $sheet->getCell($coordinate)->getFormattedValue();

        if ($newValue === null) {
            $sheet->setCellValue($coordinate, null);
        } else {
            $sheet->setCellValueExplicit(
                $coordinate,
                (string) $newValue,
                DataType::TYPE_STRING
            );
        }

        $changedCells[$coordinate] = true;
        $audit[] = [
            'row' => $row,
            'document' => $document,
            'column' => $column,
            'previous_value' => $oldValue,
            'new_value' => $newValue === null ? '' : (string) $newValue,
            'action' => $action,
            'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function manual(int $row, string $document, string $field, string $reason, string $message): array
    {
        return [
            'row' => $row,
            'document' => $document,
            'field' => $field,
            'reason' => $reason,
            'message' => $message,
        ];
    }

    /** @param array<int, array<string, mixed>> $manual */
    private function uniqueManual(array $manual): array
    {
        $seen = [];
        $result = [];

        foreach ($manual as $item) {
            $key = ($item['row'] ?? '').'|'.($item['field'] ?? '').'|'.($item['reason'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }
}
