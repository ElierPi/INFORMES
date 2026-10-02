<?php

/**
 * Pesos corregibles solo con un soporte clinico verificado.
 * Completar peso_kg y soporte; NUNCA inventar valores para pasar la EPS.
 * La clave combina la linea del reporte, tipo y documento.
 * El campo valor_actual impide aplicar la correccion a otro archivo.
 */
return [
    'pesos' => [
        '359|CC|56053168' => [
            'valor_actual' => '34',
            'peso_kg' => null, // Sustituir null por el peso REAL verificado.
            'soporte' => '',   // Referencia a historia clinica, fecha y responsable.
            'corte' => '2026-09-30',
        ],
        '377|RC|1243543066' => [
            'valor_actual' => '16',
            'peso_kg' => null,
            'soporte' => '',
            'corte' => '2026-09-30',
        ],
        '442|RC|1243544445' => [
            'valor_actual' => '230',
            'peso_kg' => null,
            'soporte' => '',
            'corte' => '2026-09-30',
        ],
    ],
];
