<?php

namespace App\Services\Sigires\Cronicos;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class SigiresCronicosReviewExporter
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function export(array $result, string $outputPath): string
    {
        $directory = dirname($outputPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de salida de la matriz.'
            );
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle('Matriz de revisión SIGIRES - PRECURSORAS')
            ->setSubject('Pacientes crónicos HTA, DM y ERC')
            ->setCreator(config('app.name'));

        try {
            $this->buildSummary($spreadsheet, $result);
            $this->buildPatients($spreadsheet, $result);
            $this->buildStructure($spreadsheet, $result);
            $this->buildPending($spreadsheet, $result);
            $this->buildValidation($spreadsheet, $result);
            $this->buildEvidence($spreadsheet, $result);
            $this->buildDictionary($spreadsheet);

            $spreadsheet->setActiveSheetIndex(0);

            (new Xlsx($spreadsheet))->save($outputPath);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible generar la matriz de revisión: '.
                $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildSummary(
        Spreadsheet $spreadsheet,
        array $result
    ): void {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RESUMEN');

        $summary = $result['summary'] ?? [];
        $rows = [
            ['MATRIZ DE REVISIÓN SIGIRES - ERC PRECURSORAS', null],
            ['Concepto', 'Resultado'],
            ['Fecha de corte', $summary['cutoff_date'] ?? null],
            ['Procedimiento', $summary['procedure'] ?? 'PRECURSORAS'],
            ['Código IPS', $summary['provider_code'] ?? null],
            ['Código EAPB', $summary['eapb_code'] ?? null],
            ['Historias clínicas procesadas', $summary['histories_total'] ?? 0],
            ['Pacientes encontrados en la base', $summary['matched_total'] ?? 0],
            ['Pacientes sin coincidencia', $summary['unmatched_total'] ?? 0],
            ['Pacientes listos para TXT', $summary['ready_total'] ?? 0],
            ['Pacientes con pendientes', $summary['pending_total'] ?? 0],
            ['Campos pendientes', $summary['pending_fields_total'] ?? 0],
            ['Advertencias', count($result['warnings'] ?? [])],
            [],
            ['IMPORTANTE', 'Esta matriz es para revisión. El TXT solo se genera cuando todos los campos obligatorios estén completos y validados.'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $sheet->mergeCells('A1:B1');
        $this->styleTitle($sheet, 'A1:B1');
        $this->styleHeader($sheet, 'A2:B2');
        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(85);
        $sheet->getStyle('A1:B20')->getAlignment()->setWrapText(true);
        $sheet->freezePane('A3');

        $row = 17;

        if (($result['warnings'] ?? []) !== []) {
            $sheet->setCellValue("A{$row}", 'ADVERTENCIAS DEL PROCESO');
            $sheet->mergeCells("A{$row}:B{$row}");
            $this->styleHeader($sheet, "A{$row}:B{$row}");
            $row++;

            foreach ($result['warnings'] as $warning) {
                $sheet->setCellValue("A{$row}", 'Advertencia');
                $sheet->setCellValue("B{$row}", $warning);
                $row++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildPatients(
        Spreadsheet $spreadsheet,
        array $result
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('PACIENTES');

        $headers = [
            'Documento',
            'Tipo base',
            'Tipo historia',
            'Paciente',
            'IPS',
            'Código IPS',
            'Archivo HC',
            'Fecha atención',
            'HTA base',
            'HTA HC',
            'DM base',
            'DM HC',
            'ERC base',
            'ERC HC',
            'Estadio ERC',
            'Peso',
            'Talla',
            'TAS',
            'TAD',
            'Creatinina',
            'Fecha creatinina',
            'TFG',
            'Pendientes',
            'Errores tipo A',
            'Advertencias',
            'Estado',
        ];

        $sheet->fromArray([$headers], null, 'A1');
        $this->styleHeader(
            $sheet,
            'A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1'
        );

        $row = 2;

        foreach ($result['patients'] ?? [] as $patient) {
            $base = $patient['base'] ?? [];
            $history = $patient['history'] ?? [];
            $build = $patient['build'] ?? [];
            $labs = $history['labs'] ?? [];
            $name = trim(implode(' ', array_filter([
                $base['first_name'] ?? null,
                $base['second_name'] ?? null,
                $base['first_surname'] ?? null,
                $base['second_surname'] ?? null,
            ])));

            $sheet->fromArray([[
                $history['document_number'] ?? $base['document_number'] ?? null,
                $base['document_type'] ?? null,
                $history['document_type_history']
                    ?? $history['document_type_file']
                    ?? null,
                $name,
                $base['provider_name'] ?? null,
                $base['provider_code'] ?? null,
                $history['file_name'] ?? null,
                $history['attention_date'] ?? null,
                $this->flag($base['hta'] ?? null),
                $this->flag($history['hta'] ?? null),
                $this->flag($base['dm'] ?? null),
                $this->flag($history['dm'] ?? null),
                $this->flag($base['erc'] ?? null),
                $this->flag($history['erc'] ?? null),
                $history['erc_stage'] ?? null,
                $history['weight'] ?? null,
                $history['height'] ?? null,
                $history['pas'] ?? null,
                $history['pad'] ?? null,
                $labs['creatinine']['value'] ?? null,
                $labs['creatinine']['date'] ?? null,
                $labs['tfg']['value'] ?? null,
                count($build['pending'] ?? []),
                count($patient['validation_issues'] ?? []),
                implode(' | ', $build['warnings'] ?? []),
                ($build['ready'] ?? false) ? 'LISTO' : 'REVISAR',
            ]], null, "A{$row}");

            $row++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth(16);
        }

        foreach (['D', 'E', 'G', 'Y'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(30);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildStructure(
        Spreadsheet $spreadsheet,
        array $result
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('ESTRUCTURA_SIGIRES');
        $fields = config('sigires_cronicos.fields', []);

        $codes = [];
        $descriptions = [];

        foreach ($fields as $definition) {
            $codes[] = $definition['code'] ?? '';
            $descriptions[] = $definition['description'] ?? '';
        }

        $sheet->fromArray([$codes, $descriptions], null, 'A1');

        $row = 3;

        foreach ($result['patients'] ?? [] as $patient) {
            $sheet->fromArray([
                $patient['build']['values'] ?? [],
            ], null, "A{$row}");
            $row++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($fields));
        $lastRow = max(3, $row - 1);
        $this->styleHeader($sheet, "A1:{$lastColumn}1");
        $sheet->getStyle("A2:{$lastColumn}2")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('D9EAF7');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('G3');
        $sheet->setAutoFilter("A2:{$lastColumn}{$lastRow}");

        for ($column = 1; $column <= count($fields); $column++) {
            $sheet->getColumnDimension(
                Coordinate::stringFromColumnIndex($column)
            )->setWidth(16);
        }

        $sheet->getRowDimension(2)->setRowHeight(90);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildPending(
        Spreadsheet $spreadsheet,
        array $result
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('PENDIENTES');

        $headers = [
            'Documento',
            'Posición',
            'Código',
            'Campo',
            'Motivo',
            'Archivo HC',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $this->styleHeader($sheet, 'A1:F1');

        $row = 2;

        foreach ($result['patients'] ?? [] as $patient) {
            $document = $patient['history']['document_number']
                ?? $patient['base']['document_number']
                ?? null;
            $fileName = $patient['history']['file_name'] ?? null;

            foreach ($patient['build']['pending'] ?? [] as $pending) {
                $sheet->fromArray([[
                    $document,
                    $pending['position'] ?? null,
                    $pending['code'] ?? null,
                    $pending['field'] ?? null,
                    $pending['reason'] ?? null,
                    $fileName,
                ]], null, "A{$row}");
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:F{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(42);
        $sheet->getColumnDimension('E')->setWidth(65);
        $sheet->getColumnDimension('F')->setWidth(48);
        $sheet->getStyle("A1:F{$lastRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildValidation(
        Spreadsheet $spreadsheet,
        array $result
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('VALIDACION_TIPO_A');

        $headers = [
            'Documento',
            'Posición',
            'Código',
            'Campo',
            'Tipo de error',
            'Mensaje',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $this->styleHeader($sheet, 'A1:F1');

        $row = 2;

        foreach ($result['patients'] ?? [] as $patient) {
            $document = $patient['history']['document_number']
                ?? $patient['base']['document_number']
                ?? null;

            foreach ($patient['validation_issues'] ?? [] as $issue) {
                $sheet->fromArray([[
                    $document,
                    $issue['position'] ?? null,
                    $issue['code'] ?? null,
                    $issue['field'] ?? null,
                    $issue['type'] ?? null,
                    $issue['message'] ?? null,
                ]], null, "A{$row}");
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:F{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(42);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(65);
        $sheet->getStyle("A1:F{$lastRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function buildEvidence(
        Spreadsheet $spreadsheet,
        array $result
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('EVIDENCIAS');

        $headers = [
            'Documento',
            'Variable',
            'Valor',
            'Fecha',
            'Evidencia extraída',
            'Archivo HC',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $this->styleHeader($sheet, 'A1:F1');

        $row = 2;

        foreach ($result['patients'] ?? [] as $patient) {
            $history = $patient['history'] ?? [];
            $document = $history['document_number'] ?? null;

            foreach ($history['labs'] ?? [] as $key => $lab) {
                if (
                    ($lab['value'] ?? null) === null
                    && ($lab['evidence'] ?? null) === null
                ) {
                    continue;
                }

                $sheet->fromArray([[
                    $document,
                    $key,
                    $lab['value'] ?? null,
                    $lab['date'] ?? null,
                    $lab['evidence'] ?? null,
                    $history['file_name'] ?? null,
                ]], null, "A{$row}");
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:F{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(90);
        $sheet->getColumnDimension('F')->setWidth(48);
        $sheet->getStyle("A1:F{$lastRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    private function buildDictionary(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('DICCIONARIO');

        $headers = [
            'Posición',
            'Código',
            'Descripción',
            'Tipo',
            'Longitud mínima',
            'Longitud máxima',
            'Valores permitidos',
            'Obligatorio',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $this->styleHeader($sheet, 'A1:H1');

        $row = 2;

        foreach (config('sigires_cronicos.fields', []) as $definition) {
            $sheet->fromArray([[
                $definition['number'] ?? null,
                $definition['code'] ?? null,
                $definition['description'] ?? null,
                $definition['type'] ?? null,
                $definition['min'] ?? null,
                $definition['max'] ?? null,
                $definition['allowed'] ?? null,
                ($definition['required'] ?? false) ? 'S' : 'N',
            ]], null, "A{$row}");
            $row++;
        }

        $lastRow = max(2, $row - 1);
        $sheet->setAutoFilter("A1:H{$lastRow}");
        $sheet->freezePane('A2');
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(85);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(32);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getStyle("A1:H{$lastRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }

    private function styleTitle(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $range
    ): void {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('0F4C5C');
        $sheet->getStyle($range)->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('FFFFFF');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);
    }

    private function styleHeader(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $range
    ): void {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('1F6F8B');
        $sheet->getStyle($range)->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('FFFFFF');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function flag(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        $value = mb_strtoupper(trim((string) $value));

        return in_array($value, ['1', 'SI', 'S', 'X', 'TRUE'], true)
            ? 'Sí'
            : 'No';
    }
}
