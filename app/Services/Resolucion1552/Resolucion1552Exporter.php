<?php

namespace App\Services\Resolucion1552;

use App\Data\Reports\RecordValidationResult;
use App\Services\Importing\ImportPipeline;
use App\Services\Reports\Engine\DelimitedTextGenerator;
use App\Services\Reports\Engine\TextEncoder;
use App\Services\Reports\Engine\ZipReportGenerator;
use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1552Exporter
{
    public function __construct(
        private readonly ImportPipeline $pipeline,
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

        $delimiter = (string) ($fileConfig['delimiter'] ?? "\t");
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

        $date = now()->format('dmY');
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
