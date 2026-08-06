<?php

namespace App\Services\Informe202;

use App\Services\Informe202\Parsers\ErrorParserInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;

class ProtegerErrorParser implements ErrorParserInterface
{
    /**
     * Traduce los códigos propios de Proteger a los códigos
     * lógicos que ya utiliza el motor de reglas de la Resolución 202.
     *
     * @var array<string, string>
     */
    private array $normalizedCodes = [
        '13' => '223',
        '14' => '244',
        '21' => '232',
        '15' => '379',
        '23' => '507',
        '36' => '518',

        '40' => '524',
        '41' => '525',
        '42' => '526',
        '44' => '528',
        '45' => '529',
        '46' => '530',

        '69' => '534',
        '72' => '537',
        '84' => '549',
        '85' => '550',
        '91' => '556',
        '93' => '560',
        '97' => '564',
        '101' => '568',
        '105' => '572',

        '95' => '560',
        '96' => '560',
        '99' => '564',
        '100' => '564',
        '103' => '568',
        '104' => '568',
        '107' => '572',
        '108' => '572',

        '110' => '575',
        '137' => '583',

        '161' => '584',
        '162' => '585',

        '223' => '402',

        '293' => '618',
        '294' => '619',

        '317' => '626',

        '325' => '630',
        '326' => '631',

        /*
         * Se mantienen sin traducir porque el motor puede
         * resolverlos por variable o por texto.
         */
        '143' => '143',
        '173' => '173',
        '207' => '329',
        '229' => '670',
        '250' => '250',
        '264' => '285',
        '268' => '285',
        '283' => '076',
        '285' => '285',
        '353' => '353',
        '402' => '402',
        '1111' => '1111',

        '356' => '641',
        '368' => '646',
        '373' => '651',
        '374' => '652',
    ];

    /**
     * Variables que deben evaluarse para cada código lógico.
     *
     * @var array<string, array<int, int>>
     */
    private array $variablesByCode = [
        '076' => [86, 87, 88, 89, 90],
        '223' => [14],
        '232' => [112, 113],
        '244' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '250' => [82, 83],
        '226' => [76, 102],
        '343' => [76, 102],
        '379' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '507' => [112, 113],
        '518' => [24, 67],

        '524' => [62],
        '525' => [27],
        '526' => [27, 62],
        '528' => [62],
        '529' => [28],
        '530' => [28, 62],

        '534' => [35, 56, 58],
        '537' => [36, 66],
        '549' => [40, 63],
        '550' => [40, 63],
        '556' => [42],

        '560' => [43, 44, 45, 46, 52],
        '564' => [43, 44, 45, 46, 52],
        '568' => [43, 44, 45, 46, 52],
        '572' => [43, 44, 45, 46, 52],

        '575' => [47],
        '583' => [51],

        '584' => [57, 105],
        '585' => [57, 105],

        '402' => [76],

        '618' => [92],
        '619' => [72, 92],

        '626' => [97],

        '630' => [98],
        '631' => [98, 118],

        '641' => [104],
        '646' => [106, 107],
        '651' => [73, 109],
        '652' => [73, 109],

        '353' => [104],

        '143' => [53],
        '1111' => [3],
        '173' => [62],
        '285' => [86, 87, 88, 89, 90],
        '226' => [76, 102],
        '343' => [76, 102],
    ];

