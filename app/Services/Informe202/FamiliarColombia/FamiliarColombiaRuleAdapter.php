<?php

namespace App\Services\Informe202\FamiliarColombia;

use Illuminate\Support\Str;

/**
 * Traduce el formato de errores de Familiar de Colombia
 * a los códigos lógicos que ya procesa el motor usado por
 * Proteger y Dusakawi.
 */
final class FamiliarColombiaRuleAdapter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function adapt(array $error): array
    {
        $message = (string) ($error['description'] ?? '');
        $normalized = Str::of($message)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();

        $reportedVariable = (int) ($error['variable'] ?? -1);
        $code = null;
        $variables = [];

        if (str_contains($normalized, 'triglicer')) {
            $code = str_contains($normalized, 'menores de 29')
                ? '631'
                : '630';

            $variables = [98, 118];
        } elseif (
            str_contains($normalized, 'salud bucal')
            || str_contains($normalized, 'cop por persona')
        ) {
            $code = '226';
            $variables = [76, 102];
        } elseif (
            str_contains($normalized, 'escala abreviada de desarrollo')
        ) {
            $code = match ($reportedVariable) {
                43 => '560',
                44 => '564',
                45 => '568',
                46 => '572',
                default => '560',
            };

            $variables = [43, 44, 45, 46, 52];
        } elseif (
            str_contains($normalized, 'planificacion familiar')
            || str_contains($normalized, 'suministro de metodo')
        ) {
            /*
             * Este bloque se resuelve en el puente complementario
             * porque involucra simultáneamente 53, 54 y 55.
             */
            return [];
        } elseif (
            str_contains($normalized, 'peso de los')
            || str_contains($normalized, 'peso de los adultos')
        ) {
            return [];
        } elseif (
            str_contains($normalized, 'fecha registrada no es valida')
        ) {
            return [];
        } elseif (
            $reportedVariable >= 0
            && $reportedVariable <= 118
        ) {
            $variables = [$reportedVariable];
        }

        if ($variables === []) {
            return [];
        }

        $relatedVariables = $variables;
        $result = [];

        foreach (array_unique($variables) as $variable) {
            $result[] = [
                'codigo' => $code,
                'fila' => (int) ($error['record'] ?? 0),
                'registro' => (int) ($error['record'] ?? 0),
                'linea' => (int) ($error['record'] ?? 0),
                'variable' => $variable,
                'variables_relacionadas' => $relatedVariables,
                'campo' => "Variable {$variable}",
                'mensaje' => $message,
                'origen' => 'dusakawi',
                'tipo_error_familiar' => $error['type'] ?? null,
                'valor_anterior_familiar' => $error['old_value'] ?? null,
                'valor_nuevo_familiar' => $error['new_value'] ?? null,
            ];
        }

        return $result;
    }
}
