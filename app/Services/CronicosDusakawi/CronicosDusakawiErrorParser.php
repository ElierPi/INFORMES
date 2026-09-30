<?php

namespace App\Services\CronicosDusakawi;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class CronicosDusakawiErrorParser
{
    /** @return array<int, array<string, mixed>> */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel de errores de DUSAKAWI.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Errores') ?? $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        $errors = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $excelRow = $this->toInt($sheet->getCell("B{$row}")->getValue());
            $document = trim((string) $sheet->getCell("C{$row}")->getFormattedValue());
            $message = trim((string) $sheet->getCell("E{$row}")->getFormattedValue());
            $suggestion = trim((string) $sheet->getCell("F{$row}")->getFormattedValue());

            if ($excelRow === null || $message === '') {
                continue;
            }

            $errors[] = [
                'excel_row' => $excelRow,
                'document' => $document,
                'message' => $message,
                'suggestion' => $suggestion,
            ];
        }

        if ($errors === []) {
            throw new RuntimeException('No se encontraron novedades reconocibles en el archivo de errores.');
        }

        return $errors;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