    /**
     * Variables relacionadas que se entregan como contexto.
     *
     * @var array<string, array<int, int>>
     */
    private array $relatedVariablesByCode = [
        '076' => [2, 10, 86, 87, 88, 89, 90],
        '223' => [10, 14],
        '232' => [18, 112, 113],
        '244' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '250' => [82, 83],
        '226' => [76, 102],
        '343' => [76, 102],
        '379' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
        '507' => [18, 112, 113],
        '518' => [24, 67, 114],

        '524' => [27, 62],
        '525' => [27, 62],
        '526' => [27, 62],
        '528' => [28, 62],
        '529' => [28, 62],
        '530' => [28, 62],

        '534' => [35, 52, 56, 58],
        '537' => [36, 66],
        '549' => [40, 63],
        '550' => [40, 63],
        '556' => [42, 110],

        '560' => [43, 44, 45, 46, 52],
        '564' => [43, 44, 45, 46, 52],
        '568' => [43, 44, 45, 46, 52],
        '572' => [43, 44, 45, 46, 52],

        '575' => [14, 47, 86, 88],
        '583' => [14, 51],

        '584' => [57, 105, 114],
        '585' => [57, 105],

        '402' => [76, 102],

        '618' => [72, 92],
        '619' => [72, 92, 114],

        '626' => [96, 97],

        '630' => [98, 118],
        '631' => [98, 114, 118],

        '641' => [103, 104],
        '646' => [106, 107, 114],
        '651' => [10, 73, 109],
        '652' => [10, 73, 109],

        '353' => [103, 104],

        '143' => [53],
        '1111' => [3, 4, 9],
        '173' => [27, 28, 62],
        '285' => [2, 86, 87, 88, 89, 90],
        '226' => [76, 102],
        '343' => [76, 102],
    ];


    /**
     * Reconoce el significado del error aunque Proteger cambie
     * el código numérico o el texto exacto del campo.
     *
     * El orden es importante: primero se detectan los casos
     * más específicos para evitar coincidencias ambiguas.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $semanticRules = [
        /*
         * Inconsistencia de afiliación contra BDUA/BDEX.
         * Debe conservarse como pendiente manual.
         */
        'affiliation_manual' => [
            'logical_code' => '2',
            'codes' => ['2'],
            'variables' => [3],
            'related_variables' => [3, 4, 9],
            'keywords' => [
                'identificacion afiliado',
                'afiliado no identificado',
                'no fue identificado en el sistema',
                'bdua',
                'bdex',
                'verificar afiliacion',
            ],
            'require_keyword' => true,
        ],

        /*
         * Tipo de documento incompatible con la edad.
         */
        'identification_by_age' => [
            'logical_code' => '1111',
            'codes' => ['1111', '253'],
            'variables' => [3],
            'related_variables' => [3, 4, 9],
            'keywords' => [
                'tipo de identificacion',
                'tipo identificacion',
                'tipo de documento',
                'registro civil',
                'tarjeta de identidad',
                'documento segun edad',
                'identificacion segun edad',
            ],
            'require_keyword' => true,
        ],

        /*
         * Bloque cervical inconsistente:
         * 86, 87, 88, 89 y 90.
         */
        'cervical_screening_block' => [
            'logical_code' => '285',
            'codes' => ['264', '267', '268', '285'],
            'variables' => [86, 87, 88, 89, 90],
            'related_variables' => [2, 86, 87, 88, 89, 90],
            'keywords' => [
                'tamizaje de cancer de cuello uterino',
                'fecha de tamizaje de cancer de cuello uterino',
                'resultado de tamizaje de cancer de cuello uterino',
                'calidad de la muestra',
                'calidad en la muestra',
                'cuello uterino',
            ],
        ],

        /*
         * Validación del bloque cervical por edad.
         */
        'cervical_screening_by_age' => [
            'logical_code' => '076',
            'codes' => ['283'],
            'variables' => [86, 87, 88, 89, 90],
            'related_variables' => [2, 10, 86, 87, 88, 89, 90],
            'keywords' => [
                'codigo habilitacion ips tamizaje',
                'ips tamizaje cancer de cuello uterino',
                'tamizaje cuello uterino por edad',
            ],
        ],

        /*
         * Salud bucal y COP por persona.
         */
        'oral_health_block' => [
            'logical_code' => '343',
            'codes' => ['223', '226', '343'],
            'variables' => [76, 102],
            'related_variables' => [76, 102],
            'keywords' => [
                'atencion en salud bucal',
                'cop por persona',
                'profesional en odontologia',
                'salud bucal',
            ],
        ],

