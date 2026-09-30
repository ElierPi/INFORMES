<?php

namespace App\Services\Resolucion1604\Dusakawi;

use Illuminate\Support\Str;
use RuntimeException;

final class Resolucion1604DusakawiCorrectionService
{
    /** @return array<string, mixed> */
    public function correct(string $reportPath, string $errorsPath, string $outputPath): array
    {
        if (! is_file($reportPath)) {
            throw new RuntimeException('No se encontró el TXT original de la 1604 DUSAKAWI.');
        }
        if (! is_file($errorsPath)) {
            throw new RuntimeException('No se encontró el TXT de errores de DUSAKAWI.');
        }

        $content = file_get_contents($reportPath);
        if ($content === false) {
            throw new RuntimeException('No fue posible leer el TXT original.');
        }

        $lines = preg_split('/\r\n|\n|\r/', rtrim($content, "\r\n"));
        if (! is_array($lines) || count($lines) < 2) {
            throw new RuntimeException('El TXT no contiene línea de control y registros de detalle.');
        }

        $control = explode('|', (string) array_shift($lines));
        if (count($control) !== 6 || ($control[0] ?? '') !== '1') {
            throw new RuntimeException('La primera línea no corresponde a la línea de control esperada de 6 campos.');
        }

        $details = [];
        foreach ($lines as $index => $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $fields = explode('|', (string) $line);
            if (count($fields) !== 25) {
                throw new RuntimeException('La línea de detalle '.($index + 1).' no tiene exactamente 25 campos.');
            }
            $details[$index + 1] = $fields; // DUSAKAWI numera detalles desde 1.
        }

        $errors = $this->parseErrors($errorsPath);
        $rowsToDelete = [];
        $audit = [];
        $manual = [];
        $updatedRows = [];

        foreach ($errors as $lineNumber => $message) {
            if (! isset($details[$lineNumber])) {
                $manual[] = $this->manual($lineNumber, '', 'FILA', 'La línea reportada por DUSAKAWI no existe en el TXT original.', $message);
                continue;
            }

            $fields =& $details[$lineNumber];
            $document = ($fields[4] ?? '').'_'.($fields[5] ?? '');
            $normalized = Str::of($message)->ascii()->lower()->toString();

            // Reglas confirmadas por los cargues reales:
            // CUM inexistente/vacío, afiliado inexistente o diagnóstico inexistente -> excluir registro.
            if (
                str_contains($normalized, 'campo cum, no existe en la base de datos')
                || str_contains($normalized, 'campo cum, no puede estar vacio')
                || str_contains($normalized, 'campo diagnostico, no existe en la base de datos')
                || (str_contains($normalized, 'el afiliado ') && str_contains($normalized, 'no existe en la base de datos'))
            ) {
                if (str_contains($normalized, 'afiliado ')) {
                    $reason = 'Afiliado no existe en la base de datos de DUSAKAWI';
                } elseif (str_contains($normalized, 'campo diagnostico')) {
                    $reason = 'Diagnóstico no existe en la base de datos de DUSAKAWI';
                } else {
                    $reason = 'CUM inexistente o vacío en la base de datos de DUSAKAWI';
                }

                $rowsToDelete[$lineNumber] = true;
                $audit[] = [
                    'line' => $lineNumber,
                    'document' => $document,
                    'field' => 'FILA',
                    'previous_value' => 'Registro completo',
                    'new_value' => '',
                    'action' => 'REGISTRO EXCLUIDO: '.$reason,
                    'message' => $message,
                ];
                unset($fields);
                continue;
            }

            // DURACION permitida: 1 a 30 días. Para valores mayores se limita a 30.
            if (str_contains($normalized, 'campo duracion, no cumple con el formato')) {
                $old = trim((string) ($fields[13] ?? ''));
                if (ctype_digit($old)) {
                    $value = (int) $old;
                    $new = (string) max(1, min(30, $value));
                    if ($new !== $old) {
                        $fields[13] = $new;
                        $updatedRows[$lineNumber] = true;
                        $audit[] = $this->change($lineNumber, $document, 'DURACION', $old, $new, 'Duración normalizada al rango 1–30 días', $message);
                    }
                } else {
                    $manual[] = $this->manual($lineNumber, $document, 'DURACION', 'La duración no es numérica y el registro no fue excluido por otra regla.', $message);
                }
            }

            // Error observado en julio: 30/07/206 terminó como 0206-07-30.
            if (str_contains($normalized, 'es mayor a la fecha de la cita')) {
                $changed = false;
                foreach ([18 => 'FECHA_PRIMERA_ENTREGA', 19 => 'FECHA_ENTREGA_PENDIENTE'] as $fieldIndex => $fieldName) {
                    $old = trim((string) ($fields[$fieldIndex] ?? ''));
                    if (str_starts_with($old, '0206-')) {
                        $new = '2026-'.substr($old, 5);
                        $fields[$fieldIndex] = $new;
                        $changed = true;
                        $updatedRows[$lineNumber] = true;
                        $audit[] = $this->change($lineNumber, $document, $fieldName, $old, $new, 'Año 0206 corregido a 2026', $message);
                    }
                }
                if (! $changed) {
                    $manual[] = $this->manual($lineNumber, $document, 'FECHA', 'DUSAKAWI reportó fecha mayor a la cita, pero no se encontró el patrón 0206- en las fechas de entrega.', $message);
                }
            }

            // DURACION vacía: si el medicamento fue entregado, inferir días desde frecuencia y cantidad prescrita.
            if (str_contains($normalized, 'campo duracion, no puede estar vacio')) {
                $old = trim((string) ($fields[13] ?? ''));
                $delivered = trim((string) ($fields[22] ?? '')) === '1' || (float) ($fields[15] ?? 0) > 0;

                if ($delivered) {
                    $new = $this->inferDuration($fields);
                    if ($new !== null) {
                        $fields[13] = $new;
                        $updatedRows[$lineNumber] = true;
                        $audit[] = $this->change(
                            $lineNumber,
                            $document,
                            'DURACION',
                            $old,
                            $new,
                            'Duración calculada automáticamente desde frecuencia y cantidad prescrita',
                            $message
                        );
                    } else {
                        $manual[] = $this->manual($lineNumber, $document, 'DURACION', 'El medicamento fue entregado, pero no fue posible calcular la duración con frecuencia/cantidad.', $message);
                    }
                } else {
                    $manual[] = $this->manual($lineNumber, $document, 'DURACION', 'La duración está vacía y no hay evidencia de entrega para inferirla.', $message);
                }
            }

            if (str_contains($normalized, 'campo frecuencia, no puede estar vacio') && trim((string) ($fields[12] ?? '')) === '') {
                $manual[] = $this->manual($lineNumber, $document, 'FRECUENCIA', 'La frecuencia está vacía y no se debe inventar.', $message);
            }

            unset($fields);
        }

        foreach (array_keys($rowsToDelete) as $lineNumber) {
            unset($details[$lineNumber]);
        }

        $detailLines = [];
        foreach ($details as $fields) {
            $detailLines[] = implode('|', $fields);
        }

        $control[5] = (string) count($detailLines);
        $newControl = implode('|', $control);
        $output = $newControl."\r\n".implode("\r\n", $detailLines);

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear la carpeta de salida.');
        }
        if (file_put_contents($outputPath, $output) === false) {
            throw new RuntimeException('No fue posible guardar el TXT corregido.');
        }

