<?php

return [

    'sheets' => [
        1 => '1 - Control',
        2 => '2 - ID gestantes',
        3 => '3 - Atenciones',
        4 => '4 - Seguimientos',
        5 => '5 - Urgencias',
    ],

    /*
     * Variables oficiales del anexo técnico (numeración iniciando en 0).
     * El corrector ZIP usa además la descripción del LOG para evitar
     * desplazamientos de columna.
     */
    'fields' => [
        2 => [
            2 => [
                'key' => 'pais_nacionalidad',
                'header' => 'País de la nacionalidad',
                'type' => 'number',
            ],
            16 => [
                'key' => 'fecha_probable_parto',
                'header' => 'Fecha probable de parto',
                'type' => 'date',
            ],
            17 => [
                'key' => 'direccion_residencia',
                'header' => 'Dirección de residencia de la gestante',
                'type' => 'text',
            ],
        ],

        3 => [
            13 => [
                'key' => 'fecha_anticonceptivo',
                'header' => 'Fecha de suministro de anticonceptivo post evento obstétrico',
                'type' => 'date',
            ],
            14 => [
                'key' => 'suministro_anticonceptivo',
                'header' => 'Suministro de método anticonceptivo post evento obstétrico',
                'type' => 'number',
            ],
            15 => [
                'key' => 'fecha_salida_evento_obstetrico',
                'header' => 'Fecha de salida de aborto o atención del parto o cesárea',
                'type' => 'date',
            ],
            16 => [
                'key' => 'fecha_terminacion_gestacion',
                'header' => 'Fecha de terminación de la gestación',
                'type' => 'date',
            ],
            17 => [
                'key' => 'tipo_terminacion_gestacion',
                'header' => 'Tipo de terminación de la gestación',
                'type' => 'number',
            ],
            22 => [
                'key' => 'indice_pulsatilidad',
                'header' => 'Índice de pulsatilidad de arterias uterinas',
                'type' => 'decimal',
            ],
        ],
    ],
];