        /*
         * Escala abreviada de desarrollo.
         */
        'development_scale_block' => [
            'logical_code' => '560',
            'codes' => [
                '93',
                '95',
                '96',
                '97',
                '99',
                '100',
                '101',
                '103',
                '104',
                '105',
                '107',
                '108',
            ],
            'variables' => [43, 44, 45, 46, 52],
            'related_variables' => [43, 44, 45, 46, 52],
            'keywords' => [
                'motricidad gruesa',
                'motricidad fino adaptativa',
                'personal social',
                'audicion y lenguaje',
                'audicion lenguaje',
                'escala abreviada de desarrollo',
            ],
        ],

        /*
         * Lactancia materna según condición de gestación.
         */
        'lactation_support' => [
            'logical_code' => '583',
            'codes' => ['137'],
            'variables' => [51],
            'related_variables' => [14, 51],
            'keywords' => [
                'promocion atencion y apoyo lactancia materna',
                'apoyo lactancia materna',
            ],
        ],

        /*
         * Sintomático respiratorio y baciloscopia.
         */
        'respiratory_block' => [
            'logical_code' => '232',
            'codes' => ['21'],
            'variables' => [112, 113],
            'related_variables' => [18, 112, 113],
            'keywords' => [
                'sintomatico respiratorio',
                'baciloscopia',
            ],
        ],

