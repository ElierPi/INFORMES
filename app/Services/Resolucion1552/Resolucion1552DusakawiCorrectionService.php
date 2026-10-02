<?php

namespace App\Services\Resolucion1552;

use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1552DusakawiCorrectionService
{
    public function __construct(
        private readonly Resolucion1552DusakawiErrorParser $parser,
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

        $control = explode('|', $lines[0]);
        if (count($control) !== 6 || trim((string) ($control[0] ?? '')) !== '1') {
            throw new RuntimeException('La primera línea no es una línea de control válida de seis campos.');
        }

        $periodStart = trim((string) ($control[3] ?? ''));
        $periodEnd = trim((string) ($control[4] ?? ''));

        if (! $this->isIsoDate($periodStart) || ! $this->isIsoDate($periodEnd)) {
            throw new RuntimeException(
                'La línea de control no contiene un período válido en formato AAAA-MM-DD.'
            );
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
            $fields = explode('|', $line);

            $isDuplicate = str_contains($normalizedMessage, 'ya se encuentra cargada')
                || str_contains($normalizedMessage, 'repetida en el archivo');

            if ($isDuplicate) {
                if ($lineNumber === 1) {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' => 'La línea de control no puede excluirse automáticamente.',
                    ];
                    continue;
                }

                $firstOccurrence = array_search($line, $lines, true);
                $internalDuplicate = $firstOccurrence !== false && ($firstOccurrence + 1) < $lineNumber;

                $remove[$lineNumber] = true;
                $audit[] = [
                    'line' => $lineNumber,
                    'record_type' => $fields[0] ?? '',
                    'document_type' => $fields[2] ?? '',
                    'document_number' => $fields[3] ?? '',
                    'cups' => $fields[10] ?? '',
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
                if (count($fields) !== 11 || trim((string) ($fields[0] ?? '')) !== '2') {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' => 'La línea no tiene los once campos de detalle esperados.',
                    ];
                    continue;
                }

                $currentType = strtoupper(trim((string) ($fields[2] ?? '')));
                $nextType = match ($currentType) {
                    'RC' => 'TI',
                    'TI' => 'CC',
                    default => null,
                };

                if ($nextType === null) {
                    $remove[$lineNumber] = true;
                    $audit[] = [
                        'line' => $lineNumber,
                        'record_type' => $fields[0],
                        'document_type' => $currentType,
                        'document_number' => $fields[3] ?? '',
                        'cups' => $fields[10] ?? '',
                        'internal_duplicate' => false,
                        'first_occurrence' => null,
                        'previous_value' => $currentType,
                        'new_value' => null,
                        'action' => "Línea excluida porque el afiliado con tipo {$currentType} no existe en la base de datos",
                        'message' => $error['message'],
                    ];
                    continue;
                }

                $fields[2] = $nextType;
                $updates[$lineNumber] = implode('|', $fields);
                $audit[] = [
                    'line' => $lineNumber,
                    'record_type' => $fields[0],
                    'document_type' => $nextType,
                    'document_number' => $fields[3] ?? '',
                    'cups' => $fields[10] ?? '',
                    'internal_duplicate' => false,
                    'first_occurrence' => null,
                    'previous_value' => $currentType,
                    'new_value' => $nextType,
                    'action' => "Tipo de documento cambiado de {$currentType} a {$nextType}",
                    'message' => $error['message'],
                ];
                continue;
            }

            $isDateOutOfRange = str_contains(
                $normalizedMessage,
                'fecha cita no esta en el rango de las fechas'
            );

            if ($isDateOutOfRange) {
                if (count($fields) !== 11 || trim((string) ($fields[0] ?? '')) !== '2') {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' => 'La línea no tiene los once campos de detalle esperados.',
                    ];
                    continue;
                }

                $appointmentDates = [
                    trim((string) ($fields[6] ?? '')),
                    trim((string) ($fields[7] ?? '')),
                    trim((string) ($fields[8] ?? '')),
                ];

                $outsidePeriod = false;

                foreach ($appointmentDates as $date) {
                    if (
                        $this->isIsoDate($date)
                        && ($date < $periodStart || $date > $periodEnd)
                    ) {
                        $outsidePeriod = true;
                        break;
                    }
                }

                if (! $outsidePeriod) {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' =>
                            'DUSAKAWI reportó una fecha fuera del período, pero las fechas del registro no permiten confirmar esa condición automáticamente.',
                    ];
                    continue;
                }

                $remove[$lineNumber] = true;
                $audit[] = [
                    'line' => $lineNumber,
                    'record_type' => $fields[0] ?? '',
                    'document_type' => $fields[2] ?? '',
                    'document_number' => $fields[3] ?? '',
                    'cups' => $fields[10] ?? '',
                    'appointment_date' => $fields[6] ?? '',
                    'internal_duplicate' => false,
                    'first_occurrence' => null,
                    'previous_value' => implode(' / ', $appointmentDates),
                    'new_value' => null,
                    'action' =>
                        "Línea excluida porque la fecha de cita está fuera del período {$periodStart} a {$periodEnd}",
                    'message' => $error['message'],
                ];
                continue;
            }

            $isInvalidCups = str_contains(
                $normalizedMessage,
                'el valor del campo cups, no existe en la base de datos'
            );

            if ($isInvalidCups) {
                if (count($fields) !== 11 || trim((string) ($fields[0] ?? '')) !== '2') {
                    $manual[] = [
                        'line' => $lineNumber,
                        'message' => $error['message'],
                        'reason' => 'La línea no tiene los once campos de detalle esperados.',
                    ];
                    continue;
                }

                $remove[$lineNumber] = true;
                $audit[] = [
                    'line' => $lineNumber,
                    'record_type' => $fields[0] ?? '',
                    'document_type' => $fields[2] ?? '',
                    'document_number' => $fields[3] ?? '',
                    'cups' => $fields[10] ?? '',
                    'appointment_date' => $fields[6] ?? '',
                    'internal_duplicate' => false,
                    'first_occurrence' => null,
                    'previous_value' => $fields[10] ?? '',
                    'new_value' => null,
                    'action' =>
                        'Línea excluida porque DUSAKAWI reportó que el CUPS no existe en su base de datos',
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

        $correctedDetails = [];
        foreach (array_slice($lines, 1) as $index => $line) {
            $originalLineNumber = $index + 2;

            if (isset($remove[$originalLineNumber])) {
                continue;
            }

            $fields = explode('|', $updates[$originalLineNumber] ?? $line);
            if (count($fields) !== 11 || trim((string) ($fields[0] ?? '')) !== '2') {
                throw new RuntimeException("La línea {$originalLineNumber} no tiene la estructura de detalle esperada.");
            }

            $fields[1] = (string) (count($correctedDetails) + 1);
            $correctedDetails[] = implode('|', $fields);
        }

        $previousTotal = trim((string) ($control[5] ?? ''));
        $control[5] = (string) count($correctedDetails);
        $correctedLines = [implode('|', $control), ...$correctedDetails];

        $directory = storage_path('app/private/reports/resolucion1552-dusakawi-correccion/' . Str::uuid());
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
            'original_records' => count($lines) - 1,
            'corrected_records' => count($correctedDetails),
            'removed_records' => count($remove),
            'updated_records' => count($updates),
            'parsed_errors' => count($parsedErrors),
            'automatic_corrections' => count($audit),
            'manual_errors' => $manual,
            'audit' => $audit,
            'previous_control_total' => $previousTotal,
            'new_control_total' => count($correctedDetails),
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

    private function isIsoDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map(
            'intval',
            explode('-', $value)
        );

        return checkdate($month, $day, $year);
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
