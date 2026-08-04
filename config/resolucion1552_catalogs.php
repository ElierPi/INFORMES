<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de documento
    |--------------------------------------------------------------------------
    |
    | La clave representa las posibles formas en las que puede venir
    | el dato desde el Excel.
    |
    | El valor es la representación normalizada que manejará
    | internamente la Resolución 1552.
    |
    */

    'document_types' => [
        'CC' => 'CC',
        'CEDULA' => 'CC',
        'CEDULA DE CIUDADANIA' => 'CC',
        'CÉDULA' => 'CC',
        'CÉDULA DE CIUDADANÍA' => 'CC',

        'TI' => 'TI',
        'TARJETA DE IDENTIDAD' => 'TI',

        'RC' => 'RC',
        'REGISTRO CIVIL' => 'RC',

        'CE' => 'CE',
        'CEDULA DE EXTRANJERIA' => 'CE',
        'CÉDULA DE EXTRANJERÍA' => 'CE',

        'PA' => 'PA',
        'PASAPORTE' => 'PA',

        'PT' => 'PT',
        'PERMISO POR PROTECCION TEMPORAL' => 'PT',
        'PERMISO POR PROTECCIÓN TEMPORAL' => 'PT',

        'PEP' => 'PEP',
        'PERMISO ESPECIAL DE PERMANENCIA' => 'PEP',

        'CN' => 'CN',
        'CERTIFICADO DE NACIDO VIVO' => 'CN',

        'MS' => 'MS',
        'MENOR SIN IDENTIFICACION' => 'MS',
        'MENOR SIN IDENTIFICACIÓN' => 'MS',

        'AS' => 'AS',
        'ADULTO SIN IDENTIFICACION' => 'AS',
        'ADULTO SIN IDENTIFICACIÓN' => 'AS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Régimen
    |--------------------------------------------------------------------------
    */

    'regimes' => [
        'S' => 'S',
        'SUBSIDIADO' => 'S',
        'REGIMEN SUBSIDIADO' => 'S',
        'RÉGIMEN SUBSIDIADO' => 'S',

        'C' => 'C',
        'CONTRIBUTIVO' => 'C',
        'REGIMEN CONTRIBUTIVO' => 'C',
        'RÉGIMEN CONTRIBUTIVO' => 'C',
    ],

    /*
    |--------------------------------------------------------------------------
    | Formato interno de fechas
    |--------------------------------------------------------------------------
    */

    'date_output_format' => 'd/m/Y',

    /*
    |--------------------------------------------------------------------------
    | Campos obligatorios para la transformación inicial
    |--------------------------------------------------------------------------
    |
    | Esta lista corresponde al conjunto de datos que actualmente
    | recibe el importador.
    |
    | Posteriormente se ajustará contra la estructura definitiva
    | establecida en config/resolucion1552.php.
    |
    */

    'required_fields' => [
        'provider_nit',
        'provider_code',
        'provider_name',
        'patient_name',
        'document_type',
        'document_number',
        'municipality_code',
        'cups_code',
        'specialty',
        'request_date',
        'assignment_date',
        'appointment_date',
        'opportunity_days',
        'specialist_hours',
        'regime',
    ],

    /*
    |--------------------------------------------------------------------------
    | Teléfono
    |--------------------------------------------------------------------------
    |
    | Por ahora no se marca como obligatorio porque el archivo de CIDSMA
    | no contiene esa información.
    |
    */

    'phone_required' => false,
];