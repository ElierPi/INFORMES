<?php

namespace App\Services\Resolucion1604\Dusakawi;

use DateTimeImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1604DusakawiExporter
{
    public const CONTRACT = 'ASE-44430-2026-52';
    public const ENABLEMENT_CODE = '444300063502';
    public const EPSI_CODE = 'EPSI01';

    public function __construct(
        private readonly Resolucion1604DusakawiExcelReader $reader,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(string $path, string $period): array
    {
        $periodDate = DateTimeImmutable::createFromFormat('!Y-m', $period);
        if (! $periodDate instanceof DateTimeImmutable) {
            throw new RuntimeException('Selecciona un período válido.');
        }

        $start = $periodDate->modify('first day of this month');
        $end = $periodDate->modify('last day of this month');
        $dataset = $this->reader->read($path);
        $records = $dataset['records'];
        $errors = $dataset['errors'];
        $warnings = $dataset['warnings'];

        $invalidRows = array_values(array_unique(array_filter(array_map(
            static fn (array $item): mixed => $item['source_row'] ?? null,
            $errors
        ))));

        $statistics = [
            'records_count' => count($records),
            'valid_records_count' => max(0, count($records) - count($invalidRows)),
            'invalid_records_count' => count($invalidRows),
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
        ];

        if ($errors !== []) {
            return [
                'success' => false,
                'validation' => compact('statistics', 'errors', 'warnings'),
                'sheet_name' => $dataset['sheet_name'],
                'header_row' => $dataset['header_row'],
            ];
        }

        $control = implode('|', [
            '1',
            self::CONTRACT,
            self::ENABLEMENT_CODE,
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            (string) count($records),
        ]);

        $detailLines = array_map(
            static fn (array $row): string => implode('|', $row['values']),
            $records
        );

        // El TXT real de junio está en UTF-8, con CRLF y sin encabezado.
        $content = $control."\r\n".implode("\r\n", $detailLines);

        $directory = storage_path('app/private/reports/resolucion1604-dusakawi/'.Str::uuid());
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear la carpeta de salida.');
        }

        $fileName = sprintf('1604_%s_%s.txt', self::EPSI_CODE, $end->format('Ymd'));
        $txtPath = $directory.DIRECTORY_SEPARATOR.$fileName;
        if (file_put_contents($txtPath, $content) === false) {
            throw new RuntimeException('No fue posible guardar el TXT 1604 de DUSAKAWI.');
        }

        return [
            'success' => true,
            'validation' => compact('statistics', 'errors', 'warnings'),
            'sheet_name' => $dataset['sheet_name'],
            'header_row' => $dataset['header_row'],
            'txt_path' => $txtPath,
            'txt_name' => $fileName,
            'control_line' => $control,
            'delimiter' => '|',
            'encoding' => 'UTF-8',
            'detail_field_count' => Resolucion1604DusakawiExcelReader::DETAIL_FIELD_COUNT,
        ];
    }
}
