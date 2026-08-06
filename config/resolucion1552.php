<?php

return [
    'report_name' => 'Resolución 1552 de 2013',

    'file' => [
        'extension' => 'zip',
        'txt_extension' => 'txt',
        'delimiter' => '|',
        'encoding' => 'Windows-1252',
        'has_header' => false,
        'include_header' => false,

        'filename_pattern' =>
            '/^RESOLUCION_1552_\d{2,12}_\d{8}\.zip$/i',

        'txt_filename_pattern' =>
            '/^RESOLUCION_1552_\d{2,12}_\d{8}\.txt$/i',

        'filename_example' =>
            'RESOLUCION_1552_123456789012_20260430.zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Columnas oficiales
    |--------------------------------------------------------------------------
    |
    | La propiedad "field" enlaza la columna oficial con el DTO
    | Resolucion1552Record.
    |
    */

    'columns' => [
        [
            'index' => 1,
            'field' => 'provider_code',
            'name' => 'Código de habilitación',
            'header' => 'Código de habilitación',
            'type' => 'numeric',
            'required' => true,
            'min_length' => 2,
            'max_length' => 12,
            'help' =>
                'Registre el código de habilitación de la IPS.',
        ],

        [
            'index' => 2,
            'field' => 'document_type',
            'name' => 'Tipo de identificación del usuario',
            'header' => 'Tipo de identificación del usuario',
            'type' => 'alpha',
            'required' => true,
            'min_length' => 2,
            'max_length' => 2,

            'allowed' => [
                'CC',
                'TI',
                'CE',
                'PA',
                'RC',
                'PE',
                'MS',
                'AS',
                'CD',
                'NV',
                'PT',
                'SC',
                'CN',
            ],

            'allowed_labels' => [
                'CC' => 'Cédula de ciudadanía',
                'TI' => 'Tarjeta de identidad',
                'CE' => 'Cédula de extranjería',
                'PA' => 'Pasaporte',
                'RC' => 'Registro civil',
                'PE' => 'Permiso especial de permanencia',
                'MS' => 'Menor sin identificación',
                'AS' => 'Adulto sin identificación',
                'CD' => 'Carné diplomático',
                'NV' => 'Certificado de nacido vivo',
                'PT' => 'Permiso por protección temporal',
                'SC' => 'Salvoconducto',
                'CN' => 'Certificado de nacido vivo',
            ],

            'help' =>
                'Registre una abreviatura válida de documento.',
        ],

        [
            'index' => 3,
            'field' => 'document_number',
            'name' => 'Identificación del usuario',
            'header' => 'Identificación del usuario',
            'type' => 'alphanumeric',
            'required' => true,
            'min_length' => 3,
            'max_length' => 20,
            'help' =>
                'Registre el número de identificación del usuario.',
        ],

        [
            'index' => 4,
            'field' => 'regime',
            'name' => 'Régimen',
            'header' => 'Régimen',
            'type' => 'alpha',
            'required' => true,
            'min_length' => 1,
            'max_length' => 1,

            'allowed' => [
                'C',
                'S',
            ],

            'allowed_labels' => [
                'C' => 'Contributivo',
                'S' => 'Subsidiado',
            ],

            'help' =>
                'Registre C para contributivo o S para subsidiado.',
        ],

        [
            'index' => 5,
            'field' => 'phone',
            'name' => 'Datos de contacto del usuario',
            'header' => 'Datos de contacto del usuario',
            'type' => 'contact',
            'required' => true,
            'min_length' => 10,
            'max_length' => 21,

            'default' => '9999999999',

            'help' =>
                'Puede registrar hasta dos números separados por coma. '
                . 'Si no cuenta con el dato, registre 9999999999.',
        ],

        [
            'index' => 6,
            'field' => 'specialty',
            'name' => 'Especialidad',
            'header' => 'Especialidad',
            'type' => 'catalog',
            'required' => true,
            'min_length' => 3,
            'max_length' => 4,

            'catalog' => 'resolucion1552_specialties',

            'help' =>
                'Registre un código válido del catálogo de especialidades.',
        ],

        [
            'index' => 7,
            'field' => 'request_date',
            'name' => 'Fecha en que el usuario solicita la cita',
            'header' => 'Fecha en que el usuario solicita la cita',
            'type' => 'date',
            'format' => 'd/m/Y',
            'required' => true,
            'min_length' => 10,
            'max_length' => 10,

            'rules' => [
                [
                    'type' => 'not_future',
                ],
            ],

            'help' =>
                'Use el formato DD/MM/AAAA. La fecha no puede ser futura.',
        ],

        [
            'index' => 8,
            'field' => 'assignment_date',

            'name' =>
                'Fecha en que el usuario solicita le sea asignada la cita',

            'header' =>
                'Fecha en que el usuario solicita le sea asignada la cita',

            'type' => 'date',
            'format' => 'd/m/Y',
            'required' => true,
            'min_length' => 10,
            'max_length' => 10,

            'rules' => [
                [
                    'type' => 'date_not_before',
                    'field_index' => 7,
                ],
            ],

            'help' =>
                'Use DD/MM/AAAA. No puede ser anterior al campo 7.',
        ],

        [
            'index' => 9,
            'field' => 'appointment_date',
            'name' => 'Fecha para la cual se asigna la cita',
            'header' => 'Fecha para la cual se asigna la cita',
            'type' => 'date',
            'format' => 'd/m/Y',
            'required' => true,
            'min_length' => 10,
            'max_length' => 10,

            'rules' => [
                [
                    'type' => 'date_not_before',
                    'field_index' => 7,
                ],
            ],

            'help' =>
                'Use DD/MM/AAAA. No puede ser anterior al campo 7.',
        ],

        [
            'index' => 10,
            'field' => 'specialist_hours',

            'name' =>
                'Número de horas-especialista, contratadas o disponibles para cada especialidad en el mes anterior a la cuantificación.',

            'header' =>
                'Número de horas-especialista, contratadas o disponibles para cada especialidad en el mes anterior a la cuantificación.',

            'type' => 'decimal',
            'required' => true,
            'min_length' => 1,
            'max_length' => 5,
            'minimum' => 0.01,

            'help' =>
                'Registre un número mayor que cero. Los decimales deben utilizar punto.',
        ],
    ],
];