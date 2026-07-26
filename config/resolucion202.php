<?php

/*
|--------------------------------------------------------------------------
| Catálogo oficial — Resolución 202 de 2021
|--------------------------------------------------------------------------
|
| Variables 0 a 118 del Registro Tipo 2.
| Este archivo centraliza tipo, longitud, obligatoriedad, valores permitidos
| y relaciones básicas. Las reglas clínicas por edad, sexo y coherencia
| deben vivir en el motor de reglas, no en los parsers de cada EPS.
|
*/

$specialDates = [
    '1800-01-01' => 'No se tiene el dato',
    '1805-01-01' => 'No se realiza por tradición',
    '1810-01-01' => 'No se realiza por condición de salud',
    '1825-01-01' => 'No se realiza por negación del usuario',
    '1830-01-01' => 'Datos de contacto no actualizados',
    '1835-01-01' => 'No se realiza por otras razones',
    '1845-01-01' => 'No aplica',
];

$basicResultValues = [
    0 => 'No aplica',
    4 => 'Alterado / positivo / reactivo según campo',
    5 => 'Normal / negativo / no reactivo según campo',
    21 => 'Riesgo no evaluado',
];

return [

    /*
    |--------------------------------------------------------------------------
    | Metadatos generales del archivo
    |--------------------------------------------------------------------------
    */

    'meta' => [
        'sheet_name' => 'ESTRUCTURA',
        'first_variable_column_index' => 2, // B = variable 0
        'record_number_variable' => 1,
        'birth_date_variable' => 9,
        'sex_variable' => 10,
        'encoding' => 'ANSI',
        'separator' => '|',
        'date_format' => 'Y-m-d',
        'text_uppercase' => true,
        'text_without_accents' => true,
        'text_without_special_characters' => true,
    ],

    'special_dates' => $specialDates,

    /*
    |--------------------------------------------------------------------------
    | Definición de variables 0 a 118
    |--------------------------------------------------------------------------
    */

    'fields' => [

        0 => [
            'name' => 'Tipo de registro',
            'length' => 1,
            'type' => 'N',
            'required' => true,
            'allowed' => [2],
            'correction' => 'fixed',
            'fixed_value' => 2,
        ],

        1 => [
            'name' => 'Consecutivo de registro',
            'length' => 8,
            'type' => 'N',
            'required' => true,
            'correction' => 'sequence',
        ],

        2 => [
            'name' => 'Código de habilitación IPS primaria',
            'length' => 12,
            'type' => 'N',
            'required' => true,
            'reference_table' => 'REPS',
            'fallback' => 999,
        ],

        3 => [
            'name' => 'Tipo de identificación del usuario',
            'length' => 2,
            'type' => 'A',
            'required' => true,
            'reference_table' => 'SGDTipoID',
        ],

        4 => [
            'name' => 'Número de identificación del usuario',
            'length' => 18,
            'type' => 'A',
            'required' => true,
        ],

        5 => [
            'name' => 'Primer apellido del usuario',
            'length' => 30,
            'type' => 'A',
            'required' => true,
            'uppercase' => true,
            'strip_accents' => true,
            'strip_special_characters' => true,
        ],

        6 => [
            'name' => 'Segundo apellido del usuario',
            'length' => 30,
            'type' => 'A',
            'required' => true,
            'uppercase' => true,
            'strip_accents' => true,
            'strip_special_characters' => true,
            'fallback' => 'NONE',
        ],

        7 => [
            'name' => 'Primer nombre del usuario',
            'length' => 30,
            'type' => 'A',
            'required' => true,
            'uppercase' => true,
            'strip_accents' => true,
            'strip_special_characters' => true,
        ],

        8 => [
            'name' => 'Segundo nombre del usuario',
            'length' => 30,
            'type' => 'A',
            'required' => true,
            'uppercase' => true,
            'strip_accents' => true,
            'strip_special_characters' => true,
            'fallback' => 'NONE',
        ],

        9 => [
            'name' => 'Fecha de nacimiento',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
        ],

        10 => [
            'name' => 'Sexo',
            'length' => 1,
            'type' => 'A',
            'required' => true,
            'reference_table' => 'PSRGSEXO',
        ],

        11 => [
            'name' => 'Código pertenencia étnica',
            'length' => 1,
            'type' => 'N',
            'required' => true,
            'reference_table' => 'GrupoEtnico',
        ],

        12 => [
            'name' => 'Código de ocupación',
            'length' => 4,
            'type' => 'N',
            'required' => true,
            'reference_table' => 'SGDCIUO',
            'special_values' => [
                9999 => 'No se tiene información',
                9998 => 'No aplica',
            ],
        ],

        13 => [
            'name' => 'Código de nivel educativo',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'reference_table' => 'SGDNivEducativo',
        ],

        14 => [
            'name' => 'Gestante',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Sí',
                2 => 'No',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['sex', 'age'],
        ],

        15 => [
            'name' => 'Sífilis gestacional o congénita',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => ['age', 'pregnancy'],
        ],

        16 => [
            'name' => 'Resultado de prueba mini-mental state',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Sospecha de deterioro cognoscitivo',
                5 => 'Normal',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['age'],
        ],

        17 => [
            'name' => 'Hipotiroidismo congénito',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => ['age'],
        ],

        18 => [
            'name' => 'Sintomático respiratorio',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                1 => 'Sí',
                2 => 'No',
                21 => 'Riesgo no evaluado',
            ],
        ],

        19 => [
            'name' => 'Consumo de tabaco',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed_range' => [0, 95],
            'special_values' => [
                96 => 'IPA igual o mayor a 96',
                97 => 'Exfumador',
                98 => 'No aplica',
                99 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['age'],
        ],

        20 => [
            'name' => 'Lepra',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [21 => 'Riesgo no evaluado'],
        ],

        21 => [
            'name' => 'Obesidad o desnutrición proteico calórica',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [21 => 'Riesgo no evaluado'],
        ],

        22 => [
            'name' => 'Resultado del tacto rectal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Próstata anormal',
                5 => 'Próstata normal',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['sex', 'age', 64],
        ],

        23 => [
            'name' => 'Ácido fólico preconcepcional',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Sí',
                2 => 'No',
                21 => 'Registro no evaluado',
            ],
            'depends_on' => ['sex', 'age'],
        ],

        24 => [
            'name' => 'Resultado prueba sangre oculta en materia fecal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Positivo',
                5 => 'Negativo',
                6 => 'No es posible determinar el resultado',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 67,
        ],

        25 => [
            'name' => 'Enfermedad mental',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [21 => 'Riesgo no evaluado'],
        ],

        26 => [
            'name' => 'Cáncer de cérvix',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => ['sex', 'age'],
        ],

        27 => [
            'name' => 'Agudeza visual lejana ojo izquierdo',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                3 => 'Menor o igual a 20/20 normal',
                4 => 'Entre 20/25 y 20/40',
                5 => 'Mayor o igual a 20/50 hasta 1200 anormal',
                6 => 'Cuenta dedos',
                7 => 'Percepción de bultos',
                8 => 'Proyección y percepción de luz',
                9 => 'No percibe luz',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 62,
            'depends_on' => ['age'],
        ],

        28 => [
            'name' => 'Agudeza visual lejana ojo derecho',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                3 => 'Menor o igual a 20/20 normal',
                4 => 'Entre 20/25 y 20/40',
                5 => 'Mayor o igual a 20/50 hasta 1200 anormal',
                6 => 'Cuenta dedos',
                7 => 'Percepción de bultos',
                8 => 'Proyección y percepción de luz',
                9 => 'No percibe luz',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 62,
            'depends_on' => ['age'],
        ],

        29 => [
            'name' => 'Fecha del peso',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => ['1800-01-01' => 'No se toma'],
            'paired_result' => 30,
        ],

        30 => [
            'name' => 'Peso en kilogramos',
            'length' => 5,
            'type' => 'D',
            'required' => true,
            'special_values' => [999 => 'No se toma'],
            'paired_date' => 29,
        ],

        31 => [
            'name' => 'Fecha de la talla',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => ['1800-01-01' => 'No se toma'],
            'paired_result' => 32,
        ],

        32 => [
            'name' => 'Talla en centímetros',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'special_values' => [999 => 'No se toma'],
            'paired_date' => 31,
        ],

        33 => [
            'name' => 'Fecha probable de parto',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => [
                '1800-01-01' => 'No se tiene el dato',
                '1845-01-01' => 'No aplica',
            ],
            'depends_on' => ['pregnancy'],
        ],

        34 => [
            'name' => 'Código país',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'reference_table' => 'PAIS_ISO3166_1_NUMERIC',
        ],

        35 => [
            'name' => 'Clasificación del riesgo gestacional',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Alto riesgo',
                5 => 'Bajo riesgo',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['pregnancy'],
        ],

        36 => [
            'name' => 'Resultado de colonoscopia tamizaje',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                2 => 'Pólipos hiperplásicos',
                3 => 'Proceso preneoplásico',
                4 => 'Proceso neoplásico',
                5 => 'Colonoscopia normal',
                6 => 'Otros hallazgos',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 66,
        ],

        37 => [
            'name' => 'Resultado de tamizaje auditivo neonatal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'No pasó',
                5 => 'Pasó',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 69,
            'depends_on' => ['age'],
        ],

        38 => [
            'name' => 'Resultado de tamizaje visual neonatal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Alterado',
                5 => 'Normal',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 75,
            'depends_on' => ['age'],
        ],

        39 => [
            'name' => 'DPT menores de 5 años',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => ['age'],
        ],

        40 => [
            'name' => 'Resultado de tamizaje VALE',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Falla',
                5 => 'Pasa',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 63,
            'depends_on' => ['age'],
        ],

        41 => [
            'name' => 'Neumococo',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => ['age'],
        ],

        42 => [
            'name' => 'Resultado de tamizaje para hepatitis C',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Reactivo',
                5 => 'No reactivo',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 110,
        ],

        43 => [
            'name' => 'Escala abreviada desarrollo - motricidad gruesa',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                3 => 'Sospecha de problemas de desarrollo',
                4 => 'Riesgo de problemas de desarrollo',
                5 => 'Desarrollo esperado para la edad',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['age'],
        ],

        44 => [
            'name' => 'Escala abreviada desarrollo - motricidad finoadaptativa',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                3 => 'Sospecha de problemas de desarrollo',
                4 => 'Riesgo de problemas de desarrollo',
                5 => 'Desarrollo esperado para la edad',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['age'],
        ],

        45 => [
            'name' => 'Escala abreviada desarrollo - personal social',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                3 => 'Sospecha de problemas de desarrollo',
                4 => 'Riesgo de problemas de desarrollo',
                5 => 'Desarrollo esperado para la edad',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['age'],
        ],

        46 => [
            'name' => 'Escala abreviada desarrollo - audición lenguaje',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                3 => 'Sospecha de problemas de desarrollo',
                4 => 'Riesgo de problemas de desarrollo',
                5 => 'Desarrollo esperado para la edad',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['age'],
        ],

        47 => [
            'name' => 'Tratamiento ablativo o de escisión',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                6 => 'Tratamiento ablativo',
                7 => 'Tratamiento de escisión',
                8 => 'Tratamiento homologable',
                9 => 'Requiere otro procedimiento',
                10 => 'No se realizó por otras razones',
                21 => 'Registro no evaluado',
            ],
            'depends_on' => [86, 87, 88],
        ],

        48 => [
            'name' => 'Resultado de tamización con oximetría pre y post ductal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                4 => 'Alterado',
                5 => 'Normal',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 65,
            'depends_on' => ['age'],
        ],

        49 => [
            'name' => 'Fecha de atención parto o cesárea',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => [
                '1800-01-01' => 'No se tiene el dato',
                '1845-01-01' => 'No aplica',
            ],
        ],

        50 => [
            'name' => 'Fecha de salida de atención parto o cesárea',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => [
                '1800-01-01' => 'No se tiene el dato',
                '1845-01-01' => 'No aplica',
            ],
        ],

        51 => ['name' => 'Fecha atención promoción y apoyo lactancia materna', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates],
        52 => ['name' => 'Fecha consulta de valoración integral', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates],
        53 => ['name' => 'Fecha atención asesoría en anticoncepción', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates],

        54 => [
            'name' => 'Suministro de método anticonceptivo',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Dispositivo intrauterino',
                2 => 'Dispositivo intrauterino y preservativo',
                3 => 'Implante subdérmico',
                4 => 'Implante subdérmico y preservativo',
                5 => 'Oral',
                6 => 'Oral y preservativo',
                7 => 'Inyectable mensual',
                8 => 'Inyectable mensual y preservativo',
                9 => 'Inyectable trimestral',
                10 => 'Inyectable trimestral y preservativo',
                11 => 'Emergencia',
                12 => 'Emergencia y preservativo',
                13 => 'Esterilización',
                14 => 'Esterilización y preservativo',
                15 => 'Preservativo',
                16 => 'No se suministra por tradición',
                17 => 'No se suministra por condición de salud',
                18 => 'No se suministra por negación',
                20 => 'No se suministra por otras razones',
                21 => 'Registro no evaluado',
            ],
            'paired_date' => 55,
            'depends_on' => ['sex', 'age'],
        ],

        55 => ['name' => 'Fecha de suministro de método anticonceptivo', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 54],
        56 => ['name' => 'Fecha de primera consulta prenatal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'depends_on' => ['pregnancy']],

        57 => [
            'name' => 'Resultado de glicemia basal',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'special_values' => [
                998 => 'Riesgo no evaluado',
                0 => 'No aplica',
            ],
            'paired_date' => 105,
        ],

        58 => [
            'name' => 'Fecha de último control prenatal de seguimiento',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => [
                '1800-01-01' => 'No se tiene el dato',
                '1845-01-01' => 'No aplica',
            ],
            'depends_on' => ['pregnancy'],
        ],

        59 => [
            'name' => 'Suministro de ácido fólico en control prenatal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 1 => 'Sí', 16 => 'Tradición', 17 => 'Condición de salud', 18 => 'Negación', 20 => 'Otras razones', 21 => 'Registro no evaluado'],
            'depends_on' => ['pregnancy'],
        ],

        60 => [
            'name' => 'Suministro de sulfato ferroso en control prenatal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 1 => 'Sí', 16 => 'Tradición', 17 => 'Condición de salud', 18 => 'Negación', 20 => 'Otras razones', 21 => 'Registro no evaluado'],
            'depends_on' => ['pregnancy'],
        ],

        61 => [
            'name' => 'Suministro de carbonato de calcio en control prenatal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 1 => 'Sí', 16 => 'Tradición', 17 => 'Condición de salud', 18 => 'Negación', 20 => 'Otras razones', 21 => 'Registro no evaluado'],
            'depends_on' => ['pregnancy'],
        ],

        62 => ['name' => 'Fecha de valoración agudeza visual', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_results' => [27, 28], 'depends_on' => ['age']],
        63 => ['name' => 'Fecha de tamizaje VALE', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 40, 'depends_on' => ['age']],
        64 => ['name' => 'Fecha del tacto rectal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 22, 'depends_on' => ['sex', 'age']],
        65 => ['name' => 'Fecha de tamización con oximetría pre y post ductal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 48, 'depends_on' => ['age']],
        66 => ['name' => 'Fecha de realización colonoscopia tamizaje', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 36],
        67 => ['name' => 'Fecha prueba sangre oculta en materia fecal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 24],
        68 => ['name' => 'Consulta de psicología', 'length' => 10, 'type' => 'F', 'required' => true, 'fixed_value' => '1845-01-01', 'correction' => 'fixed'],
        69 => ['name' => 'Fecha de tamizaje auditivo neonatal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 37, 'depends_on' => ['age']],

        70 => [
            'name' => 'Suministro de fortificación casera en primera infancia',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 1 => 'Sí', 16 => 'Tradición', 17 => 'Condición de salud', 18 => 'Negación', 20 => 'Otras razones', 21 => 'Registro no evaluado'],
            'age_months' => [6, 23],
        ],

        71 => [
            'name' => 'Suministro de vitamina A en primera infancia',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 1 => 'Sí', 16 => 'Tradición', 17 => 'Condición de salud', 18 => 'Negación', 20 => 'Otras razones', 21 => 'Registro no evaluado'],
            'age_months' => [24, 60],
        ],

        72 => ['name' => 'Fecha de toma LDL', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 92],
        73 => ['name' => 'Fecha de toma PSA', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 109, 'depends_on' => ['sex', 'age']],

        74 => [
            'name' => 'Preservativos entregados a pacientes con ITS',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
        ],

        75 => ['name' => 'Fecha de tamizaje visual neonatal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 38, 'depends_on' => ['age']],
        76 => ['name' => 'Fecha de atención en salud bucal por odontología', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates],

        77 => [
            'name' => 'Suministro de hierro en primera infancia',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 1 => 'Sí', 16 => 'Tradición', 17 => 'Condición de salud', 18 => 'Negación', 20 => 'Otras razones', 21 => 'Registro no evaluado'],
            'age_months' => [24, 59],
        ],

        78 => ['name' => 'Fecha de antígeno de superficie hepatitis B', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 79],

        79 => [
            'name' => 'Resultado antígeno de superficie hepatitis B',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 4 => 'Reactivo', 5 => 'No reactivo', 21 => 'Riesgo no evaluado'],
            'paired_date' => 78,
        ],

        80 => ['name' => 'Fecha toma prueba tamizaje para sífilis', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 81],

        81 => [
            'name' => 'Resultado prueba tamizaje para sífilis',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 4 => 'Positivo', 5 => 'Negativo', 21 => 'Riesgo no evaluado'],
            'paired_date' => 80,
        ],

        82 => ['name' => 'Fecha toma prueba para VIH', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 83],

        83 => [
            'name' => 'Resultado prueba para VIH',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 4 => 'Reactivo', 5 => 'No reactivo', 21 => 'Riesgo no evaluado'],
            'paired_date' => 82,
        ],

        84 => ['name' => 'Fecha de TSH neonatal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 85, 'depends_on' => ['age']],

        85 => [
            'name' => 'Resultado de TSH neonatal',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 4 => 'Alterado', 5 => 'Normal', 21 => 'Riesgo no evaluado'],
            'paired_date' => 84,
            'depends_on' => ['age'],
        ],

        86 => [
            'name' => 'Tamizaje del cáncer de cuello uterino',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Citología cérvico uterina',
                2 => 'Prueba ADN-VPH',
                3 => 'Técnica de inspección visual',
                4 => 'ADN-VPH y citología',
                16 => 'No se realiza por tradición',
                17 => 'No se realiza por condición de salud',
                18 => 'No se realiza por negación',
                19 => 'Datos de contacto no actualizados',
                20 => 'No se realiza por otras razones',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => ['sex', 'age'],
            'paired_date' => 87,
        ],

        87 => ['name' => 'Fecha de tamizaje cáncer de cuello uterino', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 86, 'depends_on' => ['sex', 'age']],

        88 => [
            'name' => 'Resultado tamizaje cáncer de cuello uterino',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'ASC-US',
                2 => 'ASC-H',
                3 => 'LEI bajo grado',
                4 => 'LEI alto grado',
                5 => 'LEI alto grado sospechosa de infiltración',
                6 => 'Carcinoma de células escamosas',
                7 => 'Células endocervicales atípicas',
                8 => 'Células endometriales atípicas',
                9 => 'Células glandulares atípicas',
                10 => 'Endocervicales atípicas sospechosas de neoplasia',
                11 => 'Endometriales atípicas sospechosas de neoplasia',
                12 => 'Glandulares atípicas sospechosas de neoplasia',
                13 => 'Adenocarcinoma endocervical in situ',
                14 => 'Adenocarcinoma endocervical',
                15 => 'Adenocarcinoma endometrial',
                16 => 'Otras neoplasias',
                17 => 'Negativa para lesión intraepitelial o neoplasia',
                18 => 'Inadecuada para lectura',
                19 => 'Positivo ADN-VPH / inspección visual',
                20 => 'Negativo ADN-VPH / inspección visual',
                21 => 'Riesgo no evaluado',
            ],
            'depends_on' => [86, 87],
        ],

        89 => [
            'name' => 'Calidad en la muestra de citología cervicouterina',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Satisfactoria ZT presente',
                2 => 'Satisfactoria ZT ausente',
                3 => 'Insatisfactoria',
                4 => 'Rechazada',
                999 => 'No se tiene el dato',
            ],
            'depends_on' => [86, 87],
        ],

        90 => [
            'name' => 'Código habilitación IPS tamizaje cáncer de cuello uterino',
            'length' => 12,
            'type' => 'N',
            'required' => true,
            'reference_table' => 'REPS',
            'special_values' => [999 => 'No se tiene el dato', 0 => 'No aplica'],
            'depends_on' => [86, 87],
        ],

        91 => ['name' => 'Fecha de colposcopia', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates],

        92 => [
            'name' => 'Resultado de LDL',
            'length' => 12,
            'type' => 'N',
            'required' => true,
            'special_values' => [998 => 'Riesgo no evaluado', 0 => 'No aplica'],
            'paired_date' => 72,
        ],

        93 => ['name' => 'Fecha de biopsia cervicouterina', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 94],

        94 => [
            'name' => 'Resultado de biopsia cervicouterina',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Negativo para neoplasia',
                3 => 'NIC bajo grado',
                4 => 'NIC alto grado',
                5 => 'Neoplasia microinfiltrante',
                6 => 'Neoplasia infiltrante',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 93,
        ],

        95 => [
            'name' => 'Resultado de HDL',
            'length' => 12,
            'type' => 'N',
            'required' => true,
            'special_values' => [998 => 'Riesgo no evaluado', 0 => 'No aplica'],
            'paired_date' => 111,
        ],

        96 => ['name' => 'Fecha de toma de mamografía', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 97, 'depends_on' => ['sex', 'age']],

        97 => [
            'name' => 'Resultado de mamografía',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'BIRADS 0',
                2 => 'BIRADS 1',
                3 => 'BIRADS 2',
                4 => 'BIRADS 3',
                5 => 'BIRADS 4',
                6 => 'BIRADS 5',
                7 => 'BIRADS 6',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 96,
            'depends_on' => ['sex', 'age'],
        ],

        98 => [
            'name' => 'Resultado de triglicéridos',
            'length' => 12,
            'type' => 'N',
            'required' => true,
            'special_values' => [998 => 'Riesgo no evaluado', 0 => 'No aplica'],
            'paired_date' => 118,
        ],

        99 => ['name' => 'Fecha toma biopsia de mama', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 101],

        100 => [
            'name' => 'Fecha resultado biopsia de mama',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => [
                '1800-01-01' => 'No se tiene el dato',
                '1845-01-01' => 'No aplica',
            ],
            'paired_result' => 101,
        ],

        101 => [
            'name' => 'Resultado de biopsia de mama',
            'length' => 3,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                0 => 'No aplica',
                1 => 'Benigna',
                2 => 'Atípica',
                3 => 'Malignidad sospechosa/probable',
                4 => 'Maligna',
                5 => 'No satisfactoria',
                21 => 'Riesgo no evaluado',
            ],
            'paired_dates' => [99, 100],
        ],

        102 => [
            'name' => 'COP por persona',
            'length' => 12,
            'type' => 'N',
            'required' => true,
            'special_values' => [
                21 => 'Riesgo no evaluado',
                0 => 'No aplica',
            ],
            'custom_validator' => 'cop_12_digits',
        ],

        103 => ['name' => 'Fecha de toma hemoglobina', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 104],

        104 => [
            'name' => 'Resultado de hemoglobina',
            'length' => 4,
            'type' => 'D',
            'required' => true,
            'special_values' => [998 => 'Riesgo no evaluado', 0 => 'No aplica'],
            'paired_date' => 103,
        ],

        105 => ['name' => 'Fecha de toma glicemia basal', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 57],
        106 => ['name' => 'Fecha de toma creatinina', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 107],

        107 => [
            'name' => 'Resultado de creatinina',
            'length' => 4,
            'type' => 'D',
            'required' => true,
            'special_values' => [998 => 'Riesgo no evaluado', 0 => 'No aplica'],
            'paired_date' => 106,
        ],

        108 => [
            'name' => 'Preservativos entregados a pacientes con ITS',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'fixed_value' => '1845-01-01',
            'correction' => 'fixed',
        ],

        109 => [
            'name' => 'Resultado de PSA',
            'length' => 4,
            'type' => 'D',
            'required' => true,
            'special_values' => [998 => 'Riesgo no evaluado', 0 => 'No aplica'],
            'paired_date' => 73,
            'depends_on' => ['sex', 'age'],
        ],

        110 => ['name' => 'Fecha de toma tamizaje hepatitis C', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 42],
        111 => ['name' => 'Fecha de toma HDL', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 95],
        112 => ['name' => 'Fecha toma baciloscopia diagnóstico', 'length' => 10, 'type' => 'F', 'required' => true, 'format' => 'Y-m-d', 'special_values' => $specialDates, 'paired_result' => 113],

        113 => [
            'name' => 'Resultado de baciloscopia diagnóstico',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [
                1 => 'Negativa',
                2 => 'Positiva',
                3 => 'En proceso',
                4 => 'No',
                21 => 'Riesgo no evaluado',
            ],
            'paired_date' => 112,
        ],

        114 => [
            'name' => 'Clasificación del riesgo cardiovascular',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 4 => 'Alto', 5 => 'Bajo', 6 => 'Moderado', 21 => 'Riesgo no evaluado'],
        ],

        115 => [
            'name' => 'Tratamiento para sífilis gestacional',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => [14, 15],
        ],

        116 => [
            'name' => 'Tratamiento para sífilis congénita',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica'],
            'depends_on' => [15, 'age'],
        ],

        117 => [
            'name' => 'Clasificación del riesgo metabólico',
            'length' => 2,
            'type' => 'N',
            'required' => true,
            'allowed' => [0 => 'No aplica', 4 => 'Alto', 5 => 'Bajo', 6 => 'Moderado', 21 => 'Riesgo no evaluado'],
        ],

        118 => [
            'name' => 'Fecha de toma triglicéridos',
            'length' => 10,
            'type' => 'F',
            'required' => true,
            'format' => 'Y-m-d',
            'special_values' => $specialDates,
            'paired_result' => 98,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grupos reutilizables de reglas
    |--------------------------------------------------------------------------
    */

    'rule_groups' => [
        'paired_date_result' => [
            [29, 30],
            [31, 32],
            [62, 27],
            [62, 28],
            [63, 40],
            [64, 22],
            [65, 48],
            [66, 36],
            [67, 24],
            [69, 37],
            [72, 92],
            [73, 109],
            [75, 38],
            [78, 79],
            [80, 81],
            [82, 83],
            [84, 85],
            [87, 86],
            [93, 94],
            [96, 97],
            [103, 104],
            [105, 57],
            [106, 107],
            [110, 42],
            [111, 95],
            [112, 113],
            [118, 98],
        ],

        'laboratory_results' => [57, 92, 95, 98, 104, 107, 109],

        'date_fields_with_special_values' => [
            51, 52, 53, 55, 56, 62, 63, 64, 65, 66, 67, 69,
            72, 73, 75, 76, 78, 80, 82, 84, 87, 91, 93, 96,
            99, 103, 105, 106, 110, 111, 112, 118,
        ],

        'age_sensitive' => [
            14, 15, 16, 17, 19, 22, 23, 26, 27, 28, 37, 38,
            39, 40, 41, 43, 44, 45, 46, 48, 62, 63, 64, 65,
            69, 70, 71, 73, 75, 77, 84, 85, 86, 87, 96, 97,
            109, 116,
        ],

        'sex_sensitive' => [
            14, 22, 23, 26, 33, 35, 53, 54, 55, 56, 58, 59,
            60, 61, 64, 73, 86, 87, 88, 89, 90, 96, 97, 109,
            115,
        ],
    ],
];
