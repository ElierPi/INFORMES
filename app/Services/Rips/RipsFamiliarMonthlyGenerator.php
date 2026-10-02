<?php

namespace App\Services\Rips;

use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;
use ZipArchive;

class RipsFamiliarMonthlyGenerator
{
    private const TEMPLATE_PATH =
        'storage/app/templates/rips/Plantilla_excel_RIPS_JSON_948_COMPLETA.xlsx';

    private const PRESTADOR = '444300063502';

    /**
     * Primera etapa del nuevo RIPS:
     * SOLO se llenan US, AC y AP.
     *
     * US <- hoja AC del archivo fuente
     * AC <- hoja AC del archivo fuente
     * AP <- Hoja2 del archivo fuente
     *
     * AF, AM, AT, AU, AH y AN permanecen intactas/vacías.
     */
    public function generateMonth(
        string $sourcePath,
        string $period
    ): array {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('No se encontró el Excel fuente.');
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new RuntimeException('El período debe tener formato AAAA-MM.');
        }

        [$year, $month] = array_map('intval', explode('-', $period));
        $templatePath = base_path(self::TEMPLATE_PATH);

        if (! is_file($templatePath)) {
            throw new RuntimeException('No se encontró la plantilla RIPS completa.');
        }

        try {
            $source = IOFactory::load($sourcePath);
            $sourceAc = $source->getSheetByName('AC');
            $sourceAp = $source->getSheetByName('Hoja2');

            if (! $sourceAc instanceof Worksheet || ! $sourceAp instanceof Worksheet) {
                throw new RuntimeException('El archivo fuente debe contener AC y Hoja2.');
            }

            $allAcRows = $this->filterAcRows($sourceAc, $year, $month);
            $procedureRows = $this->filterApRows($sourceAp, $year, $month);

            $morbidityRows = [];
            $pymRows = [];

            foreach ($allAcRows as $row) {
                if ($this->isMorbidityCups($row['cups_raw'] ?? '')) {
                    $morbidityRows[] = $row;
                } else {
                    $pymRows[] = $row;
                }
            }

            if ($morbidityRows === [] && $pymRows === [] && $procedureRows === []) {
                throw new RuntimeException('No se encontraron registros para '.$period.'.');
            }

            $workDir = storage_path('app/private/rips/familiar/'.uniqid('', true));
            File::ensureDirectoryExists($workDir);
            $code = sprintf('%04d_%02d', $year, $month);
            $files = [];

            // MORBILIDAD: US + AC, AP vacío.
            $mor = IOFactory::load($templatePath);
            $this->buildUs($mor->getSheetByName('US'), $morbidityRows);
            $this->buildAc($mor->getSheetByName('AC'), $morbidityRows);
            $this->clearDataRows($mor->getSheetByName('AP'), 29, 0);
            $morPath = $workDir.DIRECTORY_SEPARATOR.'RIPS_MORBILIDAD_'.$code.'.xlsx';
            IOFactory::createWriter($mor, 'Xlsx')->save($morPath);
            $files['MORBILIDAD'] = ['path'=>$morPath,'name'=>basename($morPath),'count'=>count($morbidityRows)];

            // PYM: US + AC, AP vacío.
            $pym = IOFactory::load($templatePath);
            $this->buildUs($pym->getSheetByName('US'), $pymRows);
            $this->buildAc($pym->getSheetByName('AC'), $pymRows);
            $this->clearDataRows($pym->getSheetByName('AP'), 29, 0);
            $pymPath = $workDir.DIRECTORY_SEPARATOR.'RIPS_PYM_'.$code.'.xlsx';
            IOFactory::createWriter($pym, 'Xlsx')->save($pymPath);
            $files['PYM'] = ['path'=>$pymPath,'name'=>basename($pymPath),'count'=>count($pymRows)];

            // PROCEDIMIENTOS: solo AP, US y AC vacíos.
            $proc = IOFactory::load($templatePath);
            $this->clearDataRows($proc->getSheetByName('US'), 15, 0);
            $this->clearDataRows($proc->getSheetByName('AC'), 32, 0);
            $this->buildAp($proc->getSheetByName('AP'), $procedureRows);
            $procPath = $workDir.DIRECTORY_SEPARATOR.'RIPS_PROCEDIMIENTOS_'.$code.'.xlsx';
            IOFactory::createWriter($proc, 'Xlsx')->save($procPath);
            $files['PROCEDIMIENTOS'] = ['path'=>$procPath,'name'=>basename($procPath),'count'=>count($procedureRows)];

            $zipName = 'RIPS_FAMILIAR_'.$code.'_3_ARCHIVOS.zip';
            $zipPath = $workDir.DIRECTORY_SEPARATOR.$zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el ZIP mensual.');
            }
            foreach ($files as $file) $zip->addFile($file['path'], $file['name']);
            $zip->close();

