<?php

namespace App\Services\Resolucion1552;

use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1552FamiliarCorrectionService
{
    public function __construct(
        private readonly Resolucion1552FamiliarErrorParser $parser,
    ) {
    }

    /** @return array<string, mixed> */
    public function correct(string $reportPath, string $errorsPath, ?string $originalReportName = null): array
    {
        $reportContent = $this->decodeFile($reportPath);
        $errorContent = $this->decodeFile($errorsPath);
        $lines = preg_split('/\R/u', $reportContent) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
        $parsedErrors = $this->parser->parse($errorContent);

        if ($lines === []) {
            throw new RuntimeException('El TXT del informe está vacío.');
        }

        if ($parsedErrors === []) {
            throw new RuntimeException('No se encontraron mensajes de error reconocibles.');
        }

        $remove = [];
        $updates = [];
        $manual = [];
        $audit = [];

        foreach ($parsedErrors as $error) {
            $lineNumber = $error['line'];

            if ($lineNumber < 1 || $lineNumber > count($lines)) {
                $manual[] = [
                    'line' => $lineNumber,
                    'message' => $error['message'],
                    'reason' => 'La línea indicada no existe en el TXT cargado.',
                ];
                continue;
            }

            $normalizedMessage = Str::of($error['message'])->lower()->ascii()->toString();
            $line = $lines[$lineNumber - 1];
            $fields = explode(';', $line);

            $isDuplicate = str_contains($normalizedMessage, 'ya se encuentra cargada')
                || str_contains($normalizedMessage, 'repetida en el archivo');

            if ($isDuplicate) {
                $firstOccurrence = array_search($line, $lines, true);
                $internalDuplicate = $firstOccurrence !== false && ($firstOccurrence + 1) < $lineNumber;

                $remove[$lineNumber] = true;
                $audit[] = [
                    'line' => $lineNumber,
                    'document_type' => $fields[4] ?? '',
                    'document_number' => $fields[5] ?? '',
                    'cups' => $fields[7] ?? '',
                    'appointment_date' => $fields[11] ?? '',
                    'internal_duplicate' => $internalDuplicate,
                    'first_occurrence' => $internalDuplicate ? $firstOccurrence + 1 : null,
                    'previous_value' => null,
                    'new_value' => null,
                    'action' => 'Línea excluida del nuevo TXT',
                    'message' => $error['message'],
                ];
                continue;
            }

            $isMissingAffiliate = str_contains($normalizedMessage, 'afiliado')
                && str_contains($normalizedMessage, 'no existe en la base de datos');

            if ($isMissingAffiliate) {
                if (count($fields) !== 14) {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' => 'La línea no tiene los 14 campos esperados y no es seguro cambiar el tipo de documento.',
                    ];
                    continue;
                }

                $currentType = strtoupper(trim((string) ($fields[4] ?? '')));
                $nextType = match ($currentType) {
                    'RC' => 'TI',
                    'TI' => 'CC',
                    default => null,
                };

                if ($nextType === null) {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' => "El tipo de documento {$currentType} no tiene una conversión automática definida.",
                    ];
                    continue;
                }

                $fields[4] = $nextType;
                $updates[$lineNumber] = implode(';', $fields);
                $audit[] = [
                    'line' => $lineNumber,
                    'document_type' => $nextType,
                    'document_number' => $fields[5] ?? '',
                    'cups' => $fields[7] ?? '',
                    'appointment_date' => $fields[11] ?? '',
                    'internal_duplicate' => false,
                    'first_occurrence' => null,
                    'previous_value' => $currentType,
                    'new_value' => $nextType,
                    'action' => "Tipo de documento cambiado de {$currentType} a {$nextType}",
                    'message' => $error['message'],
                ];
                continue;
            }

            $manual[] = [
                'line' => $lineNumber,
                'message' => $error['message'],
                'reason' => 'Este tipo de error todavía requiere una regla específica.',
            ];
        }

        $correctedLines = [];
        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (isset($remove[$lineNumber])) {
                continue;
            }

            $correctedLines[] = $updates[$lineNumber] ?? $line;
        }

        $directory = storage_path('app/private/reports/resolucion1552-familiar-correccion/' . Str::uuid());
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de salida.');
        }

        $outputName = $this->resolveOutputName($originalReportName, $reportPath);
        $outputPath = $directory . DIRECTORY_SEPARATOR . $outputName;
        $content = implode("\r\n", $correctedLines);
        $encoded = mb_convert_encoding($content, 'Windows-1252', 'UTF-8');

        if (file_put_contents($outputPath, $encoded) === false) {
            throw new RuntimeException('No fue posible guardar el TXT corregido.');
        }

        return [
            'success' => $manual === [],
            'output_path' => $outputPath,
            'output_name' => $outputName,
            'original_records' => count($lines),
            'corrected_records' => count($correctedLines),
            'removed_records' => count($remove),
            'updated_records' => count($updates),
            'parsed_errors' => count($parsedErrors),
            'automatic_corrections' => count($audit),
            'manual_errors' => $manual,
            'audit' => $audit,
        ];
    }

    private function resolveOutputName(?string $originalReportName, string $reportPath): string
    {
        $candidate = trim((string) $originalReportName);
        $candidate = $candidate !== '' ? basename($candidate) : basename($reportPath);

        if (! str_ends_with(strtolower($candidate), '.txt')) {
            $candidate .= '.txt';
        }

        return $candidate;
    }

    private function decodeFile(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('No fue posible leer uno de los archivos cargados.');
        }

        if (mb_check_encoding($content, 'UTF-8')) {
            return $content;
        }

        return mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }
}
