<?php

namespace App\Services\Informe202\Parsers;

use Illuminate\Support\Str;
use RuntimeException;

class DusakawiErrorParser implements ErrorParserInterface
{
    /**
     * Relación entre códigos de Dusakawi y la variable principal
     * de la Resolución 202 que debe evaluar el motor.
     *
     * Las variables secundarias se conservan aparte en
     * "variables_relacionadas".
     */
    private array $codeVariables = [
        '064' => [71],
        '076' => [86, 87, 88, 89, 90],
        '099' => [107],

        '125' => [51],
        '127' => [53],
        '128' => [55],

        '223' => [14],
        '227' => [16],
        '232' => [113],
        '244' => [14, 23, 33, 35, 56, 58, 59, 60, 61],

        '329' => [71],
        '352' => [86, 87, 88],
        '379' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '402' => [76],

        '503' => [16, 52],

        '507' => [112, 113],

        '518' => [37, 67],

        '524' => [62],
        '525' => [27],
        '526' => [27, 62],

        '528' => [62],
        '529' => [28],
        '530' => [28, 62],

        '534' => [35, 56, 58],
        '537' => [36, 66],
        '542' => [37, 69],
        '354' => [88, 89],
        '413' => [103, 104],
        '439' => [103, 104],
        '597' => [76, 102],
        '634' => [76, 102],
        '640' => [103, 104],

        '549' => [40],
        '550' => [40, 63],
        '551' => [40, 63],

        '553' => [42, 110],
        '556' => [42],

        '560' => [43, 44, 45, 46, 52],
        '564' => [43, 44, 45, 46, 52],
        '568' => [43, 44, 45, 46, 52],
        '572' => [43, 44, 45, 46, 52],

        '575' => [47],

        '583' => [51],
        '584' => [57, 105],
        '585' => [57],
        '586' => [105, 57],
        '588' => [60],
        '597' => [76, 102],

        '611' => [86, 87, 88],

        '618' => [92],
        '619' => [72, 92],

        '626' => [97],

        '630' => [98],
        '631' => [98, 118],
        '634' => [76, 102],
        '636' => [102],
        '637' => [102],

        '638' => [103],
        '641' => [104],

        '646' => [106, 107],

        '651' => [73, 109], 
        '652' => [73, 109],

        '655' => [114],
        '665' => [117],

        '670' => [77],
    ];

    /**
     * Variables relacionadas con algunos errores.
     *
     * No se generan automáticamente como errores independientes.
     * Se entregan al motor para que una regla especializada pueda
     * decidir cuáles campos debe modificar.
     */
    private array $relatedVariablesByCode = [
        '076' => [86, 87, 88, 89, 90],
        '232' => [112, 113],
        '244' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '352' => [87, 88],
        '379' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '402' => [76, 102],

        '503' => [16, 52],

        '507' => [18, 112, 113],

        '518' => [37, 67, 114],

        '524' => [27, 62],
        '525' => [27, 62],
        '526' => [27, 62],

        '528' => [28, 62],
        '529' => [28, 62],
        '530' => [28, 62],

        '534' => [35, 52, 56, 58],
        '537' => [36, 66],
        '542' => [37, 69],
        '354' => [88, 89],
        '413' => [103, 104],
        '439' => [103, 104],
        '597' => [76, 102],
        '634' => [76, 102],
        '640' => [103, 104],

        '550' => [40, 63],
        '551' => [40, 63],

        '553' => [42, 110],
        '556' => [42, 110],

        '560' => [43, 44, 45, 46, 52],
        '564' => [43, 44, 45, 46, 52],
        '568' => [43, 44, 45, 46, 52],
        '572' => [43, 44, 45, 46, 52],

        '575' => [14, 47, 86, 88],

        '584' => [57, 105],
        '585' => [57, 105],
        '586' => [57, 105, 114],

        '611' => [86, 88],

        '618' => [72, 92],
        '619' => [72, 92],

        '626' => [96, 97],

        '630' => [98, 118],
        '631' => [98, 118],
        '636' => [102],
        '637' => [102],

        '638' => [103, 104],
        '641' => [103, 104],

        '646' => [106, 107],

        '651' => [73, 109],
        '652' => [73, 109],
        '655' => [114],
        '665' => [117],
    ];