        /*
         * Variables relacionadas con no gestante.
         */
        'non_pregnant_block' => [
            'logical_code' => '244',
            'codes' => ['14'],
            'require_code' => true,
            'variables' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
            'related_variables' => [14, 23, 33, 35, 56, 58, 59, 60, 61],
            'keywords' => [
                'gestante',
                'no es gestante',
                'variables relacionadas con la gestacion',
            ],
        ],
    ];

    /**
     * Alias básicos para errores que Proteger expresa por nombre
     * de campo y que no requieren traducción de código.
     *
     * @var array<string, int>
     */
    private array $fieldAliases = [
        'gestante' => 14,
        'resultado prueba mini mental state' => 16,
        'sintomatico respiratorio' => 18,
        'resultado sangre oculta' => 24,
        'resultado prueba sangre oculta' => 24,
        'resultado de la prueba de sangre oculta en materia fecal tamizaje ca de colon' => 24,
        'resultado del tacto rectal' => 22,
        'peso en kilogramos' => 30,
        'peso en kilogramos toda la poblacion' => 30,
        'talla en centimetros' => 32,
        'talla en centimetros toda la poblacion' => 32,
        'fecha probable de parto' => 33,
        'resultado colonoscopia tamizaje' => 36,
        'resultado de colonoscopia tamizaje' => 36,
        'resultado tamizaje vale' => 40,
        'fecha tamizaje vale' => 63,
        'suministro de metodo anticonceptivo' => 54,
        'fecha suministro metodo anticonceptivo' => 55,
        'fecha atencion salud bucal' => 76,
        'fecha de atencion en salud bucal por profesional en odontologia' => 76,
        'resultado antigeno superficie hepatitis b' => 79,
        'resultado de antigeno de superficie hepatitis b toda la poblacion' => 79,
        'resultado prueba para vih' => 83,
        'resultado de prueba para vih' => 83,
        'tamizaje cancer cuello uterino' => 86,
        'tamizaje del cancer de cuello uterino' => 86,
        'fecha tamizaje cancer cuello uterino' => 87,
        'fecha toma mamografia' => 96,
        'fecha de toma de mamografia' => 96,
        'cop por persona' => 102,
        'fortificacion casera' => 70,
        'suministro de fortificacion casera en la primera infancia 6 a 23 meses' => 70,
        'fecha consulta valoracion integral' => 52,
        'fecha primera consulta prenatal' => 56,
        'fecha ultimo control prenatal' => 58,
        'fecha glicemia basal' => 105,
        'resultado glicemia basal' => 57,
        'fecha toma hemoglobina' => 103,
        'resultado hemoglobina' => 104,
        'fecha creatinina' => 106,
        'resultado creatinina' => 107,
        'fecha psa' => 73,
        'resultado psa' => 109,
        'nivel educativo' => 13,
        'fecha atencion salud bucal' => 76,
        'cop por persona' => 102,
    ];

    public function supports(string $eps): bool
    {
        return Str::of($eps)
            ->ascii()
            ->lower()
            ->trim()
            ->toString() === 'proteger';
    }

    public function parse(string $filePath): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException(
                'No se encontró el reporte de errores de Proteger.'
            );
        }

        $content = file_get_contents($filePath);

        if ($content === false || trim($content) === '') {
            throw new RuntimeException(
                'El reporte de errores de Proteger está vacío.'
            );
        }

        $encoding = mb_detect_encoding(
            $content,
            ['UTF-8', 'Windows-1252', 'ISO-8859-1'],
            true
        ) ?: 'Windows-1252';

        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding(
                $content,
                'UTF-8',
                $encoding
            );
        }

        libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8">' . $content,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();

        if (! $loaded) {
            throw new RuntimeException(
                'No fue posible interpretar el reporte de Proteger.'
            );
        }

        $xpath = new DOMXPath($document);
        $rows = $xpath->query('//tr[td]');

        $errors = [];

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);

            if ($cells->length < 5) {
                continue;
            }

            $archivo = $this->clean(
                $cells->item(0)?->textContent
            );

            $originalCode = $this->normalizeCode(
                $this->clean(
                    $cells->item(1)?->textContent
                )
            );

            $rowNumber = $this->clean(
                $cells->item(2)?->textContent
            );

            $field = $this->clean(
                $cells->item(3)?->textContent
            );

            $message = $this->clean(
                $cells->item(4)?->textContent
            );

            if (
                ! is_numeric($originalCode)
                || ! is_numeric($rowNumber)
            ) {
                continue;
            }

            $normalizedCode =
                $this->normalizedCodes[$originalCode]
                ?? $originalCode;

            $normalizedFieldAndMessage = $this->normalizeText(
                $field . ' ' . $message
            );

            $semanticMatch = $this->resolveSemanticRule(
                originalCode: $originalCode,
                field: $field,
                message: $message
            );

            /*
             * La coincidencia semántica tiene prioridad sobre el
             * código numérico, excepto en casos estructurales muy
             * específicos como CODIGO_REPS y nivel educativo.
             */
            if (
                $originalCode === '2'
                && str_contains(
                    $normalizedFieldAndMessage,
                    'codigo reps'
                )
            ) {
                $variables = [2];
                $normalizedCode = '2';
                $semanticMatch = null;
            } elseif (
                $originalCode === '402'
                && str_contains(
                    $normalizedFieldAndMessage,
                    'nivel educativo'
                )
            ) {
                $variables = [13];
                $semanticMatch = null;
            } elseif ($semanticMatch !== null) {
                $normalizedCode =
                    $semanticMatch['logical_code'];

                $variables =
                    $semanticMatch['variables'];
            } else {
                $variables =
                    $this->variablesByCode[$normalizedCode]
                    ?? [];
            }

            if ($variables === []) {
                $inferredVariable = $this->inferVariableFromField(
                    $field
                );

                if ($inferredVariable !== null) {
                    $variables = [$inferredVariable];
                }
            }

            /*
             * Si todavía no se identifica la variable, conserva el error
             * para que VariableResolver intente resolverla por campo/mensaje.
             */
            if ($variables === []) {
                $errors[] = $this->buildError(
                    archivo: $archivo,
                    originalCode: $originalCode,
                    normalizedCode: $normalizedCode,
                    rowNumber: (int) $rowNumber,
                    field: $field,
                    message: $message,
                    variable: null,
                    relatedVariables:
                        $semanticMatch['related_variables']
                        ?? (
                            $this->relatedVariablesByCode[$normalizedCode]
                            ?? []
                        )
                );

                continue;
            }

            /*
             * Genera una entrada por cada variable afectada.
             * Esto permite corregir bloques completos como gestación,
             * desarrollo, glicemia o PSA.
             */
            foreach ($variables as $variable) {
                $errors[] = $this->buildError(
                    archivo: $archivo,
                    originalCode: $originalCode,
                    normalizedCode: $normalizedCode,
                    rowNumber: (int) $rowNumber,
                    field: $field,
                    message: $message,
                    variable: $variable,
                    relatedVariables:
                        $originalCode === '402'
                            ? [13]
                            : (
                                $originalCode === '2'
                                && $variable === 2
                                    ? [2]
                                    : (
                                        $semanticMatch['related_variables']
                                        ?? (
                                            $this->relatedVariablesByCode[$normalizedCode]
                                            ?? $variables
                                        )
                                    )
                            )
                );
            }
        }

        return $errors;
    }

    private function buildError(
        string $archivo,
        string $originalCode,
        string $normalizedCode,
        int $rowNumber,
        string $field,
        string $message,
        ?int $variable,
        array $relatedVariables
    ): array {
        return [
            'archivo' => $archivo,
            'codigo_original' => $originalCode,
            'codigo' => $normalizedCode,
            'fila' => $rowNumber,
            'registro' => $rowNumber,
            'campo' => $field,
            'mensaje' => $message,
            'variable' => $variable,
            'variables_relacionadas' => array_values(
                array_unique(
                    array_map(
                        static fn (mixed $item): int => (int) $item,
                        $relatedVariables
                    )
                )
            ),
            'origen' => 'proteger',
        ];
    }


    private function resolveSemanticRule(
        string $originalCode,
        string $field,
        string $message
    ): ?array {
        $combined = $this->normalizeText(
            $field . ' ' . $message
        );

        foreach (
            $this->semanticRules
            as $logicalRule => $definition
        ) {
            $codes = array_map(
                static fn (mixed $code): string =>
                    (string) $code,
                $definition['codes'] ?? []
            );

            $matchesCode = in_array(
                $originalCode,
                $codes,
                true
            );

            $matchesKeyword = false;

            foreach (
                $definition['keywords'] ?? []
                as $keyword
            ) {
                $normalizedKeyword = $this->normalizeText(
                    (string) $keyword
                );

                if (
                    $normalizedKeyword !== ''
                    && str_contains(
                        $combined,
                        $normalizedKeyword
                    )
                ) {
                    $matchesKeyword = true;

                    break;
                }
            }

            $requireKeyword =
                (bool) (
                    $definition['require_keyword']
                    ?? false
                );

            $requireCode =
                (bool) (
                    $definition['require_code']
                    ?? false
                );

            if (
                $requireCode
                && ! $matchesCode
            ) {
                continue;
            }

            /*
             * Reglas sensibles, como identificación por edad,
             * exigen coincidencia textual para no interpretar un
             * código genérico 1111 como cualquier otro campo.
             */
            if (
                $requireKeyword
                && ! $matchesKeyword
            ) {
                continue;
            }

            if (
                ! $matchesCode
                && ! $matchesKeyword
            ) {
                continue;
            }

            return [
                'logical_rule' =>
                    $logicalRule,

                'logical_code' =>
                    (string) (
                        $definition['logical_code']
                        ?? $originalCode
                    ),

                'variables' =>
                    array_values(
                        array_map(
                            static fn (mixed $item): int =>
                                (int) $item,
                            $definition['variables']
                            ?? []
                        )
                    ),

                'related_variables' =>
                    array_values(
                        array_map(
                            static fn (mixed $item): int =>
                                (int) $item,
                            $definition['related_variables']
                            ?? []
                        )
                    ),
            ];
        }

        return null;
    }

    private function inferVariableFromField(
        string $field
    ): ?int {
        $normalizedField = $this->normalizeText(
            $field
        );

        if ($normalizedField === '') {
            return null;
        }

        foreach ($this->fieldAliases as $alias => $variable) {
            if (
                str_contains($normalizedField, $alias)
                || str_contains($alias, $normalizedField)
            ) {
                return $variable;
            }
        }

        return null;
    }

    private function normalizeCode(
        string $value
    ): string {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return (string) ((int) $value);
    }

    private function normalizeText(
        string $value
    ): string {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function clean(?string $value): string
    {
        $value = html_entity_decode(
            $value ?? '',
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return trim($value ?? '');
    }
}