            return [
                'period'=>sprintf('%04d-%02d',$year,$month),
                'files'=>$files,
                'zip_path'=>$zipPath,
                'zip_name'=>$zipName,
                'counts'=>[
                    'MORBILIDAD'=>count($morbidityRows),
                    'PYM'=>count($pymRows),
                    'PROCEDIMIENTOS'=>count($procedureRows),
                ],
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) throw $exception;
            throw new RuntimeException('No fue posible generar el RIPS mensual: '.$exception->getMessage(), previous:$exception);
        }
    }

    public function generateJuneToDecember(
        string $sourcePath,
        int $year
    ): array {
        $months = [];
        $files = [];

        for ($month = 6; $month <= 12; $month++) {
            $period = sprintf('%04d-%02d', $year, $month);
            try {
                $result = $this->generateMonth($sourcePath, $period);
                $months[] = $result;
                foreach ($result['files'] as $file) $files[] = $file;
            } catch (RuntimeException $exception) {
                if (str_contains($exception->getMessage(), 'No se encontraron registros')) continue;
                throw $exception;
            }
        }

        if ($files === []) {
            throw new RuntimeException('No se encontraron datos entre junio y diciembre de '.$year.'.');
        }

        $workDir = storage_path('app/private/rips/familiar/lotes/'.uniqid('', true));
        File::ensureDirectoryExists($workDir);
        $zipName = 'RIPS_FAMILIAR_'.$year.'_JUNIO_DICIEMBRE_3_POR_MES.zip';
        $zipPath = $workDir.DIRECTORY_SEPARATOR.$zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el ZIP de meses.');
        }
        foreach ($files as $file) $zip->addFile($file['path'], $file['name']);
        $zip->close();

        return [
            'zip_path'=>$zipPath,
            'zip_name'=>$zipName,
            'months'=>$months,
            'month_count'=>count($months),
            'file_count'=>count($files),
        ];
    }

    private function filterAcRows(
        Worksheet $sheet,
        int $year,
        int $month
    ): array {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $date = $this->parseExcelDate(
                $sheet->getCell([11, $row])->getValue()
            );

            if (
                ! $date
                || (int) $date->format('Y') !== $year
                || (int) $date->format('n') !== $month
            ) {
                continue;
            }

            $birthDate = $this->parseExcelDate(
                $sheet->getCell([5, $row])->getValue()
            );

            $cupsRaw = trim(
                (string) $sheet
                    ->getCell([12, $row])
                    ->getValue()
            );

            $rows[] = [
                'tipo_documento' => trim(
                    (string) $sheet
                        ->getCell([2, $row])
                        ->getValue()
                ),
                'documento' => trim(
                    (string) $sheet
                        ->getCell([3, $row])
                        ->getValue()
                ),
                'nombre_completo' => trim(
                    (string) $sheet
                        ->getCell([4, $row])
                        ->getValue()
                ),
                'fecha_nacimiento' => $birthDate,
                'sexo' => trim(
                    (string) $sheet
                        ->getCell([6, $row])
                        ->getValue()
                ),
                'dx' => trim(
                    (string) $sheet
                        ->getCell([7, $row])
                        ->getValue()
                ),
                'fecha_atencion' => $date,
                'cups_raw' => $cupsRaw,
                'cups' => $this->extractFirstToken($cupsRaw),
                'medico' => trim(
                    (string) $sheet
                        ->getCell([13, $row])
                        ->getValue()
                ),
            ];
        }

        return $rows;
    }

    /**
     * Hoja2 del origen.
     *
     * A = CUPS / código fuente
     * B = Descripción
     * C = AFGILIADO
     * D = F-ATENCION
     * E = MEDICO
     * F = DX
     */
    private function isMorbidityCups(string $cupsRaw): bool
    {
        $value = trim(mb_strtoupper($cupsRaw));

        return $value !== ''
            && preg_match('/(?:^|\s)MOR\s*$/u', $value) === 1;
    }

    private function filterApRows(
        Worksheet $sheet,
        int $year,
        int $month
    ): array {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $date = $this->parseExcelDate(
                $sheet->getCell([4, $row])->getValue()
            );

            if (
                ! $date
                || (int) $date->format('Y') !== $year
                || (int) $date->format('n') !== $month
            ) {
                continue;
            }

            $rows[] = [
                'codigo' => trim(
                    (string) $sheet
                        ->getCell([1, $row])
                        ->getValue()
                ),
                'descripcion' => trim(
                    (string) $sheet
                        ->getCell([2, $row])
                        ->getValue()
                ),
                'documento' => trim(
                    (string) $sheet
                        ->getCell([3, $row])
                        ->getValue()
                ),
                'fecha_atencion' => $date,
                'medico' => trim(
                    (string) $sheet
                        ->getCell([5, $row])
                        ->getValue()
                ),
                'dx' => trim(
                    (string) $sheet
                        ->getCell([6, $row])
                        ->getValue()
                ),
            ];
        }

        return $rows;
    }

    private function buildUs(
        Worksheet $sheet,
        array $rows
    ): void {
        $this->clearDataRows(
            $sheet,
            15,
            count($rows)
        );

        $targetRow = 2;

        foreach ($rows as $item) {
            /*
             * US <- AC
             *
             * A Tipo documento      <- AC!B
             * B Documento           <- AC!C
             * C Tipo usuario        <- 04
             * D Fecha nacimiento    <- AC!E
             * E Sexo                <- AC!F
             * F País residencia     <- 170
             * G Municipio           <- 44430
             * H Zona                <- vacío por ahora
             * I Incapacidad         <- NO
             * J País origen         <- 170
             * K Registro SIRAS      <- vacío
             * L:O nombres separados <- vacío por ahora
             *
             * La fuente sí trae el nombre completo, pero no se divide
             * automáticamente porque hacerlo puede separar mal apellidos
             * y nombres compuestos.
             */
            $this->writeText(
                $sheet,
                "A{$targetRow}",
                $item['tipo_documento']
            );

            $this->writeText(
                $sheet,
                "B{$targetRow}",
                $item['documento']
            );

            $this->writeText(
                $sheet,
                "C{$targetRow}",
                '04'
            );

            if ($item['fecha_nacimiento']) {
                $this->writeDate(
                    $sheet,
                    "D{$targetRow}",
                    $item['fecha_nacimiento']
                );
            }

            $this->writeText(
                $sheet,
                "E{$targetRow}",
                $item['sexo']
            );

            $this->writeText(
                $sheet,
                "F{$targetRow}",
                '170'
            );

            $this->writeText(
                $sheet,
                "G{$targetRow}",
                '44430'
            );

            $this->writeText(
                $sheet,
                "I{$targetRow}",
                'NO'
            );

            $this->writeText(
                $sheet,
                "J{$targetRow}",
                '170'
            );

            $targetRow++;
        }
    }

    private function buildAc(
        Worksheet $sheet,
        array $rows
    ): void {
        $this->clearDataRows(
            $sheet,
            32,
            count($rows)
        );

        $targetRow = 2;

        foreach ($rows as $item) {
            /*
             * AC <- AC
             *
             * B  Código prestador          <- 444300063502
             * C  Documento paciente        <- AC!C
             * D  Fecha consulta            <- AC!K
             * E  Hora consulta             <- AC!K
             * G  CUPS consulta             <- primer token AC!L
             * H  Modalidad                 <- 01
             * I  Grupo servicios           <- 01
             * M  DX principal CIE10        <- AC!G
             * AA Tipo doc profesional      <- CC
             * AB Documento profesional     <- AC!M
             * AC Valor servicio            <- 0
             * AD Concepto recaudo          <- 5
             * AE Valor pago moderador      <- 0
             *
             * Los demás campos quedan vacíos por ahora.
             */
            $this->writeText(
                $sheet,
                "B{$targetRow}",
                self::PRESTADOR
            );

            $this->writeText(
                $sheet,
                "C{$targetRow}",
                $item['documento']
            );

            $this->writeDate(
                $sheet,
                "D{$targetRow}",
                $item['fecha_atencion']
            );

            $this->writeTime(
                $sheet,
                "E{$targetRow}",
                $item['fecha_atencion']
            );

            $this->writeText(
                $sheet,
                "G{$targetRow}",
                $item['cups']
            );

            $this->writeText(
                $sheet,
                "H{$targetRow}",
                '01'
            );

            $this->writeText(
                $sheet,
                "I{$targetRow}",
                '01'
            );

            $this->writeText(
                $sheet,
                "M{$targetRow}",
                $item['dx']
            );

            $this->writeText(
                $sheet,
                "AA{$targetRow}",
                'CC'
            );

            $this->writeText(
                $sheet,
                "AB{$targetRow}",
                $item['medico']
            );

            $sheet->setCellValue(
                "AC{$targetRow}",
                0
            );

            $this->writeText(
                $sheet,
                "AD{$targetRow}",
                '5'
            );

            $sheet->setCellValue(
                "AE{$targetRow}",
                0
            );

            $targetRow++;
        }
    }

    private function buildAp(
        Worksheet $sheet,
        array $rows
    ): void {
        $this->clearDataRows(
            $sheet,
            29,
            count($rows)
        );

        $targetRow = 2;

        foreach ($rows as $item) {
            /*
             * AP <- Hoja2
             *
             * B  Código prestador       <- 444300063502
             * C  Documento paciente     <- Hoja2!C
             * D  Fecha procedimiento    <- Hoja2!D
             * E  Hora procedimiento     <- Hoja2!D
             * H  Código procedimiento   <- Hoja2!A (sin modificar)
             * N  Tipo doc profesional   <- CC
             * O  Documento profesional  <- Hoja2!E
             * P  DX principal           <- Hoja2!F
             * Z  Valor servicio         <- 0
             * AA Concepto recaudo       <- 5
             * AB Valor pago moderador   <- 0
             *
             * Los demás campos quedan vacíos por ahora.
             */
            $this->writeText(
                $sheet,
                "B{$targetRow}",
                self::PRESTADOR
            );

            $this->writeText(
                $sheet,
                "C{$targetRow}",
                $item['documento']
            );

            $this->writeDate(
                $sheet,
                "D{$targetRow}",
                $item['fecha_atencion']
            );

            $this->writeTime(
                $sheet,
                "E{$targetRow}",
                $item['fecha_atencion']
            );

            $this->writeText(
                $sheet,
                "H{$targetRow}",
                $item['codigo']
            );

            $this->writeText(
                $sheet,
                "N{$targetRow}",
                'CC'
            );

            $this->writeText(
                $sheet,
                "O{$targetRow}",
                $item['medico']
            );

            $this->writeText(
                $sheet,
                "P{$targetRow}",
                $item['dx']
            );

            $sheet->setCellValue(
                "Z{$targetRow}",
                0
            );

            $this->writeText(
                $sheet,
                "AA{$targetRow}",
                '5'
            );

            $sheet->setCellValue(
                "AB{$targetRow}",
                0
            );

            $targetRow++;
        }
    }

    private function clearDataRows(
        Worksheet $sheet,
        int $columnCount,
        int $recordCount
    ): void {
        $maxRow = max(
            $sheet->getHighestDataRow(),
            $recordCount + 1,
            2
        );

        for ($row = 2; $row <= $maxRow; $row++) {
            for ($column = 1; $column <= $columnCount; $column++) {
                $sheet->getCell(
                    [$column, $row]
                )->setValue(null);
            }
        }
    }

    private function writeText(
        Worksheet $sheet,
        string $cell,
        mixed $value
    ): void {
        $sheet->setCellValueExplicit(
            $cell,
            (string) $value,
            DataType::TYPE_STRING
        );

        $sheet->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode('@');
    }

    private function writeDate(
        Worksheet $sheet,
        string $cell,
        DateTimeImmutable $date
    ): void {
        $sheet->setCellValue(
            $cell,
            ExcelDate::PHPToExcel($date)
        );

        $sheet->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode('dd/mm/yyyy');
    }

    private function writeTime(
        Worksheet $sheet,
        string $cell,
        DateTimeImmutable $date
    ): void {
        $seconds =
            ((int) $date->format('H')) * 3600
            + ((int) $date->format('i')) * 60
            + (int) $date->format('s');

        $sheet->setCellValue(
            $cell,
            $seconds / 86400
        );

        $sheet->getStyle($cell)
            ->getNumberFormat()
            ->setFormatCode('hh:mm');
    }

    private function extractFirstToken(
        string $value
    ): string {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $parts = preg_split(
            '/\s+/',
            $value
        );

        return trim(
            (string) ($parts[0] ?? '')
        );
    }

    private function parseExcelDate(
        mixed $value
    ): ?DateTimeImmutable {
        try {
            if ($value instanceof \DateTimeInterface) {
                return DateTimeImmutable::createFromInterface(
                    $value
                );
            }

            if (is_numeric($value)) {
                $dt =
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
                    );

                return DateTimeImmutable::createFromInterface(
                    $dt
                );
            }

            $text = trim(
                (string) $value
            );

            if ($text === '') {
                return null;
            }

            foreach (
                [
                    'Y-m-d H:i:s',
                    'Y-m-d H:i',
                    'd/m/Y H:i:s',
                    'd/m/Y H:i',
                    'Y-m-d',
                    'd/m/Y',
                ]
                as $format
            ) {
                $date = DateTimeImmutable::createFromFormat(
                    '!'.$format,
                    $text
                );

                if ($date instanceof DateTimeImmutable) {
                    return $date;
                }
            }
        } catch (Throwable) {
        }

        return null;
    }
}