    /**
     * Alias usados en errores estructurales de Dusakawi.
     */
    private array $fieldAliases = [
        'primer_apellido_usuario' => 5,
        'segundo_apellido_usuario' => 6,
        'primer_nombre_usuario' => 7,
        'segundo_nombre_usuario' => 8,

        'resultado_glicemia_basal' => 57,
        'resultado_ldl' => 92,
        'resultado_hdl' => 95,
        'resultado_trigliceridos' => 98,
        'resultado_hemoglobina' => 104,
        'resultado_creatinina' => 107,
        'resultado_psa' => 109,

        'clasificacion_riesgo_cardiovascular' => 114,
        'clasificacion_riesgo_metabolico' => 117,
    ];

    public function supports(string $eps): bool
    {
        return strtolower(trim($eps)) === 'dusakawi';
    }

    public function parse(string $filePath): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException(
                'No se encontró el reporte TXT de Dusakawi.'
            );
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException(
                'No fue posible leer el reporte de Dusakawi.'
            );
        }

        /*
         * Convierte el archivo a UTF-8 cuando viene en ANSI.
         */
        $content = mb_convert_encoding(
            $content,
            'UTF-8',
            [
                'UTF-8',
                'Windows-1252',
                'ISO-8859-1',
            ]
        );

        $lines = preg_split(
            '/\R/u',
            trim($content)
        );

        $errors = [];

        foreach ($lines ?: [] as $rawLine) {
            $rawLine = trim($rawLine);

            if ($rawLine === '') {
                continue;
            }

            $header = $this->parseHeader(
                $rawLine
            );

            if ($header === null) {
                continue;
            }

            $messages = $this->splitMessages(
                $header['remaining_text']
            );

            foreach ($messages as $message) {
                $normalizedErrors =
                    $this->normalizeMessage(
                        header: $header,
                        message: $message
                    );

                foreach (
                    $normalizedErrors
                    as $normalizedError
                ) {
                    $errors[] = $normalizedError;
                }
            }
        }

        return $this->removeDuplicates(
            $errors
        );
    }

    private function parseHeader(
        string $line
    ): ?array {
        /*
         * Ejemplos:
         *
         * Linea 1,CC,22444148 Fecha Nacimiento...
         * Linea 182,RC,1243538086,El afiliado...
         */
        if (! preg_match(
            '/^Linea\s+(\d+)\s*,\s*([^,]+)\s*,\s*([^,\s]+)\s*(.*)$/iu',
            $line,
            $matches
        )) {
            return null;
        }

        $birthDate = null;

        $remainingText = trim(
            $matches[4] ?? ''
        );

        if (preg_match(
            '/Fecha\s+Nacimiento\s+del\s+Sistema\s*:\s*'
            . '(\d{2}\/\d{2}\/\d{4})/iu',
            $remainingText,
            $birthMatches
        )) {
            $birthDate = $this->normalizeDate(
                $birthMatches[1]
            );

            $remainingText = trim(
                preg_replace(
                    '/Fecha\s+Nacimiento\s+del\s+Sistema\s*:\s*'
                    . '\d{2}\/\d{2}\/\d{4}\s*,?/iu',
                    '',
                    $remainingText,
                    1
                ) ?? $remainingText
            );
        }

        return [
            'linea' =>
                (int) $matches[1],

            'tipo_identificacion' =>
                strtoupper(
                    trim($matches[2])
                ),

            'identificacion' =>
                trim($matches[3]),

            'fecha_nacimiento_sistema' =>
                $birthDate,

            'remaining_text' =>
                ltrim(
                    $remainingText,
                    " ,"
                ),
        ];
    }

    private function splitMessages(
        string $text
    ): array {
        if ($text === '') {
            return [];
        }

        /*
         * No se usa explode(',') porque los mensajes contienen comas.
         * Solo se divide cuando comienza un nuevo error reconocible.
         */
        $pattern =
            '/(?='
            . '(?:Error|Warning)\s*\d{3}\b'
            . '|Error\s*:\s*La\s+estructura'
            . '|El\s+Campo\b'
            . '|El\s+valor\s+del\s+campo\b'
            . '|El\s+afiliado\s+en\s+mención\b'
            . ')/iu';

        $parts = preg_split(
            $pattern,
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (! is_array($parts)) {
            return [];
        }

        $messages = array_map(
            fn (string $part): string =>
                trim(
                    $part,
                    " ,\t\n\r\0\x0B"
                ),
            $parts
        );

        return array_values(
            array_filter(
                $messages,
                fn (string $part): bool =>
                    $part !== ''
            )
        );
    }

    private function normalizeMessage(
        array $header,
        string $message
    ): array {
        $code = $this->extractCode(
            $message
        );

        $variables = [];

        if (
            $code !== null
            && isset(
                $this->codeVariables[$code]
            )
        ) {
            $variables =
                $this->codeVariables[$code];
        }

        $relatedVariables =
            $code !== null
            && isset(
                $this->relatedVariablesByCode[
                    $code
                ]
            )
                ? $this->relatedVariablesByCode[
                    $code
                ]
                : $variables;

        /*
         * Errores como:
         *
         * Error: La estructura del archivo no es válida.
         * Variable resultado_hdl
         */
        $fieldName = $this->extractFieldName(
            $message
        );

        if (
            $fieldName !== null
            && isset(
                $this->fieldAliases[
                    $fieldName
                ]
            )
        ) {
            $variables = [
                $this->fieldAliases[
                    $fieldName
                ],
            ];

            $relatedVariables =
                $variables;
        }

        /*
         * Errores que escriben directamente:
         *
         * campo 11
         * campo 13
         */
        if (preg_match(
            '/campo\s+(\d{1,3})\b/iu',
            $message,
            $variableMatch
        )) {
            $directVariable =
                (int) $variableMatch[1];

            if (
                $directVariable >= 0
                && $directVariable <= 118
            ) {
                $variables = [
                    $directVariable,
                ];

                $relatedVariables =
                    $variables;
            }
        }

        /*
         * Algunos mensajes nombran el campo sin usar código.
         */
        if ($variables === []) {
            $inferredVariable =
                $this->inferVariableFromText(
                    $message
                );

            if (
                $inferredVariable !== null
            ) {
                $variables = [
                    $inferredVariable,
                ];

                $normalizedMessage = Str::of($message)
                    ->ascii()
                    ->lower()
                    ->toString();

                if (
                    $inferredVariable === 3
                    && str_contains(
                        $normalizedMessage,
                        'el afiliado en mencion no fue identificado en el sistema'
                    )
                ) {
                    $relatedVariables = [3, 4];
                } else {
                    $relatedVariables = $variables;
                }
            }
        }

        /*
         * Los casos administrativos no deben inventar una variable.
         */
        if ($variables === []) {
            return [[
                'codigo' => $code,

                'fila' =>
                    $header['linea'],

                'registro' =>
                    $header['linea'],

                'linea' =>
                    $header['linea'],

                'variable' =>
                    null,

                'variables_relacionadas' =>
                    [],

                'campo' =>
                    $fieldName,

                'mensaje' =>
                    $message,

                'tipo_identificacion' =>
                    $header[
                        'tipo_identificacion'
                    ],

                'identificacion' =>
                    $header[
                        'identificacion'
                    ],

                'fecha_nacimiento_sistema' =>
                    $header[
                        'fecha_nacimiento_sistema'
                    ],

                'origen' =>
                    'dusakawi',

                'requiere_revision_manual' =>
                    true,
            ]];
        }

        $normalized = [];

        foreach (
            array_unique($variables)
            as $variable
        ) {
            $definition = config(
                "resolucion202.fields.{$variable}",
                []
            );

            $normalized[] = [
                'codigo' =>
                    $code,

                'fila' =>
                    $header['linea'],

                'registro' =>
                    $header['linea'],

                'linea' =>
                    $header['linea'],

                'variable' =>
                    $variable,

                'variables_relacionadas' =>
                    array_values(
                        array_unique(
                            $relatedVariables
                        )
                    ),

                'campo' =>
                    $definition['name']
                    ?? $fieldName
                    ?? "Variable {$variable}",

                'mensaje' =>
                    $message,

                'tipo_identificacion' =>
                    $header[
                        'tipo_identificacion'
                    ],

                'identificacion' =>
                    $header[
                        'identificacion'
                    ],

                'fecha_nacimiento_sistema' =>
                    $header[
                        'fecha_nacimiento_sistema'
                    ],

                'origen' =>
                    'dusakawi',

                'requiere_revision_manual' =>
                    false,
            ];
        }

        return $normalized;
    }

    private function extractCode(
        string $message
    ): ?string {
        if (preg_match(
            '/\b(?:Error|Warning)\s*(\d{3})\b/iu',
            $message,
            $matches
        )) {
            return str_pad(
                $matches[1],
                3,
                '0',
                STR_PAD_LEFT
            );
        }

        return null;
    }

    private function extractFieldName(
        string $message
    ): ?string {
        if (preg_match(
            '/Variable\s+([a-zA-Z0-9_\-]+)/iu',
            $message,
            $matches
        )) {
            return $this->normalizeFieldAlias(
                $matches[1]
            );
        }

        if (preg_match(
            '/El\s+Campo\s+([a-zA-Z0-9_\-]+)/iu',
            $message,
            $matches
        )) {
            return $this->normalizeFieldAlias(
                $matches[1]
            );
        }

        return null;
    }

    private function inferVariableFromText(
        string $message
    ): ?int {
        $text = Str::of($message)
            ->ascii()
            ->lower()
            ->toString();

        /*
         * Mensaje administrativo sin código de error:
         * el afiliado no fue identificado por posible cambio
         * de tipo de documento.
         *
         * La variable principal es la 3 (tipo de identificación).
         * La variable 4 solo se conserva como relacionada.
         */
        if (str_contains(
            $text,
            'el afiliado en mencion no fue identificado en el sistema'
        )) {
            return 3;
        }

if (
            str_contains(
                $text,
                'el peso de los ninos menores de 2 anos debe ser mayor o igual a 1 kg'
            )
        ) {
            return 30;
        }

        $patterns = [
    'resultado hdl' => 95,
    'resultado hemoglobina' => 104,
    'resultado creatinina' => 107,
    'resultado psa' => 109,

    'clasificacion del riesgo cardiovascular' => 114,
    'clasificacion de riesgo cardiovascular' => 114,
    'clasificacion de riesgo metabolico' => 117,

    'codigo pertenencia etnica' => 11,
    'codigo de pertenencia etnica' => 11,

    'codigo de nivel educativo' => 13,
    'codigo nivel educativo' => 13,

    'primer apellido del usuario' => 5,
    'primer apellido usuario' => 5,

    'segundo apellido del usuario' => 6,
    'segundo apellido usuario' => 6,

    'primer nombre del usuario' => 7,
    'primer nombre usuario' => 7,

    'segundo nombre del usuario' => 8,
    'segundo nombre usuario' => 8,
];

        foreach (
            $patterns
            as $needle => $variable
        ) {
            if (
                str_contains(
                    $text,
                    $needle
                )
            ) {
                return $variable;
            }
        }

        return null;
    }

    private function normalizeFieldAlias(
        string $field
    ): string {
        return Str::of($field)
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }

    private function normalizeDate(
        string $date
    ): ?string {
        $parsed =
            \DateTimeImmutable::createFromFormat(
                'd/m/Y',
                $date
            );

        if (! $parsed) {
            return null;
        }

        return $parsed->format(
            'Y-m-d'
        );
    }

    private function removeDuplicates(
        array $errors
    ): array {
        $unique = [];

        foreach ($errors as $error) {
            $key = implode('|', [
                $error['linea']
                    ?? $error['fila']
                    ?? '',

                $error['tipo_identificacion']
                    ?? '',

                $error['identificacion']
                    ?? '',

                $error['codigo']
                    ?? '',

                $error['variable']
                    ?? '',

                md5(
                    (string) (
                        $error['mensaje']
                        ?? ''
                    )
                ),
            ]);

            $unique[$key] = $error;
        }

        return array_values(
            $unique
        );
    }


}