<?php

namespace App\Services\Reports\Text;

use RuntimeException;

class DelimitedTextReader
{
    /**
     * Lee un archivo TXT delimitado y lo convierte en registros.
     *
     * @return array{
     *     headers: array<int, string>,
     *     records: array<int, array<string, mixed>>,
     *     total_records: int,
     *     delimiter: string,
     *     encoding: string
     * }
     */
    public function read(
        string $path,
        string $delimiter = "\t",
        string $sourceEncoding = 'Windows-1252',
        bool $hasHeader = true
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException(
                "No se encontró el archivo TXT: {$path}"
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(
                'No fue posible leer el archivo TXT.'
            );
        }

        $contents = $this->convertToUtf8(
            contents: $contents,
            sourceEncoding: $sourceEncoding
        );

        $contents = $this->removeBom($contents);

        $lines = preg_split('/\r\n|\r|\n/', $contents);

        if (! is_array($lines)) {
            throw new RuntimeException(
                'No fue posible separar las líneas del archivo.'
            );
        }

        $lines = array_values(array_filter(
            $lines,
            static fn (string $line): bool => trim($line) !== ''
        ));

        if ($lines === []) {
            throw new RuntimeException(
                'El archivo TXT se encuentra vacío.'
            );
        }

        $rows = array_map(
            fn (string $line): array => str_getcsv(
                string: $line,
                separator: $delimiter,
                enclosure: '"',
                escape: '\\'
            ),
            $lines
        );

        $headers = [];

        if ($hasHeader) {
            $headers = array_map(
                fn ($header): string => trim((string) $header),
                array_shift($rows)
            );
        } else {
            $columnCount = count($rows[0] ?? []);

            for ($column = 1; $column <= $columnCount; $column++) {
                $headers[] = "Campo {$column}";
            }
        }

        $records = [];

        foreach ($rows as $index => $row) {
            $normalizedRow = $this->normalizeRow(
                row: $row,
                expectedColumns: count($headers)
            );

            $records[] = [
                'row_number' => $index + ($hasHeader ? 2 : 1),
                'values' => $normalizedRow,
                'data' => array_combine(
                    $headers,
                    $normalizedRow
                ) ?: [],
            ];
        }

        return [
            'headers' => $headers,
            'records' => $records,
            'total_records' => count($records),
            'delimiter' => $delimiter,
            'encoding' => $sourceEncoding,
        ];
    }

    private function convertToUtf8(
        string $contents,
        string $sourceEncoding
    ): string {
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        $converted = mb_convert_encoding(
            $contents,
            'UTF-8',
            $sourceEncoding
        );

        if ($converted === '') {
            throw new RuntimeException(
                'No fue posible convertir el archivo a UTF-8.'
            );
        }

        return $converted;
    }

    private function removeBom(string $contents): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
    }

    /**
     * @param array<int, mixed> $row
     * @return array<int, string>
     */
    private function normalizeRow(
        array $row,
        int $expectedColumns
    ): array {
        $row = array_map(
            static fn ($value): string => trim((string) $value),
            $row
        );

        if (count($row) < $expectedColumns) {
            $row = array_pad($row, $expectedColumns, '');
        }

        return array_slice($row, 0, $expectedColumns);
    }
}