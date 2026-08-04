<?php

namespace App\Services\Importing;

use Illuminate\Support\Str;

class HeaderNormalizer
{
    public function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = Str::ascii($value);
        $value = mb_strtolower($value);

        /*
        |--------------------------------------------------------------------------
        | Normalizaciones frecuentes
        |--------------------------------------------------------------------------
        */

        $replacements = [
            'n°' => 'numero',
            'nº' => 'numero',
            'nro' => 'numero',
            'num' => 'numero',
            'cod' => 'codigo',
            'doc' => 'documento',
            'ipss' => 'ips',
            'yo' => 'y o',
        ];

        foreach ($replacements as $search => $replacement) {
            $value = preg_replace(
                '/\b' . preg_quote($search, '/') . '\b/u',
                $replacement,
                $value
            ) ?? $value;
        }

        /*
        |--------------------------------------------------------------------------
        | Eliminar aclaraciones de formato
        |--------------------------------------------------------------------------
        |
        | Ejemplos:
        | FECHA SOLICITUD (dd/mm/aaaa)
        | TIPO DOC (CC, TI, RC)
        |
        */

        $value = preg_replace('/\([^)]*\)/u', ' ', $value) ?? $value;

        /*
        |--------------------------------------------------------------------------
        | Dejar solamente letras y números
        |--------------------------------------------------------------------------
        */

        $value = preg_replace(
            '/[^a-z0-9]+/u',
            ' ',
            $value
        ) ?? $value;

        return trim(
            preg_replace('/\s+/', ' ', $value) ?? $value
        );
    }
}