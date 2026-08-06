<?php

namespace App\Services\Sigires\Cronicos;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

final class SigiresCronicosReviewedMatrixService
{
    public function __construct(
        private readonly SigiresCronicosValidator $validator,
        private readonly SigiresCronicosTxtGenerator $txtGenerator
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(
        string $matrixPath,
        string $cutoffDate,
        string $procedure
    ): array {
        try {
            $spreadsheet = IOFactory::load($matrixPath);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible abrir la matriz completada: '.$exception->getMessage(),
                previous: $exception
            );
        }

        try {
            $sheet = $spreadsheet->getSheetByName('ESTRUCTURA_SIGIRES');

            if ($sheet === null) {
                throw new RuntimeException(
                    'La matriz no contiene la hoja ESTRUCTURA_SIGIRES.'
                );
            }

            $fieldCount = (int) config(
                'sigires_cronicos.profile.field_count',
                143
            );
            $highestRow = $sheet->getHighestDataRow();
            $patients = [];
            $issuesByRow = [];
            $providerCodes = [];

            for ($row = 3; $row <= $highestRow; $row++) {
                $values = [];

                for ($column = 1; $column <= $fieldCount; $column++) {
                    $cell = $sheet->getCell([$column, $row]);
                    $definition = config(
                        'sigires_cronicos.fields.'.($column - 1),
                        []
                    );
                    $value = ($definition['type'] ?? null) === 'DT'
                        ? $cell->getFormattedValue()
                        : $cell->getValue();
                    $values[] = $this->normalizeCell($value);
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $issues = $this->validator->validate($values);

                if ($issues !== []) {
                    $issuesByRow[$row] = $issues;
                }

                $providerCode = preg_replace(
                    '/\D+/',
                    '',
                    (string) ($values[15] ?? '')
                ) ?? '';

                if ($providerCode !== '') {
                    $providerCodes[] = $providerCode;
                }

                $patients[] = [
                    'build' => [
                        'values' => $values,
                        'pending' => [],
                        'warnings' => [],
                        'ready' => $issues === [],
                    ],
                ];
            }

            if ($patients === []) {
                throw new RuntimeException(
                    'La hoja ESTRUCTURA_SIGIRES no contiene registros desde la fila 3.'
                );
            }

            if ($issuesByRow !== []) {
                throw new RuntimeException(
                    $this->formatIssues($issuesByRow)
                );
            }

            $providerCodes = array_values(array_unique(array_filter(
                $providerCodes,
                static fn (string $code): bool => strlen($code) === 12
            )));

            if (count($providerCodes) !== 1) {
                throw new RuntimeException(
                    'La matriz debe contener un único código de habilitación de 12 dígitos en el campo 16.'
                );
            }

            $directory = storage_path(
                'app/private/sigires-cronicos/generated/'.
                now()->format('Ymd_His').'_reviewed'
            );

            $generated = $this->txtGenerator->generate(
                $patients,
                $directory,
                $providerCodes[0],
                $cutoffDate,
                $procedure
            );

            return [
                ...$generated,
                'records_total' => count($patients),
                'provider_code' => $providerCodes[0],
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function normalizeCell(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return trim((string) $value);
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, array<int, array<string, mixed>>> $issuesByRow */
    private function formatIssues(array $issuesByRow): string
    {
        $messages = [];
        $total = 0;

        foreach ($issuesByRow as $row => $issues) {
            foreach ($issues as $issue) {
                $total++;

                if (count($messages) < 12) {
                    $messages[] = sprintf(
                        'Fila %d, %s: %s',
                        $row,
                        (string) ($issue['field'] ?? $issue['code'] ?? 'campo'),
                        (string) ($issue['message'] ?? 'valor inválido')
                    );
                }
            }
        }

        $suffix = $total > count($messages)
            ? ' Se muestran '.count($messages).' de '.$total.' novedades.'
            : '';

        return 'La matriz completada todavía contiene errores: '.
            implode(' | ', $messages).$suffix;
    }
}
