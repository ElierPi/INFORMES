<?php

namespace App\Services\Resolucion1552;

use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

final class Resolucion1552SanitasExporter
{
    private const ALLOWED_DOCUMENT_TYPES = ['CC', 'TI', 'CE', 'PA', 'RC', 'PE', 'MS', 'AS', 'CD', 'NV', 'PT', 'SC', 'CN'];
    private const ALLOWED_REGIMES = ['C', 'S'];

    public function __construct(private readonly Resolucion1552SanitasExcelReader $reader) {}

    /** @return array<string, mixed> */
    public function export(string $excelPath, string $period): array
    {
        $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $period . '-01');
        if (! $periodStart instanceof DateTimeImmutable) {
            throw new RuntimeException('El período seleccionado no es válido.');
        }
        $periodEnd = $periodStart->modify('last day of this month');

        $input = $this->reader->read($excelPath);
        $records = $input['records'];
        $sourceRows = $input['source_rows'];
        $errors = [];
        $warnings = $input['warnings'];
        $phoneCorrections = 0;
        $specialties = $this->specialtyCodes();
        $seen = [];
        $duplicateCount = 0;

        foreach ($records as $index => $record) {
            $row = (int) ($sourceRows[$index] ?? $index + 2);
            $beforePhone = $record[4];

            if ($beforePhone === '9999999999') {
                $phoneCorrections++;
                $warnings[] = $this->issue($row, 'Número telefónico del contacto', 'Se asignó automáticamente 9999999999 porque el teléfono estaba vacío o no era válido.', $beforePhone, 'warning');
            }

            $this->validateRecord($record, $row, $periodStart, $periodEnd, $specialties, $errors);

            $signature = implode("\t", $record);
            if (isset($seen[$signature])) {
                $duplicateCount++;
                $warnings[] = $this->issue($row, 'Registro duplicado', 'El registro es idéntico a uno anterior. Se conserva y se reporta como advertencia.', '', 'warning');
            } else {
                $seen[$signature] = $row;
            }
        }