        return [
            'output_path' => $outputPath,
            'parsed_error_lines' => count($errors),
            'original_records' => count($lines),
            'deleted_records' => count($rowsToDelete),
            'updated_records' => count($updatedRows),
            'final_records' => count($detailLines),
            'control_line' => $newControl,
            'manual_errors' => $this->uniqueManual($manual),
            'audit' => $audit,
        ];
    }

    /** @return array<int, string> */
    private function parseErrors(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('No fue posible leer el archivo de errores.');
        }

        $result = [];
        foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $line) {
            if (preg_match('/^Error linea\s+(\d+)\s+-->\s*(.*)$/i', trim((string) $line), $match)) {
                $result[(int) $match[1]] = trim((string) $match[2]);
            }
        }

        if ($result === []) {
            throw new RuntimeException('No se encontraron errores con el formato "Error linea N --> ...".');
        }
        return $result;
    }

    private function firstDiagnosis(string $value): string
    {
        $value = strtoupper(trim($value));
        $first = trim(explode('/', $value)[0] ?? '');
        $first = preg_replace('/\s+/', '', $first) ?? $first;

        // La estructura usada por DUSAKAWI maneja CIE10 de 4 caracteres (I10X, Z003, J00X...).
        if (preg_match('/^([A-Z][0-9]{2}[A-Z0-9])/', $first, $match)) {
            return $match[1];
        }
        return $first;
    }


    /** @param array<int, string> $fields */
    private function inferDuration(array $fields): ?string
    {
        $frequencyRaw = trim((string) ($fields[12] ?? ''));
        $quantityRaw = trim((string) ($fields[14] ?? ''));

        if (! is_numeric($frequencyRaw) || ! is_numeric($quantityRaw)) {
            return null;
        }

        $frequencyHours = (float) $frequencyRaw;
        $quantity = (float) $quantityRaw;
        if ($frequencyHours <= 0 || $quantity <= 0) {
            return null;
        }

        $dosesPerDay = 24 / $frequencyHours;
        if ($dosesPerDay <= 0) {
            return null;
        }

        $days = (int) ceil($quantity / $dosesPerDay);
        return (string) max(1, min(30, $days));
    }

    /** @return array<string, mixed> */
    private function change(int $line, string $document, string $field, string $old, string $new, string $action, string $message): array
    {
        return [
            'line' => $line,
            'document' => $document,
            'field' => $field,
            'previous_value' => $old,
            'new_value' => $new,
            'action' => $action,
            'message' => $message,
        ];
    }

    /** @return array<string, mixed> */
    private function manual(int $line, string $document, string $field, string $reason, string $message): array
    {
        return compact('line', 'document', 'field', 'reason', 'message');
    }

    /** @param array<int, array<string, mixed>> $items */
    private function uniqueManual(array $items): array
    {
        $seen = [];
        $result = [];
        foreach ($items as $item) {
            $key = ($item['line'] ?? '').'|'.($item['field'] ?? '').'|'.($item['reason'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $item;
        }
        return $result;
    }
}
