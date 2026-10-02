<?php

namespace App\Services\DemandaInducida;

use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;
use ZipArchive;

class DemandaInducidaErrorCorrectionService
{
    public function correct(string $zipPath, string $logPath): array
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException('No se encontró el ZIP rechazado.');
        }

        if (! is_file($logPath)) {
            throw new RuntimeException('No se encontró el LOG de SIGIRES.');
        }

        $workDir = storage_path(
            'app/private/demanda-inducida/correction/'.uniqid('', true)
        );

        File::ensureDirectoryExists($workDir);

        try {
            [$xlsxPath, $xlsxName] = $this->extractXlsx($zipPath, $workDir);
            $errors = $this->parseLog($logPath);

            $spreadsheet = IOFactory::load($xlsxPath);
            [$sheet, $headerRow] = $this->findSheet($spreadsheet);

            $changes = [];
            $pending = [];

            foreach ($errors as $error) {
                $row = (int) ($error['row'] ?? 0);
                $column = (int) ($error['column'] ?? 0);
                $description = $this->comparable(
                    (string) ($error['description'] ?? '')
                );

                if ($row <= $headerRow || $column < 1 || $column > 35) {
                    $pending[] = $error;
                    continue;
                }

                if ($column === 10 && str_contains($description, 'relacion con el usuario')) {
                    $old = trim((string) $sheet->getCell([10, $row])->getValue());
                    $map = [
                        'hija' => 'Hijo(a)', 'hijo' => 'Hijo(a)',
                        'amiga' => 'Amigo', 'amigo' => 'Amigo',
                        'esposo' => 'Otro. Cual', 'esposa' => 'Otro. Cual',
                        'conyuge' => 'Otro. Cual',
                        'suegra' => 'Otro. Cual', 'suegro' => 'Otro. Cual',
                        'cuñada' => 'Otro. Cual', 'cunada' => 'Otro. Cual',
                        'cuñado' => 'Otro. Cual', 'cunado' => 'Otro. Cual',
                    ];
                    $new = $map[$this->comparable($old)] ?? 'Otro. Cual';
                    $this->write($sheet, $row, 10, $old, $new, $error['description'], $changes);
                    continue;
                }

                if ($column === 11 && str_contains($description, 'fecha de llamada')) {
                    $old = $sheet->getCell([11, $row])->getValue();
                    $new = $this->normalizeDate(
                        $sheet->getCell([3, $row])->getValue()
                    );

                    if ($new !== null) {
                        $this->write($sheet, $row, 11, $old, $new, $error['description'], $changes);
                    } else {
                        $pending[] = $error;
                    }
                    continue;
                }

                if ($column === 13 && str_contains($description, 'texto de llamada debe ser diferente a vacio')) {
                    $old = $sheet->getCell([13, $row])->getValue();
                    $this->write(
                        $sheet, $row, 13, $old,
                        'NO SE LOGRO CONTACTO CON EL USUARIO',
                        $error['description'], $changes
                    );
                    continue;
                }

                if ($column === 25 && str_contains($description, 'resultado de la llamada debe ser')) {
                    $old = $sheet->getCell([25, $row])->getValue();
                    $this->write(
                        $sheet, $row, 25, $old, 'Otro',
                        $error['description'], $changes
                    );
                    continue;
                }

                $pending[] = $error;
            }

            // Barrido completo: aplica las reglas conocidas a todo el archivo.
            $this->sweepKnownRules($sheet, $headerRow, $changes);

            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

            $base = pathinfo($xlsxName, PATHINFO_FILENAME);
            $zipName = $base.'.zip';
            $outputZip = $workDir.DIRECTORY_SEPARATOR.$zipName;

            $newZip = new ZipArchive();
            if ($newZip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el ZIP corregido.');
            }

            $newZip->addFile($xlsxPath, $xlsxName);
            $newZip->close();

            return [
                'xlsx_path' => $xlsxPath,
                'zip_path' => $outputZip,
                'xlsx_name' => $xlsxName,
                'zip_name' => $zipName,
                'errors' => $errors,
                'error_count' => count($errors),
                'changes' => $changes,
                'change_count' => count($changes),
                'pending' => $pending,
                'pending_count' => count($pending),
            ];
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException(
                'No fue posible corregir Demanda inducida: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    private function extractXlsx(string $zipPath, string $workDir): array
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('No fue posible abrir el ZIP.');
        }

        $entry = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (
                is_string($name)
                && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'xlsx'
            ) {
                $entry = $name;
                break;
            }
        }

        if ($entry === null) {
            $zip->close();
            throw new RuntimeException('El ZIP no contiene un XLSX.');
        }

        $content = $zip->getFromName($entry);
        $zip->close();

        if ($content === false) {
            throw new RuntimeException('No fue posible extraer el XLSX.');
        }

        $name = basename($entry);
        $path = $workDir.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $content);

        return [$path, $name];
    }

    private function parseLog(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();

        $headerRow = null;

        for ($row = 1; $row <= min(20, $highestRow); $row++) {
            $a = $this->comparable((string) $sheet->getCell([1, $row])->getValue());
            $b = $this->comparable((string) $sheet->getCell([2, $row])->getValue());

            if ($a === 'fila' && $b === 'columna') {
                $headerRow = $row;
                break;
            }
        }

        if ($headerRow === null) {
            throw new RuntimeException(
                'No se encontró el encabezado Fila/Columna en el LOG.'
            );
        }

        $errors = [];

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $reportRow = (int) $sheet->getCell([1, $row])->getValue();
            $column = (int) $sheet->getCell([2, $row])->getValue();
            $description = trim((string) $sheet->getCell([6, $row])->getValue());

            if ($reportRow <= 0 || $column <= 0 || $description === '') {
                continue;
            }

            $errors[] = [
                'row' => $reportRow,
                'column' => $column,
                'type' => trim((string) $sheet->getCell([3, $row])->getValue()),
                'old' => $sheet->getCell([4, $row])->getValue(),
                'new' => $sheet->getCell([5, $row])->getValue(),
                'description' => $description,
            ];
        }

        return $errors;
    }

    private function sweepKnownRules($sheet, int $headerRow, array &$changes): void
    {
        $highestRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            if (trim((string) $sheet->getCell([1, $row])->getValue()) === '') {
                continue;
            }

            $element = trim((string) $sheet->getCell([4, $row])->getValue());
            $classification = trim((string) $sheet->getCell([8, $row])->getValue());

            $relationship = trim((string) $sheet->getCell([10, $row])->getValue());

            if ($relationship !== '') {
                $map = [
                    'hija' => 'Hijo(a)', 'hijo' => 'Hijo(a)',
                    'amiga' => 'Amigo', 'amigo' => 'Amigo',
                    'esposo' => 'Otro. Cual', 'esposa' => 'Otro. Cual',
                    'conyuge' => 'Otro. Cual',
                    'suegra' => 'Otro. Cual', 'suegro' => 'Otro. Cual',
                    'cuñada' => 'Otro. Cual', 'cunada' => 'Otro. Cual',
                    'cuñado' => 'Otro. Cual', 'cunado' => 'Otro. Cual',
                ];

                $key = $this->comparable($relationship);

                if (isset($map[$key])) {
                    $this->writeIfDifferent(
                        $sheet, $row, 10, $map[$key],
                        'Normalización de relación con el usuario', $changes
                    );
                }
            }

            if ($this->sameText($element, 'Llamada telefónica')) {
                $callDate = trim((string) $sheet->getCell([11, $row])->getValue());

                if ($this->normalizeDate($callDate) === null) {
                    $activity = $this->normalizeDate(
                        $sheet->getCell([3, $row])->getValue()
                    );

                    if ($activity !== null) {
                        $this->writeIfDifferent(
                            $sheet, $row, 11, $activity,
                            'Fecha de llamada = Fecha actividad DI', $changes
                        );
                    }
                }
            }

            if (
                $this->sameText($element, 'Llamada telefónica')
                && $this->sameText($classification, 'No efectivo')
            ) {
                if (trim((string) $sheet->getCell([13, $row])->getValue()) === '') {
                    $this->writeIfDifferent(
                        $sheet, $row, 13,
                        'NO SE LOGRO CONTACTO CON EL USUARIO',
                        'Texto requerido en llamada no efectiva', $changes
                    );
                }

                $result = trim((string) $sheet->getCell([25, $row])->getValue());
                $allowed = [
                    'Número equivocado',
                    'Número no existe',
                    'Sistema correo de voz',
                    'Otro',
                ];

                if ($result === '' || ! $this->inAllowed($result, $allowed)) {
                    $this->writeIfDifferent(
                        $sheet, $row, 25, 'Otro',
                        'Resultado de llamada no efectiva', $changes
                    );
                }
            }
        }
    }

    private function findSheet($spreadsheet): array
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            for ($row = 1; $row <= min(20, $sheet->getHighestDataRow()); $row++) {
                $a = mb_strtoupper(trim((string) $sheet->getCell([1, $row])->getValue()));
                $b = mb_strtoupper(trim((string) $sheet->getCell([2, $row])->getValue()));

                if ($a === 'TIPO DOC' && $b === 'NUMERO DOC') {
                    return [$sheet, $row];
                }
            }
        }

        throw new RuntimeException(
            'No se encontró la estructura de Demanda inducida.'
        );
    }

    private function write(
        $sheet,
        int $row,
        int $column,
        mixed $old,
        string $new,
        string $description,
        array &$changes
    ): void {
        $sheet->setCellValueExplicit(
            [$column, $row],
            $new,
            DataType::TYPE_STRING
        );

        $changes[] = [
            'row' => $row,
            'column' => $column,
            'old' => $old,
            'new' => $new,
            'description' => $description,
        ];
    }

    private function writeIfDifferent(
        $sheet,
        int $row,
        int $column,
        string $new,
        string $description,
        array &$changes
    ): void {
        $old = $sheet->getCell([$column, $row])->getValue();

        if ((string) $old === $new) {
            return;
        }

        $this->write(
            $sheet, $row, $column, $old, $new, $description, $changes
        );
    }

    private function normalizeDate(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $text, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];

            if (checkdate($month, $day, $year)) {
                return sprintf('%02d/%02d/%04d', $day, $month, $year);
            }
        }

        if (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $text, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];

            if (checkdate($month, $day, $year)) {
                return sprintf('%02d/%02d/%04d', $day, $month, $year);
            }
        }

        return null;
    }

    private function inAllowed(string $value, array $allowed): bool
    {
        foreach ($allowed as $candidate) {
            if ($this->sameText($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function sameText(string $a, string $b): bool
    {
        return $this->comparable($a) === $this->comparable($b);
    }

    private function comparable(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        ]);

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