        $validRows = count($records) - count(array_unique(array_map(static fn (array $error): int => (int) $error['source_row'], $errors)));
        $statistics = [
            'records_count' => count($records),
            'valid_records_count' => max(0, $validRows),
            'invalid_records_count' => count($records) - max(0, $validRows),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
            'duplicate_count' => $duplicateCount,
            'phone_corrections_count' => $phoneCorrections,
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'validation' => compact('errors', 'warnings', 'statistics'),
            ];
        }

        $providerCodes = array_values(array_unique(array_column($records, 0)));
        if (count($providerCodes) !== 1 || ! preg_match('/^\d{12}$/', $providerCodes[0])) {
            throw new RuntimeException('El archivo debe contener un único código de habilitación válido de 12 dígitos.');
        }

        $providerCode = $providerCodes[0];
        $baseName = sprintf('RESOLUCION_1552_%s_%s', $providerCode, $periodEnd->format('dmY'));
        $folder = storage_path('app/private/generated/resolucion1552-sanitas/' . uniqid('', true));
        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $txtName = $baseName . '.txt';
        $zipName = $baseName . '.zip';
        $txtPath = $folder . DIRECTORY_SEPARATOR . $txtName;
        $zipPath = $folder . DIRECTORY_SEPARATOR . $zipName;

        $rows = [Resolucion1552SanitasExcelReader::HEADERS, ...$records];
        $utf8 = implode("\r\n", array_map(static fn (array $row): string => implode("\t", $row), $rows));
        $ansi = iconv('UTF-8', 'Windows-1252//TRANSLIT', $utf8);
        if ($ansi === false || file_put_contents($txtPath, $ansi) === false) {
            throw new RuntimeException('No fue posible generar el TXT ANSI.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el ZIP.');
        }
        $zip->addFile($txtPath, $txtName);
        $zip->close();

        return [
            'success' => true,
            'provider_code' => $providerCode,
            'txt_path' => $txtPath,
            'txt_name' => $txtName,
            'zip_path' => $zipPath,
            'zip_name' => $zipName,
            'validation' => compact('errors', 'warnings', 'statistics'),
        ];
    }

    private function validateRecord(array $record, int $row, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, array $specialties, array &$errors): void
    {
        $fields = ['Código de habilitación', 'Tipo de documento', 'Número de identificación', 'Régimen', 'Teléfono', 'Especialidad', 'Fecha de solicitud', 'Fecha solicitada', 'Fecha asignada', 'Horas-especialista'];
        foreach ($record as $index => $value) {
            if (trim((string) $value) === '') {
                $errors[] = $this->issue($row, $fields[$index], 'El campo es obligatorio y se encuentra vacío.', '', 'error');
            }
        }

        if (! preg_match('/^\d{12}$/', $record[0])) {
            $errors[] = $this->issue($row, $fields[0], 'Debe contener exactamente 12 dígitos.', $record[0], 'error');
        }
        if (! in_array($record[1], self::ALLOWED_DOCUMENT_TYPES, true)) {
            $errors[] = $this->issue($row, $fields[1], 'El tipo de documento no pertenece a los valores permitidos.', $record[1], 'error');
        }
        $idLength = strlen($record[2]);
        if ($idLength < 3 || $idLength > 20) {
            $errors[] = $this->issue($row, $fields[2], 'La identificación debe tener entre 3 y 20 caracteres.', $record[2], 'error');
        }
        if (! in_array($record[3], self::ALLOWED_REGIMES, true)) {
            $errors[] = $this->issue($row, $fields[3], 'El régimen permitido es C o S.', $record[3], 'error');
        }
        if (strlen($record[4]) < 10 || strlen($record[4]) > 21 || ! preg_match('/^\d{10}(,\d{10})?$/', $record[4])) {
            $errors[] = $this->issue($row, $fields[4], 'Debe registrar uno o dos teléfonos de 10 dígitos separados por coma.', $record[4], 'error');
        }
        if (! isset($specialties[$record[5]])) {
            $errors[] = $this->issue($row, $fields[5], 'La especialidad no existe en el catálogo oficial de la Resolución 1552.', $record[5], 'error');
        }

        $dates = [];
        foreach ([6, 7, 8] as $index) {
            $date = DateTimeImmutable::createFromFormat('!d/m/Y', $record[$index]);
            if (! $date instanceof DateTimeImmutable || $date->format('d/m/Y') !== $record[$index]) {
                $errors[] = $this->issue($row, $fields[$index], 'La fecha debe tener formato DD/MM/AAAA.', $record[$index], 'error');
            }
            $dates[$index] = $date ?: null;
        }
        if ($dates[6] instanceof DateTimeImmutable && ($dates[6] < $periodStart || $dates[6] > $periodEnd)) {
            $errors[] = $this->issue($row, $fields[6], 'La fecha de solicitud debe pertenecer al período reportado.', $record[6], 'error');
        }
        if ($dates[6] instanceof DateTimeImmutable && $dates[7] instanceof DateTimeImmutable && $dates[7] < $dates[6]) {
            $errors[] = $this->issue($row, $fields[7], 'No puede ser inferior a la fecha en que el usuario solicita la cita.', $record[7], 'error');
        }
        if ($dates[6] instanceof DateTimeImmutable && $dates[8] instanceof DateTimeImmutable && $dates[8] < $dates[6]) {
            $errors[] = $this->issue($row, $fields[8], 'No puede ser inferior a la fecha en que el usuario solicita la cita.', $record[8], 'error');
        }
        if (! is_numeric($record[9]) || (float) $record[9] <= 0 || (float) $record[9] > 99999) {
            $errors[] = $this->issue($row, $fields[9], 'Debe ser un número mayor que cero y de máximo cinco caracteres.', $record[9], 'error');
        }
    }

    /** @return array<string, string> */
    private function specialtyCodes(): array
    {
        $path = base_path('config/resolucion1552_sanitas_specialties.php');
        $codes = is_file($path) ? require $path : [];
        return is_array($codes) ? $codes : [];
    }

    private function issue(int $row, string $field, string $message, string $value, string $severity): array
    {
        return ['source_row' => $row, 'field_name' => $field, 'message' => $message, 'value' => $value, 'severity' => $severity];
    }
}
