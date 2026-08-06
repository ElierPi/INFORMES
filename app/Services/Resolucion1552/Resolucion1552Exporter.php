<?php

namespace App\Services\Resolucion1552;

use App\Data\Reports\RecordValidationResult;
use App\Services\Importing\ImportPipeline;
use App\Services\Reports\Engine\DelimitedTextGenerator;
use App\Services\Reports\Engine\TextEncoder;
use App\Services\Reports\Engine\ZipReportGenerator;
use DateTimeImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1552Exporter
{
    public function __construct(
        private readonly ImportPipeline $pipeline,
        private readonly Resolucion1552DusakawiExcelReader $dusakawiExcelReader,
        private readonly Resolucion1552DatasetTransformer $transformer,
        private readonly Resolucion1552Validator $validator,
        private readonly DelimitedTextGenerator $textGenerator,
        private readonly TextEncoder $textEncoder,
        private readonly ZipReportGenerator $zipGenerator,
    ) {
    }

    /**
     * Ejecuta el flujo completo:
     * Excel -> importación -> transformación -> validación -> TXT -> ZIP.
     *
     * @return array<string, mixed>
     */
    public function exportFromExcel(
        string $path,
        string $originalFilename,
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el Excel que será procesado.');
        }

        /*
         * DUSAKAWI entrega el formato oficial sin fila de encabezados:
         * la primera fila es el registro tipo 1 y las demás son tipo 2.
         * Este formato debe detectarse antes de ejecutar el importador universal.
         */
        $dusakawiDataset = $this->dusakawiExcelReader->read($path);

        if (is_array($dusakawiDataset)) {
            return $this->exportDusakawiDataset($dusakawiDataset);
        }

        $pipelineResult = $this->pipeline->process(
            path: $path,
            originalFilename: $originalFilename,
            reportType: 'resolucion_1552',
            fieldDefinitions: config('report_importer.aliases', []),
        );

        $transformation = $this->transformer->transform(
            $pipelineResult['dataset']
        );

        if (($transformation['errors'] ?? []) !== []) {
            throw new RuntimeException(
                'La transformación contiene errores y no se puede generar el archivo.'
            );
        }

        $validation = $this->validator->validateRecords(
            $transformation['records'] ?? []
        );

        if (! ($validation['valid'] ?? false)) {
            return [
                'success' => false,
                'stage' => 'validation',
                'history' => $pipelineResult['history'],
                'transformation' => $transformation,
                'validation' => $validation,
                'txt_path' => null,
                'zip_path' => null,
            ];
        }

        $normalizedRecords = array_map(
            static fn (RecordValidationResult $result): array =>
                $result->normalizedData,
            $validation['records']
        );

        if ($normalizedRecords === []) {
            throw new RuntimeException('No existen registros válidos para exportar.');
        }

        $providerCode = trim((string) ($normalizedRecords[0]['provider_code'] ?? ''));

        if ($providerCode === '') {
            throw new RuntimeException('No fue posible determinar el código del prestador.');
        }

        $this->assertSingleProvider($normalizedRecords, $providerCode);

        $fileConfig = config('resolucion1552.file', []);
        $columns = config('resolucion1552.columns', []);

        $delimiter = (string) ($fileConfig['delimiter'] ?? '|');
        $lineEnding = (string) ($fileConfig['line_ending'] ?? "\r\n");
        $includeHeader = (bool) (
            $fileConfig['include_header']
            ?? $fileConfig['has_header']
            ?? false
        );
        $quoteFields = (bool) ($fileConfig['quote_fields'] ?? false);
        $encoding = (string) ($fileConfig['encoding'] ?? 'Windows-1252');
        $bom = (bool) ($fileConfig['bom'] ?? false);

        $utf8Content = $this->textGenerator->generate(
            records: $normalizedRecords,
            columns: $columns,
            delimiter: $delimiter,
            lineEnding: $lineEnding,
            includeHeader: $includeHeader,
            quoteFields: $quoteFields,
        );

        $encodedContent = $this->textEncoder->encode(
            content: $utf8Content,
            targetEncoding: $encoding,
            sourceEncoding: 'UTF-8',
            bom: $bom,
        );

        $date = now()->format('Ymd');
        $baseName = "RESOLUCION_1552_{$providerCode}_{$date}";
        $relativeDirectory = 'private/reports/resolucion1552/' . Str::uuid();
        $directory = storage_path('app/' . $relativeDirectory);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $txtPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.txt';
        $zipPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.zip';

        if (file_put_contents($txtPath, $encodedContent) === false) {
            throw new RuntimeException('No fue posible guardar el archivo TXT.');
        }

        $this->zipGenerator->generate(
            sourceFile: $txtPath,
            zipPath: $zipPath,
            entryName: basename($txtPath),
        );

        return [
            'success' => true,
            'stage' => 'completed',
            'history' => $pipelineResult['history'],
            'dataset' => $pipelineResult['dataset'],
            'transformation' => $transformation,
            'validation' => $validation,
            'records_count' => count($normalizedRecords),
            'provider_code' => $providerCode,
            'encoding' => $encoding,
            'delimiter' => $delimiter,
            'txt_name' => basename($txtPath),
            'txt_path' => $txtPath,
            'zip_name' => basename($zipPath),
            'zip_path' => $zipPath,
        ];
    }

    /**
     * Exporta el formato DUSAKAWI que ya viene organizado en registro
     * tipo 1 de control y registros tipo 2 de detalle.
     *
     * @param array<string, mixed> $dataset
     * @return array<string, mixed>
     */
    private function exportDusakawiDataset(array $dataset): array
    {
        $errors = is_array($dataset['errors'] ?? null)
            ? $dataset['errors']
            : [];
        $warnings = is_array($dataset['warnings'] ?? null)
            ? $dataset['warnings']
            : [];
        $rows = is_array($dataset['rows'] ?? null)
            ? $dataset['rows']
            : [];
        $control = is_array($dataset['control'] ?? null)
            ? $dataset['control']
            : [];
        $recordsCount = count($rows);

        $validation = [
            'valid' => $errors === [],
            'statistics' => [
                'records_count' => $recordsCount,
                'valid_records_count' => $errors === [] ? $recordsCount : 0,
                'invalid_records_count' => $errors === [] ? 0 : $recordsCount,
                'errors_count' => count($errors),
                'warnings_count' => count($warnings),
            ],
            'records' => [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'stage' => 'validation',
                'history' => [
                    'Se detectó el formato DUSAKAWI sin encabezados.',
                    'Se validaron el registro tipo 1 y los registros tipo 2.',
                ],
                'dataset' => $dataset,
                'transformation' => [
                    'format' => 'dusakawi_preformatted',
                    'sheet_name' => $dataset['sheet_name'] ?? null,
                    'records_count' => $recordsCount,
                ],
                'validation' => $validation,
                'txt_path' => null,
                'zip_path' => null,
            ];
        }

        if ($recordsCount === 0 || count($control) !== 6) {
            throw new RuntimeException(
                'El formato DUSAKAWI no contiene una línea de control y registros de detalle válidos.'
            );
        }

        $providerCode = trim((string) ($dataset['provider_code'] ?? ''));
        if ($providerCode === '') {
            throw new RuntimeException(
                'No fue posible determinar el código de habilitación del registro tipo 1.'
            );
        }

        $fileConfig = config('resolucion1552.file', []);
        $delimiter = (string) ($fileConfig['delimiter'] ?? '|');
        $lineEnding = (string) ($fileConfig['line_ending'] ?? "\r\n");
        $encoding = (string) ($fileConfig['encoding'] ?? 'Windows-1252');
        $bom = (bool) ($fileConfig['bom'] ?? false);

        $lines = [];
        $lines[] = $this->serializeDusakawiRow($control, $delimiter);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines[] = $this->serializeDusakawiRow($row, $delimiter);
        }

        $utf8Content = implode($lineEnding, $lines);

        $encodedContent = $this->textEncoder->encode(
            content: $utf8Content,
            targetEncoding: $encoding,
            sourceEncoding: 'UTF-8',
            bom: $bom,
        );

        $date = $this->filenameDateFromControl($control[4] ?? null);
        $baseName = "RESOLUCION_1552_{$providerCode}_{$date}";
        $relativeDirectory = 'private/reports/resolucion1552/' . Str::uuid();
        $directory = storage_path('app/' . $relativeDirectory);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $txtPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.txt';
        $zipPath = $directory . DIRECTORY_SEPARATOR . $baseName . '.zip';

        if (file_put_contents($txtPath, $encodedContent) === false) {
            throw new RuntimeException('No fue posible guardar el archivo TXT.');
        }

        $this->zipGenerator->generate(
            sourceFile: $txtPath,
            zipPath: $zipPath,
            entryName: basename($txtPath),
        );

        return [
            'success' => true,
            'stage' => 'completed',
            'history' => [
                'Se detectó el formato DUSAKAWI sin encabezados.',
                'Se normalizaron fechas, consecutivos y cantidad de registros.',
                'Se generaron el TXT y el ZIP con delimitador pipe (|).',
            ],
            'dataset' => $dataset,
            'transformation' => [
                'format' => 'dusakawi_preformatted',
                'sheet_name' => $dataset['sheet_name'] ?? null,
                'records_count' => $recordsCount,
            ],
            'validation' => $validation,
            'records_count' => $recordsCount,
            'provider_code' => $providerCode,
            'encoding' => $encoding,
            'delimiter' => $delimiter,
            'txt_name' => basename($txtPath),
            'txt_path' => $txtPath,
            'zip_name' => basename($zipPath),
            'zip_path' => $zipPath,
        ];
    }

    /** @param array<int, mixed> $values */
    private function serializeDusakawiRow(array $values, string $delimiter): string
    {
        return implode(
            $delimiter,
            array_map(
                static function (mixed $value) use ($delimiter): string {
                    $text = trim((string) $value);
                    $text = str_replace(["\r", "\n", "\t"], ' ', $text);

                    if ($delimiter !== "\t") {
                        $text = str_replace($delimiter, ' ', $text);
                    }

                    return preg_replace('/\s{2,}/u', ' ', $text) ?? $text;
                },
                $values
            )
        );
    }

    private function filenameDateFromControl(mixed $value): string
    {
        $text = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if ($date instanceof DateTimeImmutable && ! $hasErrors) {
            return $date->format('Ymd');
        }

        throw new RuntimeException(
            'La fecha final del registro de control no permite construir el nombre oficial del archivo.'
        );
    }

    /** @param array<int, array<string, mixed>> $records */
    private function assertSingleProvider(array $records, string $providerCode): void
    {
        foreach ($records as $record) {
            $current = trim((string) ($record['provider_code'] ?? ''));

            if ($current !== $providerCode) {
                throw new RuntimeException(
                    'El archivo contiene más de un código de prestador. Debe generar un reporte por prestador.'
                );
            }
        }
    }
}
