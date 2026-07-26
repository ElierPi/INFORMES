<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hojas por tipo de registro
    |--------------------------------------------------------------------------
    */

    'sheets' => [
        1 => '1 - Control',
        2 => '2 - ID gestantes',
        3 => '3 - Atenciones',
        4 => '4 - Seguimientos',
        5 => '5 - Urgencias',
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables reportadas por SIGIRES
    |--------------------------------------------------------------------------
    |
    | La posición informada por SIGIRES se traduce primero a una clave lógica.
    | Después se busca la columna real mediante el encabezado del Excel.
    |
    */

    'fields' => [

        2 => [
            18 => [
                'key' => 'direccion_residencia',
                'header' => 'Dirección de la residencia de la usuaria',
                'type' => 'text',
            ],
        ],

        3 => [
            14 => [
                'key' => 'fecha_anticonceptivo',
                'header' => 'Fecha de suministro de anticonceptivo post evento obstétrico',
                'type' => 'date',
            ],

            15 => [
                'key' => 'suministro_anticonceptivo',
                'header' => 'Suministro de anticonceptivo post evento obstétrico',
                'type' => 'number',
            ],

            16 => [
                'key' => 'fecha_salida_evento_obstetrico',
                'header' => 'Fecha de salida de aborto o atención del parto o cesárea',
                'type' => 'date',
            ],

            17 => [
                'key' => 'fecha_terminacion_gestacion',
                'header' => 'Fecha de terminación de la gestación',
                'type' => 'date',
            ],
18 => [
    'key' => 'tipo_terminacion_gestacion',
    'header' => 'Tipo de terminación de la gestación',
    'type' => 'number',
],
        ],
    ],
];