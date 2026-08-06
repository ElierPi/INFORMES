<?php

namespace App\Services\Sigires\Cronicos;

use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

class SigiresCronicosTxtGenerator
{
    /**
     * @param  array<int, array<string, mixed>>  $patients
     * @return array{txt_path: string, zip_path: string, file_name: string}
     */
    public function generate(
        array $patients,
        string $directory,
        string $providerCode,
        string $cutoffDate,
        string $procedure
    ): array {
        if ($patients === []) {
            throw new RuntimeException(
                'No existen pacientes para generar el archivo SIGIRES.'
            );
        }

        foreach ($patients as $patient) {
            if (! ($patient['build']['ready'] ?? false)) {
                throw new RuntimeException(
                    'No se puede generar el TXT porque existen campos obligatorios pendientes.'
                );
            }
        }

        $fieldCount = (int) config(
            'sigires_cronicos.profile.field_count',
            143
        );
        $delimiter = (string) config(
            'sigires_cronicos.profile.delimiter',
            "\t"
        );
        $lineEnding = (string) config(
            'sigires_cronicos.profile.line_ending',
            "\r\n"
        );
        $encoding = (string) config(
            'sigires_cronicos.profile.encoding',
            'Windows-1252'
        );

        $providerCode = preg_replace('/\D+/', '', $providerCode) ?? '';

        if (strlen($providerCode) !== 12) {
            throw new RuntimeException(
                'El código de habilitación debe contener exactamente 12 dígitos.'
            );
        }

        $cutoff = DateTimeImmutable::createFromFormat('!Y-m-d', $cutoffDate);

        if (! $cutoff instanceof DateTimeImmutable) {
            throw new RuntimeException(
                'La fecha de corte no tiene un formato válido.'
            );
        }

        $procedure = mb_strtoupper(
            preg_replace('/[^A-Z]/i', '', $procedure) ?? ''
        );

        if (! in_array(
            $procedure,
            ['PRECURSORAS', 'DIALISIS', 'TMND', 'NEFRO', 'TRASPLANTE'],
            true
        )) {
            throw new RuntimeException(
                'El procedimiento SIGIRES no es válido.'
            );
        }

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                'No fue posible crear la carpeta de salida SIGIRES.'
            );
        }

        $baseName = sprintf(
            '%s_%s_ERC_%s',
            $providerCode,
            $cutoff->format('dmY'),
            $procedure
        );
        $txtPath = rtrim($directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$baseName
            .'.txt';
        $zipPath = rtrim($directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$baseName
            .'.zip';

        $lines = [];

        foreach ($patients as $patient) {
            $values = $patient['build']['values'] ?? [];

            if (count($values) !== $fieldCount) {
                throw new RuntimeException(
                    "Un registro no contiene los {$fieldCount} campos esperados."
                );
            }

            $normalized = array_map(
                fn (mixed $value): string => $this->normalizeValue(
                    $value,
                    $delimiter
                ),
                $values
            );

            $lines[] = implode($delimiter, $normalized);
        }

        $content = implode($lineEnding, $lines);
        $encoded = mb_convert_encoding(
            $content,
            $encoding,
            'UTF-8'
        );

        if (file_put_contents($txtPath, $encoded) === false) {
            throw new RuntimeException(
                'No fue posible guardar el TXT SIGIRES.'
            );
        }

        $zip = new ZipArchive();
        $result = $zip->open(
            $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($result !== true) {
            throw new RuntimeException(
                'No fue posible crear el ZIP SIGIRES.'
            );
        }

        try {
            if (! $zip->addFile($txtPath, basename($txtPath))) {
                throw new RuntimeException(
                    'No fue posible incluir el TXT dentro del ZIP.'
                );
            }
        } finally {
            $zip->close();
        }

        return [
            'txt_path' => $txtPath,
            'zip_path' => $zipPath,
            'file_name' => $baseName,
        ];
    }

    private function normalizeValue(
        mixed $value,
        string $delimiter
    ): string {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        $value = str_replace(
            [$delimiter, "\r", "\n"],
            ' ',
            $value
        );
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
