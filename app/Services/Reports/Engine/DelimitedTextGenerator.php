<?php

namespace App\Services\Reports\Engine;

use RuntimeException;

final class DelimitedTextGenerator
{
    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<int, array<string, mixed>> $columns
     */
    public function generate(
        array $records,
        array $columns,
        string $delimiter,
        string $lineEnding = "\r\n",
        bool $includeHeader = false,
        bool $quoteFields = false,
    ): string {
        if ($columns === []) {
            throw new RuntimeException('No se configuraron columnas para generar el TXT.');
        }

        usort(
            $columns,
            static fn (array $a, array $b): int =>
                ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0))
        );

        $lines = [];

        if ($includeHeader) {
            $headers = array_map(
                fn (array $column): string => $this->escape(
                    (string) ($column['header'] ?? $column['name'] ?? $column['field'] ?? ''),
                    $delimiter,
                    $quoteFields,
                ),
                $columns
            );

            $lines[] = implode($delimiter, $headers);
        }

        foreach ($records as $recordIndex => $record) {
            if (! is_array($record)) {
                throw new RuntimeException(
                    sprintf('El registro %d no tiene una estructura válida.', $recordIndex + 1)
                );
            }

            $values = [];

            foreach ($columns as $column) {
                $field = (string) ($column['field'] ?? '');
                $value = $record[$field] ?? $column['default'] ?? '';

                $values[] = $this->escape(
                    $this->stringify($value),
                    $delimiter,
                    $quoteFields,
                );
            }

            $lines[] = implode($delimiter, $values);
        }

        return implode($lineEnding, $lines) . $lineEnding;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function escape(
        string $value,
        string $delimiter,
        bool $quoteFields,
    ): string {
        $value = str_replace(["\r", "\n"], ' ', $value);

        if (! $quoteFields) {
            if ($delimiter !== '' && str_contains($value, $delimiter)) {
                throw new RuntimeException(
                    sprintf('El valor "%s" contiene el delimitador configurado.', $value)
                );
            }

            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
