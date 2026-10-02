<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;
use Illuminate\Support\Str;

class DusakawiCodeRule implements RuleInterface
{
    private const NO_APLICA_DATE = '1845-01-01';

    private const NO_REALIZATION_DATES = [
        '1800-01-01',
        '1805-01-01',
        '1810-01-01',
        '1825-01-01',
        '1830-01-01',
        '1835-01-01',
    ];

public function supports(
    int $variable,
    array $definition,
    array $record,
    array $error
): bool {
    $origin = Str::of(
        (string) ($error['origen'] ?? '')
    )
        ->ascii()
        ->lower()
        ->trim()
        ->toString();

    if (! in_array($origin, ['dusakawi', 'proteger'], true)) {
        return false;
    }

    if (! empty($error['codigo'])) {
        return true;
    }

    $message = Str::of(
        (string) ($error['mensaje'] ?? '')
    )
        ->ascii()
        ->lower()
        ->toString();

    if (
        $variable === 3
        && str_contains(
            $message,
            'el afiliado en mencion no fue identificado en el sistema'
        )
    ) {
        return true;
    }

    if (
        $variable === 11
        && (
            str_contains($message, 'codigo pertenencia etnica')
            || str_contains($message, 'codigo de pertenencia etnica')
            || str_contains($message, 'campo 11')
        )
    ) {
        return true;
    }

    if (
        $variable === 12
        && strtoupper(
            trim((string) (
                $record['variables'][12]
                ?? ''
            ))
        ) === 'NA'
    ) {
        return true;
    }

    if (
        $variable === 12
        && (
            str_contains($message, 'codigo de ocupacion')
            || str_contains($message, 'campo 12')
        )
    ) {
        return true;
    }

    if (
        $variable === 13
        && (
            str_contains($message, 'codigo de nivel educativo')
            || str_contains($message, 'campo 13')
        )
    ) {
        return true;
    }

if (in_array($variable, [5, 6, 7, 8, 19, 30, 31, 32, 43, 44, 45, 46, 52, 86, 87, 88, 89, 95, 104, 107, 111], true)) {
    return true;
}

    return false;
}

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision {
        $code = str_pad(
            (string) ($error['codigo'] ?? ''),
            3,
            '0',
            STR_PAD_LEFT
        );

        $currentValue =
            $record['variables'][$variable] ?? null;
            $message = mb_strtolower(
    (string) ($error['mensaje'] ?? '')
);

if (
    $variable === 12
    && (
        str_contains($message, 'código de ocupación')
        || str_contains($message, 'codigo de ocupacion')
        || str_contains($message, 'campo 12')
    )
) {
    return $this->occupationCode(
        variable: $variable,
        currentValue: $currentValue
    );
}

$message = Str::of(
    (string) ($error['mensaje'] ?? '')
)
    ->ascii()
    ->lower()
    ->toString();

if (
    $variable === 13
    && (
        str_contains(
            $message,
            'codigo de nivel educativo'
        )
        || str_contains(
            $message,
            'campo 13'
        )
        || str_contains(
            $message,
            'nivel educativo'
        )
    )
) {
    return $this->educationLevelCode(
        variable: $variable,
        currentValue: $currentValue
    );
}

if (
    $variable === 11
    && (
        str_contains(
            $message,
            'codigo pertenencia etnica'
        )
        || str_contains(
            $message,
            'codigo de pertenencia etnica'
        )
        || str_contains(
            $message,
            'campo 11'
        )
    )
) {
    return $this->ethnicGroupCode(
        variable: $variable,
        currentValue: $currentValue
    );
}


if (
    $variable === 3
    && str_contains(
        $message,
        'el afiliado en mencion no fue identificado en el sistema'
    )
) {
    return $this->normalizeIdentificationTypeByAge(
        variable: $variable,
        currentValue: $currentValue,
        record: $record
    );
}

        if (in_array($variable, [5, 6, 7, 8], true)) {
            return $this->normalizePersonName(
                variable: $variable,
                currentValue: $currentValue
            );
        }

        /*
         * Proteger: consumo de tabaco en menores de 12 años.
         *
         * Variable 19 = Consumo de tabaco.
         * En menores de 12 años debe registrarse 98, No aplica.
         */
        if ($variable === 19) {
            $ageMonths = $record['age']['months'] ?? null;
            $ageYears = $record['age']['years'] ?? null;

            $isUnderTwelve = is_numeric($ageMonths)
                ? (float) $ageMonths < 144
                : (
                    is_numeric($ageYears)
                    && (float) $ageYears < 12
                );

            if ($isUnderTwelve) {
                return $this->automaticOrValid(
                    variable: 19,
                    currentValue: $currentValue,
                    newValue: 98,
                    reason:
                        'La persona es menor de 12 años. '
                        . 'El consumo de tabaco debe registrarse '
                        . 'como 98, No aplica.'
                );
            }
        }

        /*
         * Proteger - Error 309:
         *
         * 95  = Resultado de HDL
         * 111 = Fecha de toma de HDL
         * 114 = Clasificación del riesgo cardiovascular
         *
         * En menores de 29 años sin riesgo cardiovascular
         * identificado, el bloque HDL debe quedar como No aplica:
         *
         * 95  = 0
         * 111 = 1845-01-01
         *
         * La regla se reconoce tanto por el código 309 como por
         * cualquiera de las dos variables relacionadas.
         */
        if ($code === '309') {
            $variables = $record['variables'] ?? [];

            $risk = trim(
                (string) ($variables[114] ?? '')
            );

            $ageMonths = $record['age']['months'] ?? null;
            $ageYears = $record['age']['years'] ?? null;

            $isUnderTwentyNine = is_numeric($ageMonths)
                ? (float) $ageMonths < 348
                : (
                    is_numeric($ageYears)
                    && (float) $ageYears < 29
                );

            if (
                $isUnderTwentyNine
                && $risk === '0'
            ) {
                /*
                 * Proteger suele reportar el Error 309 sobre la
                 * variable 95. En ese caso se corrige el resultado.
                 *
                 * Si también reporta la variable 111, se corrige la
                 * fecha relacionada.
                 */
                if ($variable === 111) {
                    return $this->automaticOrValid(
                        variable: 111,
                        currentValue: $currentValue,
                        newValue: self::NO_APLICA_DATE,
                        reason:
                            'Error 309 de Proteger: la persona es menor '
                            . 'de 29 años y no tiene riesgo cardiovascular '
                            . 'identificado. La fecha de toma de HDL debe '
                            . 'registrarse como 1845-01-01, No aplica.'
                    );
                }

                return $this->automaticOrValid(
                    variable: 95,
                    currentValue: $currentValue,
                    newValue: 0,
                    reason:
                        'Error 309 de Proteger: la persona es menor '
                        . 'de 29 años y no tiene riesgo cardiovascular '
                        . 'identificado. El resultado de HDL debe '
                        . 'registrarse como 0, No aplica.'
                );
            }

            /*
             * Si el Error 309 llegó pero no se puede confirmar la edad
             * o la ausencia de riesgo, no se oculta como válido.
             */
            if ($code === '309') {
                /*
                 * Si no es menor de 29 años sin riesgo, entonces HDL sí
                 * aplica. Como no existe una fecha ni resultado clínico
                 * real disponible, se usa el patrón Sin dato:
                 *
                 * 95  = 998
                 * 111 = 1800-01-01
                 */
                if ($variable === 111) {
                    return $this->automaticOrValid(
                        variable: 111,
                        currentValue: $currentValue,
                        newValue: '1800-01-01',
                        reason:
                            'Error 309 de Proteger: la persona tiene '
                            . '29 años o más, o presenta riesgo '
                            . 'cardiovascular. La toma de HDL sí aplica; '
                            . 'sin fecha real disponible se registra '
                            . '1800-01-01, Sin dato.'
                    );
                }

                return $this->automaticOrValid(
                    variable: 95,
                    currentValue: $currentValue,
                    newValue: 998,
                    reason:
                        'Error 309 de Proteger: la persona tiene '
                        . '29 años o más, o presenta riesgo '
                        . 'cardiovascular. El HDL sí aplica; sin '
                        . 'resultado clínico disponible se registra 998.'
                );
            }
        }

        /*
         * Nuevos rechazos del período septiembre de 2026. Se evalúan
         * antes de cualquier normalización genérica para evitar
         * sobrescribir un resultado clínico correcto.
         */
        /*
         * DUSAKAWI 635 / 678: el COP requiere 0, 21 o exactamente
         * 12 dígitos. Reconstruir solo un cero inicial inequívoco en
         * valores numéricos de 11 dígitos y fecha odontológica real.
         * No declarar edentulismo a partir del texto ambiguo '3200'.
         */
        if (
            in_array($code, ['635', '678'], true)
            && $variable === 102
            && Str::of((string) ($error['origen'] ?? ''))
                ->ascii()->lower()->trim()->toString() === 'dusakawi'
        ) {
            return $this->normalizeCop635678($record, $code);
        }

        if ($code === '506' && $variable === 113) {
            return $this->bacilloscopyNotSymptomatic506($record);
        }

        if ($code === '625' && $variable === 95) {
            return $this->hdlConditional625($record, $error);
        }

        if ($code === '546' && $variable === 75) {
            return $this->neonatalDateWithoutExamination(
                record: $record,
                dateVariable: 75,
                resultVariable: 38,
                label: 'tamizaje visual neonatal'
            );
        }

        if ($code === '581' && $variable === 65) {
            return $this->neonatalDateWithoutExamination(
                record: $record,
                dateVariable: 65,
                resultVariable: 48,
                label: 'oximetría pre y posductal'
            );
        }

        /*
         * Un peso registrado no puede corregirse mediante una
         * suposición decimal sin verificar la fuente clínica.
         */
        if (
            $variable === 30
            && str_contains($message, 'el peso de los')
        ) {
            return $this->verifiedWeightOnly($record, $error);
        }

        if (
            $variable === 95
            && ! in_array($code, ['309', '623', '624', '625'], true)
        ) {
            return $this->normalizeHdlResult(
                variable: $variable,
                currentValue: $currentValue
            );
        }

        if (
    $variable === 104
    && ! in_array($code, ['413', '439', '640'], true)
) {
    return $this->normalizeHemoglobinResult(
        variable: $variable,
        currentValue: $currentValue
    );
}


        /*
         * Errores estructurales sin código explícito.
         */
        if (
            $variable === 30
            && str_contains(
                $message,
                'el peso de los ninos de 5 a 12 anos'
            )
        ) {
            $weight = (float) str_replace(
                ',',
                '.',
                trim((string) $currentValue)
            );

            foreach ([$weight / 10, $weight / 100] as $candidate) {
                if ($candidate >= 9 && $candidate <= 80) {
                    return $this->automaticOrValid(
                        variable: 30,
                        currentValue: $currentValue,
                        newValue: rtrim(
                            rtrim(
                                number_format($candidate, 2, '.', ''),
                                '0'
                            ),
                            '.'
                        ),
                        reason:
                            'Se detectó un desplazamiento decimal evidente '
                            . 'en el peso infantil.'
                    );
                }
            }

            return $this->manual(
                variable: 30,
                currentValue: $currentValue,
                reason:
                    'El peso está fuera del rango permitido y no se pudo '
                    . 'deducir una corrección segura.'
            );
        }

        if (
            $variable === 32
            && str_contains(
                $message,
                'la talla de los adultos mayores a 18 anos'
            )
        ) {
            $height = (int) trim((string) $currentValue);

            if ($height >= 30 && $height <= 99) {
                $height += 100;
            } elseif ($height >= 100 && $height < 130) {
                $height += 100;
            }

            if ($height >= 130 && $height <= 225) {
                return $this->automaticOrValid(
                    variable: 32,
                    currentValue: $currentValue,
                    newValue: $height,
                    reason:
                        'Se corrigió la talla porque presentaba un '
                        . 'desplazamiento de centena evidente.'
                );
            }

            return $this->manual(
                variable: 32,
                currentValue: $currentValue,
                reason:
                    'La talla no permite deducir una corrección segura.'
            );
        }

        if (
            $variable === 107
            && str_contains(
                $message,
                'resultado_creatinina tiene una longitud mayor'
            )
        ) {
            $value = (float) str_replace(
                ',',
                '.',
                trim((string) $currentValue)
            );

            if ($value > 25 && ($value / 10) >= 0.2 && ($value / 10) <= 25) {
                return $this->automaticOrValid(
                    variable: 107,
                    currentValue: $currentValue,
                    newValue: rtrim(
                        rtrim(
                            number_format($value / 10, 2, '.', ''),
                            '0'
                        ),
                        '.'
                    ),
                    reason:
                        'Se corrigió un desplazamiento decimal evidente '
                        . 'en el resultado de creatinina.'
                );
            }
        }

        return match ($code) {

            '043', '382', '423' => $this->heightDateWithoutHeight(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '623' => $this->hdlDateWithoutResult(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '624' => $this->hdlSpecialDateResultBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '309' => $this->hdlNoAplicaPorEdad(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '076' => $this->cervicalIpsByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '125', '127' => $this->futureServiceDate(
                variable: $variable,
                currentValue: $currentValue,
                code: $code
            ),

            '126' => $this->futureIntegralAssessmentDate(
                variable: $variable,
                currentValue: $currentValue
            ),

            '128' => $this->correctContraceptiveSupplyDate(
                  variable: $variable,
                  currentValue: $currentValue,
                  record: $record
),

           '503' => $this->normalizeMiniMentalBlock(
                  variable: $variable,
                  currentValue: $currentValue,
                  record: $record
),

            '558', '560', '562', '564', '566', '568', '570', '572' =>
                $this->normalizeDevelopmentScaleBlock(
                    variable: $variable,
                    currentValue: $currentValue,
                    record: $record
                ),

            '611', '614', '352' => $this->normalizeCervicalScreeningBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
),

            '379' => $this->gestationRelatedFields(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
),
             '507' => $this->normalizeRespiratorySymptomaticBlock(
                   variable: $variable,
                   currentValue: $currentValue,
                   record: $record
),
            '517' => $this->sangreOcultaPorEdad(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '518' => $this->sangreOcultaNoAplica(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),
            '223' => $this->validateGestationByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '227' => $this->miniMentalByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '232' => $this->normalizeRespiratorySymptomaticPositiveBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),
            '244' => $this->normalizeNonPregnantFields(
                 variable: $variable,
                 currentValue: $currentValue,
                 record: $record
),
            '524', '528' => $this->agudezaConResultadoSinFecha(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                resultVariable: $code === '524' ? 27 : 28
            ),

            '525' => $this->agudezaNoRealizada(
                variable: $variable,
                currentValue: $currentValue,
                expectedVariable: 27
            ),

            '529' => $this->agudezaNoRealizada(
                variable: $variable,
                currentValue: $currentValue,
                expectedVariable: 28
            ),

            '526' => $this->agudezaMenorTres(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                resultVariable: 27
            ),

            '530' => $this->agudezaMenorTres(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                resultVariable: 28
            ),

            '341', '600' => $this->hepatitisBSurfaceAntigenBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '534' => $this->gestationalRiskWithoutPrenatalDate(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '537' => $this->colonoscopiaTamizaje(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '542' => $this->auditoryNeonatalBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '354' => $this->cytologyQualityBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '413', '439', '640' => $this->hemoglobinSpecialDateBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '597', '634' => $this->oralHealthCopBlock(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '549' => $this->normalizeValeAllowedValues(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '550' => $this->valeNoAplica(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '551' => $this->valeMenorTrece(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '553' => $this->hepatitisConFecha(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '556' => $this->hepatitisNoAplica(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '575' => $this->validateAblativeTreatment(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
),

            '583' => $this->lactationSupportByGestation(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '584' => $this->normalizeGlycemiaDateResult(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '585' => $this->specialDateResult(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                dateVariable: 105,
                resultVariable: 57
            ),

            '586' => $this->glicemiaNoAplicaPorEdad(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '618' => $this->specialDateResult(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                dateVariable: 72,
                resultVariable: 92
            ),

            '619' => $this->ldlNoAplicaPorEdad(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '626' => $this->mamografiaNoRealizada(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '630' => $this->specialDateResult(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                dateVariable: 118,
                resultVariable: 98
            ),

            '631' => $this->trigliceridosNoAplicaPorEdad(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '638' => $this->hemoglobinGirlsTenToSeventeen(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '641' => $this->hemoglobinNoAplica(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '099', '371' => $this->creatinineClinicalResultRequired(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '645' => $this->specialDateResultBidirectional(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                dateVariable: 106,
                resultVariable: 107
            ),

            '646' => $this->creatinineUnderTwentyNine(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '651' => $this->femalePsa(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '652' => $this->maleUnderFortyPsa(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '655' => $this->validateCardiovascularRisk(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '665' => $this->validateMetabolicRisk(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '328' => $this->infancySupplementByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                expectedVariable: 70,
                minMonths: 6,
                maxMonths: 23,
                label: 'fortificación casera'
            ),

            '329' => $this->infancySupplementByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                expectedVariable: 71,
                minMonths: 24,
                maxMonths: 71,
                label: 'vitamina A'
            ),

            '402' => $this->oralHealthByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '636', '637' => $this->validateCopComponentsByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '670' => $this->infancySupplementByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record,
                expectedVariable: 77,
                minMonths: 24,
                maxMonths: 71,
                label: 'hierro'
            ),

            '143' => $this->protegerContraceptionCounselingByAge(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '226' => $this->protegerOralHealthNoAplica(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),

            '343' => $this->protegerOralHealthCopWithoutVisit(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
            ),
            '226', '343' => $this->protegerOralHealthCop(
                variable: $variable,
                currentValue: $currentValue,
                record: $record
),

            default => null,
        };
    }

private function validateGestationByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 14) {
        return null;
    }

    $ageYears = $this->ageYears($record);
    $sex = $this->sex($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: 14,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la variable Gestante.'
        );
    }

    /*
     * En hombres, Gestante no aplica.
     */
    if ($sex === 'M') {
        return $this->automaticOrValid(
            variable: 14,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'El sexo registrado es masculino. La variable '
                . 'Gestante debe registrarse como 0, No aplica.'
        );
    }

    /*
     * En mujeres menores de 10 años,
     * Gestante no aplica.
     */
    if ($sex === 'F' && $ageYears < 10) {
        return $this->automaticOrValid(
            variable: 14,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona tiene {$ageYears} años. Al ser mujer "
                . 'menor de 10 años, Gestante debe registrarse '
                . 'como 0, No aplica.'
        );
    }

    /*
     * En mujeres de 10 años o más:
     *
     * 1  = Sí
     * 2  = No
     * 21 = Riesgo no evaluado
     *
     * El valor 0 no debe conservarse. Cuando no existe
     * información para definir Sí o No, se registra 21.
     */
    if ($sex === 'F' && $ageYears >= 10) {
        $gestation = trim(
            (string) $currentValue
        );

        if (in_array($gestation, ['1', '2', '21'], true)) {
            return RuleDecision::valid(
                variable: 14,
                currentValue: $currentValue,
                reason:
                    "La persona tiene {$ageYears} años y el valor "
                    . 'registrado en Gestante es válido.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: 14,
            currentValue: $currentValue,
            newValue: 21,
            reason:
                "La persona es mujer de {$ageYears} años. La variable "
                . 'Gestante estaba registrada como 0, No aplica. '
                . 'Como no existe información suficiente para determinar '
                . 'Sí o No, se reemplazó por 21, Riesgo no evaluado.'
        );
    }

    return $this->manual(
        variable: 14,
        currentValue: $currentValue,
        reason:
            'No fue posible determinar automáticamente el valor '
            . 'de Gestante porque el sexo no está identificado '
            . 'como masculino o femenino.'
    );
}

    private function agudezaNoRealizada(
        int $variable,
        mixed $currentValue,
        int $expectedVariable
    ): ?RuleDecision {
        if ($variable !== $expectedVariable) {
            return null;
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 21,
            reason:
                'La fecha de agudeza visual contiene un comodín de '
                . 'no realización o sin dato; el resultado debe ser 21.'
        );
    }

    private function agudezaMenorTres(
        int $variable,
        mixed $currentValue,
        array $record,
        int $resultVariable
    ): ?RuleDecision {
        $ageYears = $record['age']['years'] ?? null;

        if ($ageYears === null) {
            return RuleDecision::manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'No fue posible calcular la edad para aplicar '
                    . 'la regla de agudeza visual.',
                rule: self::class
            );
        }

        if ($ageYears >= 3) {
            return null;
        }

        if ($variable === $resultVariable) {
            return $this->automaticOrValid(
                variable: $variable,
                currentValue: $currentValue,
                newValue: 0,
                reason:
                    'La persona es menor de 3 años; la agudeza visual '
                    . 'debe registrarse como 0, no aplica.'
            );
        }

        if ($variable === 62) {
            return $this->automaticOrValid(
                variable: $variable,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason:
                    'La persona es menor de 3 años; la fecha de '
                    . 'agudeza visual debe registrarse como no aplica.'
            );
        }

        return null;
    }

private function colonoscopiaTamizaje(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 36:
     * Resultado Colonoscopia Tamizaje.
     *
     * Variable 66:
     * Fecha de la realización de la colonoscopia tamizaje.
     */
    if (! in_array($variable, [36, 66], true)) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la colonoscopia de tamizaje.'
        );
    }

    $result = trim(
        (string) (
            $record['variables'][36]
            ?? ''
        )
    );

    if ($result !== '21') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El Error537 fue reportado, pero la variable 36 '
                . 'Resultado Colonoscopia Tamizaje no contiene 21. '
                . 'Debe revisarse el registro.'
        );
    }

    if ($ageYears >= 50 && $ageYears <= 75) {
        if ($variable === 36) {
            return $this->automaticOrValid(
                variable: 36,
                currentValue: $currentValue,
                newValue: 21,
                reason:
                    "La persona tiene {$ageYears} años y está dentro "
                    . 'del rango de 50 a 75 años. El resultado de '
                    . 'colonoscopia de tamizaje permanece en 21.'
            );
        }

        return $this->automaticOrValid(
            variable: 66,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                "La persona tiene {$ageYears} años y está dentro "
                . 'del rango de 50 a 75 años. Como el resultado '
                . 'de colonoscopia es 21, la fecha debe registrarse '
                . 'como 1800-01-01.'
        );
    }

    if ($variable === 36) {
        return $this->automaticOrValid(
            variable: 36,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona tiene {$ageYears} años y está fuera "
                . 'del rango de 50 a 75 años. La colonoscopia '
                . 'de tamizaje no aplica y el resultado debe ser 0.'
        );
    }

    return $this->automaticOrValid(
        variable: 66,
        currentValue: $currentValue,
        newValue: self::NO_APLICA_DATE,
        reason:
            "La persona tiene {$ageYears} años y está fuera "
            . 'del rango de 50 a 75 años. La colonoscopia '
            . 'de tamizaje no aplica y la fecha debe registrarse '
            . 'como 1845-01-01.'
    );
}

    private function specialDateResult(
        int $variable,
        mixed $currentValue,
        array $record,
        int $dateVariable,
        int $resultVariable
    ): ?RuleDecision {
        if ($variable !== $resultVariable) {
            return null;
        }

        $dateValue = trim(
            (string) (
                $record['variables'][$dateVariable]
                ?? ''
            )
        );

        if (! in_array(
            $dateValue,
            self::NO_REALIZATION_DATES,
            true
        )) {
            return null;
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 998,
            reason:
                "La variable {$dateVariable} contiene una fecha especial "
                . 'de no realización o sin dato; el resultado debe ser 998.'
        );
    }

    private function hemoglobinNoAplica(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        if ($variable !== 104) {
            return null;
        }

        $dateValue = trim(
            (string) (
                $record['variables'][103]
                ?? ''
            )
        );

        if ($dateValue !== self::NO_APLICA_DATE) {
            return null;
        }

        return $this->automaticOrValid(
            variable: 104,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'La fecha de toma de hemoglobina está registrada como '
                . 'no aplica; el resultado debe ser 0.'
        );
    }

    private function creatinineUnderTwentyNine(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        if (! in_array($variable, [106, 107], true)) {
            return null;
        }

        $ageYears = $this->ageYears($record);

        if ($ageYears === null) {
            return $this->manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'No fue posible calcular la edad para validar '
                    . 'la fecha y el resultado de creatinina.'
            );
        }

        $cardiovascularRisk = trim(
            (string) (
                $record['variables'][114]
                ?? ''
            )
        );

        $withoutIdentifiedRisk =
            $cardiovascularRisk === '0';

        if (
            $ageYears < 29
            && $withoutIdentifiedRisk
        ) {
            if ($variable === 107) {
                return $this->automaticOrValid(
                    variable: 107,
                    currentValue: $currentValue,
                    newValue: 0,
                    reason:
                        "La persona tiene {$ageYears} años y no tiene "
                        . 'riesgo cardiovascular identificado. El resultado '
                        . 'de creatinina debe registrarse como 0, No aplica.'
                );
            }

            return $this->automaticOrValid(
                variable: 106,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason:
                    "La persona tiene {$ageYears} años y no tiene "
                    . 'riesgo cardiovascular identificado. La fecha de '
                    . 'toma de creatinina debe registrarse como 1845-01-01.'
            );
        }

        if ($variable === 107) {
            return $this->automaticOrValid(
                variable: 107,
                currentValue: $currentValue,
                newValue: 998,
                reason:
                    "La persona tiene {$ageYears} años o presenta una "
                    . 'clasificación de riesgo cardiovascular que hace '
                    . 'aplicable la toma de creatinina. El resultado debe '
                    . 'registrarse como 998, sin resultado disponible.'
            );
        }

        return $this->automaticOrValid(
            variable: 106,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                "La persona tiene {$ageYears} años o presenta una "
                . 'clasificación de riesgo cardiovascular que hace '
                . 'aplicable la toma de creatinina. La fecha debe '
                . 'registrarse como 1800-01-01.'
        );
    }

    private function femalePsa(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        $sex = strtoupper(
            trim((string) ($record['sex'] ?? ''))
        );

        if ($sex !== 'F') {
            return null;
        }

        return $this->psaNoAplica(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El sexo registrado es femenino; la fecha y el resultado '
                . 'de PSA deben registrarse como no aplica.'
        );
    }

    private function maleUnderFortyPsa(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        $sex = strtoupper(
            trim((string) ($record['sex'] ?? ''))
        );

        $ageYears = $record['age']['years'] ?? null;

        if (
            $sex !== 'M'
            || $ageYears === null
            || $ageYears >= 40
        ) {
            return null;
        }

        return $this->psaNoAplica(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'La persona es hombre menor de 40 años; la fecha y '
                . 'el resultado de PSA deben registrarse como no aplica.'
        );
    }

    private function psaNoAplica(
        int $variable,
        mixed $currentValue,
        string $reason
    ): ?RuleDecision {
        if ($variable === 73) {
            return $this->automaticOrValid(
                variable: 73,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason: $reason
            );
        }

        if ($variable === 109) {
            return $this->automaticOrValid(
                variable: 109,
                currentValue: $currentValue,
                newValue: 0,
                reason: $reason
            );
        }

        return null;
    }


    private function hepatitisNoAplica(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        if ($variable !== 42) {
            return null;
        }

        $date = trim((string) ($record['variables'][110] ?? ''));

        if ($date !== self::NO_APLICA_DATE) {
            return $this->manual(
                $variable,
                $currentValue,
                'La fecha de hepatitis C no está registrada como no aplica.'
            );
        }

        return $this->automaticOrValid(
            variable: 42,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'La fecha de tamizaje de hepatitis C está registrada '
                . 'como no aplica; el resultado también debe ser no aplica.'
        );
    }
private function hepatitisConFecha(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [42, 110], true)) {
        return null;
    }

    $result = trim(
        (string) (
            $record['variables'][42]
            ?? ''
        )
    );

    $date = trim(
        (string) (
            $record['variables'][110]
            ?? ''
        )
    );

    $isRealDate =
        $date !== ''
        && $date !== self::NO_APLICA_DATE
        && ! in_array(
            $date,
            self::NO_REALIZATION_DATES,
            true
        );

    /*
     * Fecha real con resultado 21:
     * se conserva el resultado 21 y la fecha pasa a 1800-01-01.
     */
    if (
        $isRealDate
        && $result === '21'
    ) {
        if ($variable === 42) {
            return $this->automaticOrValid(
                variable: 42,
                currentValue: $currentValue,
                newValue: 21,
                reason:
                    'El resultado de hepatitis C está registrado como 21 '
                    . 'y debe conservarse.'
            );
        }

        return $this->automaticOrValid(
            variable: 110,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                'Existe una fecha real de hepatitis C, pero el resultado '
                . 'está registrado como 21. La fecha se reemplazó por '
                . '1800-01-01, correspondiente a no realización o sin dato.'
        );
    }

    /*
     * Fecha 1800-01-01 con un resultado distinto de 21:
     * el resultado se normaliza a 21.
     */
    if (
        $date === '1800-01-01'
        && $result !== ''
        && $result !== '21'
    ) {
        if ($variable === 110) {
            return $this->automaticOrValid(
                variable: 110,
                currentValue: $currentValue,
                newValue: '1800-01-01',
                reason:
                    'La fecha de hepatitis C ya contiene el comodín '
                    . '1800-01-01.'
            );
        }

        return $this->automaticOrValid(
            variable: 42,
            currentValue: $currentValue,
            newValue: 21,
            reason:
                'La fecha de hepatitis C está registrada como '
                . '1800-01-01; el resultado debe registrarse como 21.'
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La combinación de fecha y resultado de hepatitis C '
            . 'no coincide con los casos que pueden corregirse '
            . 'automáticamente.'
    );
}


private function valeNoAplica(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 40:
     * Resultado de tamizaje VALE.
     *
     * Variable 63:
     * Fecha de tamizaje VALE.
     */
    if (! in_array($variable, [40, 63], true)) {
        return null;
    }

    $age = $record['age'] ?? [];

    $ageMonths = $age['months'] ?? null;
    $ageYears = $age['years'] ?? null;

    /*
     * Sin edad no es seguro aplicar ninguna corrección.
     */
    if (
        ! is_numeric($ageMonths)
        && ! is_numeric($ageYears)
    ) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad de la persona. '
                . 'El Error550 exige confirmar que sea mayor de '
                . '12 años antes de registrar el tamizaje VALE '
                . 'como No aplica. El registro debe revisarse manualmente.'
        );
    }

    /*
     * Se priorizan los meses para no truncar edades como
     * 12 años y 7 meses a solamente 12 años.
     *
     * 12 años = 144 meses.
     */
    /*
     * Para este error se usa la edad cumplida en años.
     * Una persona de 12 años y varios meses todavía tiene
     * 12 años cumplidos y no se considera mayor de 12.
     */
    if (is_numeric($ageYears)) {
        $completedYears = (int) floor((float) $ageYears);
        $displayAge = $completedYears;
    } elseif (is_numeric($ageMonths)) {
        $completedYears = (int) floor((float) $ageMonths / 12);
        $displayAge = $completedYears;
    } else {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad cumplida para validar VALE.'
        );
    }

    $isOlderThanTwelve = $completedYears > 12;

    /*
     * Mayor de 12 años:
     *
     * Resultado = 0
     * Fecha = 1845-01-01
     */
    if ($isOlderThanTwelve) {
        $targetValue = match ($variable) {
            40 => 0,
            63 => self::NO_APLICA_DATE,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                "La persona tiene aproximadamente {$displayAge} años "
                . 'y es mayor de 12 años. El tamizaje VALE debe '
                . 'registrarse como No aplica: resultado igual a 0 '
                . 'y fecha igual a 1845-01-01.'
        );
    }

    /*
     * Tiene 12 años o menos:
     *
     * No corresponde registrar No aplica.
     * Como no existe información clínica real:
     *
     * Resultado = 21
     * Fecha = 1800-01-01
     */
    $targetValue = match ($variable) {
        40 => 21,
        63 => '1800-01-01',
    };

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $targetValue,
        reason:
            "La persona tiene aproximadamente {$displayAge} años "
            . 'y no es mayor de 12 años. El tamizaje VALE no puede '
            . 'registrarse como No aplica. Se normalizó el resultado '
            . 'a 21 y la fecha a 1800-01-01.'
    );
}

    private function valeMenorTrece(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        if (! in_array($variable, [40, 63], true)) {
            return null;
        }

        $ageYears = $this->ageYears($record);

        if ($ageYears === null) {
            return $this->manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'No fue posible calcular la edad para validar '
                    . 'el resultado y la fecha del tamizaje VALE.'
            );
        }

        if ($ageYears < 13) {
            if ($variable === 40) {
                return $this->automaticOrValid(
                    variable: 40,
                    currentValue: $currentValue,
                    newValue: 21,
                    reason:
                        "La persona tiene {$ageYears} años. Al ser menor "
                        . 'de 13 años, el resultado del tamizaje VALE '
                        . 'puede registrarse como 21.'
                );
            }

            return $this->automaticOrValid(
                variable: 63,
                currentValue: $currentValue,
                newValue: '1800-01-01',
                reason:
                    "La persona tiene {$ageYears} años y el resultado "
                    . 'del tamizaje VALE corresponde a 21. La fecha debe '
                    . 'registrarse con el comodín 1800-01-01.'
            );
        }

        if ($variable === 40) {
            return $this->automaticOrValid(
                variable: 40,
                currentValue: $currentValue,
                newValue: 0,
                reason:
                    "La persona tiene {$ageYears} años. Al tener 13 años "
                    . 'o más, el tamizaje VALE no aplica y el resultado '
                    . 'debe registrarse como 0.'
            );
        }

        return $this->automaticOrValid(
            variable: 63,
            currentValue: $currentValue,
            newValue: self::NO_APLICA_DATE,
            reason:
                "La persona tiene {$ageYears} años. Al tener 13 años "
                . 'o más, el tamizaje VALE no aplica y la fecha debe '
                . 'registrarse como 1845-01-01.'
        );
    }

    private function glicemiaConFecha(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        if ($variable !== 57) {
            return null;
        }

        $date = trim((string) ($record['variables'][105] ?? ''));

        if (! $this->isRealReportDate($date)) {
            return null;
        }

        $value = trim((string) $currentValue);

        if ($value !== '' && ! in_array($value, ['0', '21'], true)) {
            return RuleDecision::valid(
                variable: 57,
                currentValue: $currentValue,
                reason: 'Existe una fecha real de glicemia y el resultado contiene un valor informado.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: 57,
            currentValue: $currentValue,
            newValue: 998,
            reason: 'Existe una fecha real de glicemia basal, pero no hay un resultado utilizable. Se registró 998, sin resultado disponible.'
        );
    }

private function glicemiaNoAplicaPorEdad(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variables:
     *
     * 57  = Resultado de glicemia basal
     * 105 = Fecha de toma de glicemia basal
     * 114 = Clasificación del riesgo cardiovascular
     *
     * Error586:
     *
     * Menor de 29 años y sin riesgo cardiovascular:
     * 57  = 0
     * 105 = 1845-01-01
     *
     * Persona de 29 años o más, o con riesgo:
     * 57  = 998
     * 105 = 1800-01-01
     */
    if (! in_array($variable, [57, 105], true)) {
        return null;
    }

    $variables = $record['variables'] ?? [];

    $cardiovascularRisk = trim(
        (string) ($variables[114] ?? '')
    );

    /*
     * Se usa preferiblemente la edad en meses.
     * 29 años equivalen a 348 meses.
     */
    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageMonths)) {
        $isUnderTwentyNine =
            (float) $ageMonths < 348;

        $displayAge = round(
            (float) $ageMonths / 12,
            2
        );
    } elseif (is_numeric($ageYears)) {
        $isUnderTwentyNine =
            (float) $ageYears < 29;

        $displayAge = round(
            (float) $ageYears,
            2
        );
    } else {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la fecha y el resultado de glicemia basal.'
        );
    }

    /*
     * Variable 114 = 0:
     * sin riesgo cardiovascular identificado.
     */
    $withoutCardiovascularRisk =
        $cardiovascularRisk === '0';

    /*
     * Menor de 29 años y sin riesgo:
     * la glicemia no aplica.
     */
    if (
        $isUnderTwentyNine
        && $withoutCardiovascularRisk
    ) {
        if ($variable === 105) {
            return $this->automaticOrValid(
                variable: 105,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason:
                    "La persona tiene {$displayAge} años y la variable "
                    . '114 está registrada como 0, sin riesgo '
                    . 'cardiovascular identificado. La fecha de toma '
                    . 'de glicemia debe registrarse como '
                    . '1845-01-01, No aplica.'
            );
        }

        return $this->automaticOrValid(
            variable: 57,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona tiene {$displayAge} años y la variable "
                . '114 está registrada como 0, sin riesgo '
                . 'cardiovascular identificado. El resultado de '
                . 'glicemia debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Tiene 29 años o más, o existe una clasificación
     * de riesgo distinta de 0.
     *
     * No corresponde registrar No aplica.
     * Como no se dispone del resultado clínico verdadero:
     *
     * Fecha = 1800-01-01
     * Resultado = 998
     */
    if ($variable === 105) {
        return $this->automaticOrValid(
            variable: 105,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                "La persona tiene {$displayAge} años o presenta una "
                . 'clasificación de riesgo cardiovascular diferente '
                . 'de 0. La toma de glicemia sí aplica y la fecha no '
                . 'puede permanecer como 1845-01-01. Como no existe '
                . 'una fecha real disponible, se registra '
                . '1800-01-01, Sin dato.'
        );
    }

    return $this->automaticOrValid(
        variable: 57,
        currentValue: $currentValue,
        newValue: 998,
        reason:
            "La persona tiene {$displayAge} años o presenta una "
            . 'clasificación de riesgo cardiovascular diferente '
            . 'de 0. La glicemia sí aplica y el resultado no puede '
            . 'permanecer como 0, No aplica. Como no existe un '
            . 'resultado clínico disponible, se registra 998.'
    );
}

    private function ldlNoAplicaPorEdad(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        /*
         * Variable 72:
         * Fecha de toma de LDL.
         *
         * Variable 92:
         * Resultado de LDL.
         *
         * Variable 114:
         * Clasificación del riesgo cardiovascular.
         */
        if (! in_array($variable, [72, 92], true)) {
            return null;
        }

        $ageYears = $this->ageYears($record);

        if ($ageYears === null) {
            return $this->manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'No fue posible calcular la edad para validar '
                    . 'la fecha y el resultado de LDL.'
            );
        }

        $cardiovascularRisk = trim(
            (string) (
                $record['variables'][114]
                ?? ''
            )
        );

        /*
         * Código 0 en la variable 114:
         * No aplica / sin riesgo cardiovascular identificado.
         */
        $withoutIdentifiedRisk =
            $cardiovascularRisk === '0';

        /*
         * Menor de 29 años y sin riesgo cardiovascular:
         * - Resultado LDL = 0.
         * - Fecha LDL = 1845-01-01.
         */
        if (
            $ageYears < 29
            && $withoutIdentifiedRisk
        ) {
            if ($variable === 92) {
                return $this->automaticOrValid(
                    variable: 92,
                    currentValue: $currentValue,
                    newValue: 0,
                    reason:
                        "La persona tiene {$ageYears} años y no tiene "
                        . 'riesgo cardiovascular identificado. El resultado '
                        . 'de LDL debe registrarse como 0, No aplica.'
                );
            }

            return $this->automaticOrValid(
                variable: 72,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason:
                    "La persona tiene {$ageYears} años y no tiene "
                    . 'riesgo cardiovascular identificado. La fecha de '
                    . 'toma de LDL debe registrarse como 1845-01-01.'
            );
        }

        /*
         * Persona de 29 años o más, o con riesgo cardiovascular:
         * - Resultado LDL = 21.
         * - Fecha LDL = 1800-01-01.
         */
        if ($variable === 92) {
            return $this->automaticOrValid(
                variable: 92,
                currentValue: $currentValue,
                newValue: 998,
                reason:
                    "La persona tiene {$ageYears} años o presenta una "
                    . 'clasificación de riesgo cardiovascular que hace '
                    . 'aplicable la toma de LDL. El resultado se registra '
                    . 'como 998, sin resultado disponible.'
            );
        }

        return $this->automaticOrValid(
            variable: 72,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                "La persona tiene {$ageYears} años o presenta una "
                . 'clasificación de riesgo cardiovascular que hace '
                . 'aplicable la toma de LDL. La fecha debe registrarse '
                . 'como 1800-01-01.'
        );
    }

    private function trigliceridosNoAplicaPorEdad(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        /*
         * Variable 118:
         * Fecha de toma de triglicéridos.
         *
         * Variable 98:
         * Resultado de triglicéridos.
         *
         * Variable 114:
         * Clasificación del riesgo cardiovascular.
         */
        if (! in_array($variable, [98, 118], true)) {
            return null;
        }

        $ageYears = $this->ageYears($record);

        if ($ageYears === null) {
            return $this->manual(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'No fue posible calcular la edad para validar '
                    . 'la fecha y el resultado de triglicéridos.'
            );
        }

        $cardiovascularRisk = trim(
            (string) (
                $record['variables'][114]
                ?? ''
            )
        );

        /*
         * Código 0 en la variable 114:
         * No aplica / sin riesgo cardiovascular identificado.
         */
        $withoutIdentifiedRisk =
            $cardiovascularRisk === '0';

        /*
         * Menor de 29 años y sin riesgo cardiovascular:
         * - Resultado de triglicéridos = 0.
         * - Fecha de triglicéridos = 1845-01-01.
         */
        if (
            $ageYears < 29
            && $withoutIdentifiedRisk
        ) {
            if ($variable === 98) {
                return $this->automaticOrValid(
                    variable: 98,
                    currentValue: $currentValue,
                    newValue: 0,
                    reason:
                        "La persona tiene {$ageYears} años y no tiene "
                        . 'riesgo cardiovascular identificado. El resultado '
                        . 'de triglicéridos debe registrarse como 0, No aplica.'
                );
            }

            return $this->automaticOrValid(
                variable: 118,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason:
                    "La persona tiene {$ageYears} años y no tiene "
                    . 'riesgo cardiovascular identificado. La fecha de '
                    . 'toma de triglicéridos debe registrarse como '
                    . '1845-01-01.'
            );
        }

        /*
         * Persona de 29 años o más, o con riesgo cardiovascular:
         * - Resultado de triglicéridos = 998.
         * - Fecha de triglicéridos = 1800-01-01.
         */
        if ($variable === 98) {
            return $this->automaticOrValid(
                variable: 98,
                currentValue: $currentValue,
                newValue: 998,
                reason:
                    "La persona tiene {$ageYears} años o presenta una "
                    . 'clasificación de riesgo cardiovascular que hace '
                    . 'aplicable la toma de triglicéridos. El resultado '
                    . 'se registra como 998, sin resultado disponible.'
            );
        }

        return $this->automaticOrValid(
            variable: 118,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                "La persona tiene {$ageYears} años o presenta una "
                . 'clasificación de riesgo cardiovascular que hace '
                . 'aplicable la toma de triglicéridos. La fecha debe '
                . 'registrarse como 1800-01-01.'
        );
    }

    private function laboratorioNoAplicaMenorVeintinueve(
        int $variable,
        mixed $currentValue,
        array $record,
        int $dateVariable,
        int $resultVariable,
        string $label
    ): ?RuleDecision {
        if (
            $variable !== $dateVariable
            && $variable !== $resultVariable
        ) {
            return null;
        }

        $ageYears = $this->ageYears($record);

        if ($ageYears === null) {
            return $this->manual(
                $variable,
                $currentValue,
                "No fue posible calcular la edad para validar {$label}."
            );
        }

        if ($ageYears >= 29) {
            return $this->manual(
                $variable,
                $currentValue,
                "La persona tiene {$ageYears} años. No puede registrarse "
                . "{$label} como no aplica por edad; debe consultarse "
                . 'la fecha o resultado real.'
            );
        }

        if (! $this->hasExplicitNoRisk($record)) {
            return $this->manual(
                $variable,
                $currentValue,
                'La persona es menor de 29 años, pero no fue posible '
                . 'confirmar de forma segura que no tiene riesgo cardiovascular.'
            );
        }

        if ($variable === $dateVariable) {
            return $this->automaticOrValid(
                variable: $dateVariable,
                currentValue: $currentValue,
                newValue: self::NO_APLICA_DATE,
                reason:
                    "La persona es menor de 29 años y no tiene riesgo "
                    . "cardiovascular; la fecha de {$label} debe ser no aplica."
            );
        }

        return $this->automaticOrValid(
            variable: $resultVariable,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona es menor de 29 años y no tiene riesgo "
                . "cardiovascular; el resultado de {$label} debe ser no aplica."
        );
    }

    private function mamografiaNoRealizada(
        int $variable,
        mixed $currentValue,
        array $record
    ): ?RuleDecision {
        if ($variable !== 97) {
            return null;
        }

        $ageYears = $this->ageYears($record);
        $sex = $this->sex($record);

        if ($sex !== 'F') {
            return $this->manual(
                $variable,
                $currentValue,
                'La regla de mamografía indicada aplica a mujeres.'
            );
        }

        if ($ageYears === null || $ageYears < 50) {
            return $this->manual(
                $variable,
                $currentValue,
                'La regla reportada exige que la mujer tenga 50 años o más.'
            );
        }

        $date = trim((string) ($record['variables'][96] ?? ''));

        if (! in_array($date, self::NO_REALIZATION_DATES, true)) {
            return $this->manual(
                $variable,
                $currentValue,
                'La fecha de mamografía no contiene un comodín reconocido '
                . 'de no realización o sin dato.'
            );
        }

        return $this->automaticOrValid(
            variable: 97,
            currentValue: $currentValue,
            newValue: 21,
            reason:
                'La usuaria tiene 50 años o más y la fecha de mamografía '
                . 'contiene un comodín de no realización; el resultado debe ser 21.'
        );
    }

    private function ageYears(array $record): ?int
    {
        $years = $record['age']['years'] ?? null;

        return is_numeric($years)
            ? (int) $years
            : null;
    }

    private function sex(array $record): string
    {
        return strtoupper(
            trim(
                (string) (
                    $record['sex']
                    ?? $record['variables'][10]
                    ?? ''
                )
            )
        );
    }

    private function hasExplicitNoRisk(array $record): bool
    {
        $value = trim(
            (string) ($record['variables'][114] ?? '')
        );

        if ($value === '') {
            return false;
        }

        $allowed = config(
            'resolucion202.fields.114.allowed',
            []
        );

        $label = is_array($allowed)
            ? (string) ($allowed[$value] ?? '')
            : '';

        $normalizedLabel = Str::of($label)
            ->ascii()
            ->lower()
            ->toString();

        if (
            str_contains($normalizedLabel, 'sin riesgo')
            || str_contains($normalizedLabel, 'no aplica')
        ) {
            return true;
        }

        return $allowed === [] && $value === '0';
    }

    private function manual(
        int $variable,
        mixed $currentValue,
        string $reason
    ): RuleDecision {
        return RuleDecision::manual(
            variable: $variable,
            currentValue: $currentValue,
            reason: $reason,
            rule: self::class
        );
    }
private function hdlDateWithoutResult(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [95, 111], true)) {
        return null;
    }

    return $this->automaticBlock(
        record: $record,
        changes: [
            95 => 998,
            111 => '1800-01-01',
        ],
        reason:
            'Existe una inconsistencia entre la fecha y el resultado de '
            . 'HDL. Al no existir un resultado clínico confiable, el bloque '
            . 'se normaliza a resultado 998 y fecha 1800-01-01.'
    );
}

private function hdlSpecialDateResultBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [95, 111], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][111] ?? ''));
    $result = trim((string) ($record['variables'][95] ?? ''));

    if (in_array($date, self::NO_REALIZATION_DATES, true)) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                95 => 998,
                111 => $date,
            ],
            reason:
                'La fecha de HDL contiene un comodín de no realización. '
                . 'Dusakawi exige que el resultado relacionado sea 998.'
        );
    }

    if ($result === '998') {
        return $this->automaticBlock(
            record: $record,
            changes: [
                95 => 998,
                111 => '1800-01-01',
            ],
            reason:
                'El resultado de HDL es 998 y debe estar acompañado por '
                . 'un comodín de no realización. Se usa 1800-01-01.'
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'El Error 624 exige coherencia entre la fecha especial de HDL '
            . 'y el resultado 998, pero la combinación actual no permite '
            . 'deducir una corrección clínica segura.'
    );
}

private function heightDateWithoutHeight(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [31, 32], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][31] ?? ''));
    $height = trim((string) ($record['variables'][32] ?? ''));

    /*
     * Dusakawi no acepta 1845-01-01 en FechaTalla cuando la talla
     * es 999. En el mismo archivo fueron aceptados los registros con
     * la pareja 1800-01-01 / 999, que significa que no se tomó la talla.
     */
    if (in_array($height, ['', '0', '999'], true)) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                31 => '1800-01-01',
                32 => 999,
            ],
            reason:
                'No existe una talla clínica válida. El bloque se normaliza '
                . 'como no tomado usando FechaTalla 1800-01-01 y talla 999, '
                . 'sin inventar una fecha ni una medición.'
        );
    }

    return RuleDecision::valid(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'Existe una talla válida; la fecha se conserva y debe revisarse '
            . 'solo si Dusakawi reporta otra inconsistencia específica.',
        rule: self::class
    );
}

private function occupationCode(
    int $variable,
    mixed $currentValue
): ?RuleDecision {
    if ($variable !== 12) {
        return null;
    }

    $normalizedCurrentValue = strtoupper(
        trim((string) $currentValue)
    );

    /*
     * El archivo fuente puede traer el texto literal "NA" en el
     * campo 12. Como la variable exige un código numérico de cuatro
     * posiciones, "NA" se normaliza al comodín oficial 9999
     * (No se tiene información).
     *
     * Se valida el valor actual y no el código numérico del error,
     * de modo que la corrección siga funcionando aunque Dusakawi
     * cambie la numeración del rechazo.
     */
    if ($normalizedCurrentValue === 'NA') {
        return RuleDecision::automatic(
            variable: 12,
            currentValue: $currentValue,
            newValue: 9999,
            reason:
                'La variable 12 contenía NA. Se reemplazó por 9999, '
                . 'correspondiente a No se tiene información.',
            rule: self::class
        );
    }

    /*
     * Dusakawi indicó que, cuando el código de ocupación
     * reportado no sea válido, la variable 12 debe quedar
     * exactamente en 9999.
     *
     * Esta regla solo se ejecuta sobre registros reportados
     * por la EPS con mensajes de código de ocupación o campo 12.
     */
    return $this->automaticOrValid(
        variable: 12,
        currentValue: $currentValue,
        newValue: 9999,
        reason:
            'Dusakawi reportó que el código de ocupación no es válido. '
            . 'La variable 12 se reemplazó por 9999.'
    );
}

private function educationLevelCode(
    int $variable,
    mixed $currentValue
): ?RuleDecision {
    if ($variable !== 13) {
        return null;
    }

    $newValue = 13;

    if (
        trim((string) $currentValue)
        === (string) $newValue
    ) {
        return RuleDecision::valid(
            variable: 13,
            currentValue: $currentValue,
            reason:
                'El nivel educativo ya está registrado con '
                . 'el código 13, correspondiente a Ninguno.',
            rule: self::class
        );
    }

    return RuleDecision::automatic(
        variable: 13,
        currentValue: $currentValue,
        newValue: $newValue,
        reason:
            'El código de nivel educativo reportado no es válido. '
            . 'Se reemplazó por 13, correspondiente a Ninguno, '
            . 'como valor de respaldo cuando no se dispone '
            . 'de información confiable.',
        rule: self::class
    );
}

private function ethnicGroupCode(
    int $variable,
    mixed $currentValue
): ?RuleDecision {
    if ($variable !== 11) {
        return null;
    }

    /*
     * Código 6:
     * Ninguna de las anteriores.
     */
    $newValue = 6;

    if (
        trim((string) $currentValue)
        === (string) $newValue
    ) {
        return RuleDecision::valid(
            variable: 11,
            currentValue: $currentValue,
            reason:
                'El código de pertenencia étnica ya está registrado '
                . 'como 6, correspondiente a Ninguna de las anteriores.',
            rule: self::class
        );
    }

    return RuleDecision::automatic(
        variable: 11,
        currentValue: $currentValue,
        newValue: $newValue,
        reason:
            'El código de pertenencia étnica reportado no es válido. '
            . 'Se reemplazó por 6, correspondiente a Ninguna de las '
            . 'anteriores, porque no existe información confiable '
            . 'para determinar otro grupo étnico.',
        rule: self::class
    );
}

private function riskClassification(
    int $variable,
    mixed $currentValue,
    array $record,
    int $expectedVariable,
    string $label
): ?RuleDecision {
    if ($variable !== $expectedVariable) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                "No fue posible calcular la edad para corregir "
                . "la clasificación del riesgo {$label}."
        );
    }

    /*
     * Valores oficiales permitidos:
     * 0  = No aplica
     * 4  = Alto
     * 5  = Bajo
     * 6  = Moderado
     * 21 = Riesgo no evaluado
     */
    $allowedValues = [
        '0',
        '4',
        '5',
        '6',
        '21',
    ];

    $currentText = trim(
        (string) $currentValue
    );


if ($ageYears < 30) {
    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: 0,
        reason:
            "La persona tiene {$ageYears} años. Al ser menor "
            . 'de 30 años, la clasificación del riesgo '
            . "{$label} debe registrarse como 0, No aplica."
    );
}

return $this->automaticOrValid(
    variable: $variable,
    currentValue: $currentValue,
    newValue: 21,
    reason:
        "La persona tiene {$ageYears} años. Al tener 30 años "
        . "o más, la clasificación del riesgo {$label} "
        . 'se registra como 21, Riesgo no evaluado.'
);
    /*
     * En personas de 29 años o más no podemos deducir
     * si el riesgo es alto, bajo o moderado.
     *
     * Cuando no existe una evaluación confiable,
     * se utiliza 21: Riesgo no evaluado.
     */
    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: 21,
        reason:
            "La persona tiene {$ageYears} años y el valor actual "
            . "de la clasificación del riesgo {$label} no es válido. "
            . 'Como no existe información suficiente para clasificarlo '
            . 'como alto, bajo o moderado, se registró 21, '
            . 'Riesgo no evaluado.'
    );
}

private function hdlNoAplicaPorEdad(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [95, 111], true)) {
        return null;
    }

    $variables = $record['variables'] ?? [];

    $risk = trim(
        (string) ($variables[114] ?? '')
    );

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageMonths)) {
        $isUnderTwentyNine = (float) $ageMonths < 348;
        $displayAge = round((float) $ageMonths / 12, 2);
    } elseif (is_numeric($ageYears)) {
        $isUnderTwentyNine = (float) $ageYears < 29;
        $displayAge = round((float) $ageYears, 2);
    } else {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para resolver '
                . 'el Error 309 de HDL.'
        );
    }

    if (
        ! $isUnderTwentyNine
        || $risk !== '0'
    ) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                "La persona tiene aproximadamente {$displayAge} años "
                . 'o la variable 114 no está registrada en 0. '
                . 'No es seguro aplicar automáticamente No aplica '
                . 'al bloque HDL.'
        );
    }

    $target = $variable === 111
        ? self::NO_APLICA_DATE
        : 0;

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason:
            "La persona tiene aproximadamente {$displayAge} años "
            . 'y la variable 114 está registrada como 0. '
            . 'El Error 309 exige registrar el bloque HDL '
            . 'como No aplica.'
    );
}

private function normalizeHdlResult(
    int $variable,
    mixed $currentValue
): RuleDecision {
    $originalValue = trim(
        (string) $currentValue
    );

    if ($originalValue === '') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El resultado de HDL está vacío y no puede '
                . 'corregirse automáticamente.'
        );
    }

    /*
     * Admite decimales con punto o coma.
     * Ejemplo: 30.7 o 30,7.
     */
    $numericValue = str_replace(
        ',',
        '.',
        $originalValue
    );

    if (! is_numeric($numericValue)) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El resultado de HDL no contiene un valor numérico válido.'
        );
    }

    /*
     * Se elimina la parte decimal sin redondear:
     * 30.7 -> 30
     * 45.9 -> 45
     */
    $integerValue = (int) ((float) $numericValue);

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $integerValue,
        reason:
            'El resultado de HDL debe registrarse como número entero. '
            . "Se convirtió {$originalValue} en {$integerValue}, "
            . 'eliminando la parte decimal sin redondear.'
    );
}

private function normalizeHemoglobinResult(
    int $variable,
    mixed $currentValue
): RuleDecision {
    $originalValue = trim(
        (string) $currentValue
    );

    if ($originalValue === '') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El resultado de hemoglobina está vacío y no puede '
                . 'corregirse automáticamente.'
        );
    }

    $numericValue = str_replace(
        ',',
        '.',
        $originalValue
    );

    if (! is_numeric($numericValue)) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El resultado de hemoglobina no contiene '
                . 'un valor numérico válido.'
        );
    }

    /*
     * Elimina la parte decimal sin redondear:
     * 12.7 -> 12
     * 13.9 -> 13
     */
    $integerValue = (int) ((float) $numericValue);

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $integerValue,
        reason:
            'El resultado de hemoglobina debe registrarse como '
            . 'número entero. '
            . "Se convirtió {$originalValue} en {$integerValue}, "
            . 'eliminando la parte decimal sin redondear.'
    );
}
private function sangreOcultaPorEdad(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [24, 67], true)) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageMonths)) {
        $months = (float) $ageMonths;
        $insideAgeRange = $months >= 600 && $months <= 912;
        $displayAge = round($months / 12, 2);
    } elseif (is_numeric($ageYears)) {
        $years = (float) $ageYears;
        $insideAgeRange = $years >= 50 && $years <= 76;
        $displayAge = round($years, 2);
    } else {
        return $this->manual(
            $variable,
            $currentValue,
            'No fue posible calcular la edad para validar la prueba de sangre oculta.'
        );
    }

    if (! $insideAgeRange) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                24 => 0,
                67 => self::NO_APLICA_DATE,
            ],
            reason:
                "La persona tiene aproximadamente {$displayAge} años y está "
                . 'fuera del rango de 50 a 76 años. La prueba de sangre '
                . 'oculta se registra como No aplica.'
        );
    }

    return $this->automaticBlock(
        record: $record,
        changes: [
            24 => 21,
            67 => '1800-01-01',
        ],
        reason:
            "La persona tiene aproximadamente {$displayAge} años y está "
            . 'dentro del rango de 50 a 76 años. Al no existir resultado '
            . 'clínico, el bloque queda como no evaluado.'
    );
}

private function sangreOcultaNoAplica(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [24, 67], true)) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageMonths)) {
        $months = (float) $ageMonths;
        $outsideAgeRange = $months < 600 || $months > 900;
        $displayAge = round($months / 12, 2);
    } elseif (is_numeric($ageYears)) {
        $years = (float) $ageYears;
        $outsideAgeRange = $years < 50 || $years > 75;
        $displayAge = round($years, 2);
    } else {
        return $this->manual(
            $variable,
            $currentValue,
            'No fue posible calcular la edad para validar la prueba de sangre oculta.'
        );
    }

    $risk = trim((string) ($record['variables'][114] ?? ''));
    $noRisk = $risk === '0';

    if ($outsideAgeRange && $noRisk) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                24 => 0,
                67 => self::NO_APLICA_DATE,
            ],
            reason:
                "La persona tiene aproximadamente {$displayAge} años, está "
                . 'fuera del rango y no tiene riesgo identificado. La prueba '
                . 'se registra como No aplica.'
        );
    }

    return $this->automaticBlock(
        record: $record,
        changes: [
            24 => 21,
            67 => '1800-01-01',
        ],
        reason:
            "La persona tiene aproximadamente {$displayAge} años o no se "
            . 'confirmó ausencia de riesgo. El bloque se normaliza como no evaluado.'
    );
}

private function gestationRelatedFields(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 14:
     * Gestante
     *
     * Variables relacionadas:
     * 33 = Fecha probable de parto
     * 35 = Clasificación del riesgo gestacional
     * 56 = Fecha de primera consulta prenatal
     * 58 = Fecha del último control prenatal
     * 59 = Suministro de ácido fólico
     * 60 = Suministro de sulfato ferroso
     * 61 = Suministro de carbonato de calcio
     */
    $relatedVariables = [
        14,
        23,
        33,
        35,
        56,
        58,
        59,
        60,
        61,
    ];

    if (! in_array($variable, $relatedVariables, true)) {
        return null;
    }

    $gestante = trim(
        (string) (
            $record['variables'][14]
            ?? ''
        )
    );

    /*
     * 1 = Sí gestante.
     */
    if ($gestante !== '1') {
        if ($variable !== 14) {
            return null;
        }

        return RuleDecision::valid(
            variable: 14,
            currentValue: $currentValue,
            reason:
                'La persona no está registrada como gestante. '
                . 'El Error379 no corresponde al registro actual.',
            rule: self::class
        );
    }

    /*
     * La variable 14 ya está correctamente en Sí.
     */
    if ($variable === 14) {
        return RuleDecision::valid(
            variable: 14,
            currentValue: $currentValue,
            reason:
                'La persona está registrada como gestante. '
                . 'Se validarán las variables relacionadas.',
            rule: self::class
        );
    }

    /*
     * Fechas relacionadas con la gestación:
     * si están en No aplica, se cambia a Sin dato.
     */
    if (in_array($variable, [33, 56, 58], true)) {
        $currentText = trim(
            (string) $currentValue
        );

        if ($currentText !== self::NO_APLICA_DATE) {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'La persona está gestante y la fecha relacionada '
                    . 'no está registrada como No aplica.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                'La persona está registrada como gestante. '
                . 'La fecha relacionada no puede quedar en '
                . '1845-01-01, No aplica; se reemplazó por '
                . '1800-01-01, Sin dato.'
        );
    }
/*
 * Variable 23:
 * Ácido fólico preconcepcional.
 *
 * 0  = No aplica
 * 21 = Registro no evaluado
 */
if ($variable === 23) {
    if (trim((string) $currentValue) !== '0') {
        return RuleDecision::valid(
            variable: 23,
            currentValue: $currentValue,
            reason:
                'La persona está registrada como gestante y el campo '
                . 'de ácido fólico preconcepcional contiene un valor '
                . 'diferente de No aplica.',
            rule: self::class
        );
    }

    return $this->automaticOrValid(
        variable: 23,
        currentValue: $currentValue,
        newValue: 21,
        reason:
            'La persona está registrada como gestante. El ácido '
            . 'fólico preconcepcional no puede permanecer en 0, '
            . 'No aplica; se reemplazó por 21, Registro no evaluado.'
    );
}
    /*
     * Clasificación del riesgo gestacional:
     * 0 = No aplica
     * 21 = Riesgo no evaluado
     */
    if ($variable === 35) {
        if (trim((string) $currentValue) !== '0') {
            return RuleDecision::valid(
                variable: 35,
                currentValue: $currentValue,
                reason:
                    'La clasificación del riesgo gestacional '
                    . 'ya contiene un valor diferente de No aplica.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: 35,
            currentValue: $currentValue,
            newValue: 21,
            reason:
                'La persona está registrada como gestante. '
                . 'La clasificación del riesgo gestacional no puede '
                . 'quedar en 0, No aplica; se reemplazó por 21, '
                . 'Riesgo no evaluado.'
        );
    }

    /*
     * Suplementos del control prenatal:
     * 0 = No aplica
     * 21 = Registro no evaluado
     */
    if (in_array($variable, [59, 60, 61], true)) {
        if (trim((string) $currentValue) !== '0') {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    'La persona está gestante y el campo de suministro '
                    . 'ya contiene un valor diferente de No aplica.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 21,
            reason:
                'La persona está registrada como gestante. '
                . 'El suministro del control prenatal no puede quedar '
                . 'en 0, No aplica; se reemplazó por 21, '
                . 'Registro no evaluado.'
        );
    }

    return null;
}


private function validateAblativeTreatment(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 47) {
        return null;
    }

    $gestante = trim(
        (string) (
            $record['variables'][14]
            ?? ''
        )
    );

    $screeningTechnique = trim(
        (string) (
            $record['variables'][86]
            ?? ''
        )
    );

    $screeningResult = trim(
        (string) (
            $record['variables'][88]
            ?? ''
        )
    );

    /*
     * Variable 47:
     * Tratamiento ablativo o de escisión.
     *
     * 0  = No aplica
     * 6  = Tratamiento ablativo
     * 7  = Tratamiento de escisión
     * 8  = Tratamiento homologable
     * 9  = Requiere otro procedimiento
     * 10 = No se realizó por otras razones
     * 21 = Registro no evaluado
     */

    /*
     * Si no está gestante, el tratamiento debe quedar
     * como No aplica.
     */
    if ($gestante !== '1') {
        return $this->automaticOrValid(
            variable: 47,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'La persona no está registrada como gestante. '
                . 'El tratamiento ablativo o de escisión debe '
                . 'registrarse como 0, No aplica.'
        );
    }

    /*
     * Variable 86:
     * 3 = Técnica de inspección visual.
     *
     * Si utilizó cualquier otra técnica, el tratamiento
     * ablativo o de escisión no aplica.
     */
    if ($screeningTechnique !== '3') {
        return $this->automaticOrValid(
            variable: 47,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'El tamizaje no fue realizado mediante técnica '
                . 'de inspección visual. El tratamiento ablativo '
                . 'o de escisión debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Variable 88:
     * 20 = Resultado negativo para inspección visual.
     */
    if ($screeningResult === '20') {
        return $this->automaticOrValid(
            variable: 47,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'El resultado de la técnica de inspección visual '
                . 'fue negativo. El tratamiento ablativo o de '
                . 'escisión debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Si el resultado está como No aplica o riesgo no evaluado,
     * tampoco corresponde registrar tratamiento.
     */
    if (in_array($screeningResult, ['0', '21'], true)) {
        return $this->automaticOrValid(
            variable: 47,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'El resultado de la técnica de inspección visual '
                . 'no confirma un resultado positivo. El tratamiento '
                . 'ablativo o de escisión debe registrarse como '
                . '0, No aplica.'
        );
    }

    /*
     * Solo cuando:
     *
     * variable 86 = 3
     * variable 88 = 19
     *
     * puede existir tratamiento diferente de 0.
     */
    if (
        $screeningTechnique === '3'
        && $screeningResult === '19'
    ) {
        if (
            in_array(
                trim((string) $currentValue),
                ['6', '7', '8', '9', '10', '21'],
                true
            )
        ) {
            return RuleDecision::valid(
                variable: 47,
                currentValue: $currentValue,
                reason:
                    'La técnica utilizada fue inspección visual '
                    . 'y el resultado fue positivo. El valor del '
                    . 'tratamiento es permitido.',
                rule: self::class
            );
        }

        return $this->manual(
            variable: 47,
            currentValue: $currentValue,
            reason:
                'La técnica de inspección visual fue positiva. '
                . 'Se requiere información clínica para determinar '
                . 'si se realizó tratamiento ablativo, escisión, '
                . 'otro procedimiento o no se realizó tratamiento.'
        );
    }

    /*
     * Cualquier combinación restante se lleva a No aplica.
     */
    return $this->automaticOrValid(
        variable: 47,
        currentValue: $currentValue,
        newValue: 0,
        reason:
            'La combinación del tipo y resultado del tamizaje '
            . 'no permite registrar tratamiento ablativo o de '
            . 'escisión. Se reemplazó por 0, No aplica.'
    );
}

private function validateCardiovascularRisk(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 114) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: 114,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la clasificación del riesgo cardiovascular.'
        );
    }

    $value = trim((string) $currentValue);

    /*
     * Menores de 18 años:
     * debe registrarse 0 = No aplica.
     */
    if ($ageYears < 18) {
        return $this->automaticOrValid(
            variable: 114,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona tiene {$ageYears} años. En menores de "
                . '18 años, la clasificación del riesgo cardiovascular '
                . 'debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Personas de 18 años o más:
     * valores permitidos 4, 5, 6 y 21.
     */
    if (in_array($value, ['4', '5', '6', '21'], true)) {
        return RuleDecision::valid(
            variable: 114,
            currentValue: $currentValue,
            reason:
                "La persona tiene {$ageYears} años y la clasificación "
                . 'del riesgo cardiovascular contiene un valor permitido.',
            rule: self::class
        );
    }

    /*
     * No podemos inventar si el riesgo es alto,
     * bajo o moderado. Se registra 21.
     */
    return $this->automaticOrValid(
        variable: 114,
        currentValue: $currentValue,
        newValue: 21,
        reason:
            "La persona tiene {$ageYears} años. En personas de 18 años "
            . 'o más no corresponde registrar 0, No aplica. Como no '
            . 'existe información para determinar si el riesgo es alto, '
            . 'bajo o moderado, se reemplazó por 21, Riesgo no evaluado.'
    );
}

private function validateMetabolicRisk(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 117) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: 117,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la clasificación del riesgo metabólico.'
        );
    }

    $value = trim((string) $currentValue);

    /*
     * Menores de 18 años:
     * debe registrarse 0 = No aplica.
     */
    if ($ageYears < 18) {
        return $this->automaticOrValid(
            variable: 117,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona tiene {$ageYears} años. En menores de "
                . '18 años, la clasificación del riesgo metabólico '
                . 'debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Personas de 18 años o más:
     * valores permitidos 4, 5, 6 y 21.
     */
    if (in_array($value, ['4', '5', '6', '21'], true)) {
        return RuleDecision::valid(
            variable: 117,
            currentValue: $currentValue,
            reason:
                "La persona tiene {$ageYears} años y la clasificación "
                . 'del riesgo metabólico contiene un valor permitido.',
            rule: self::class
        );
    }

    return $this->automaticOrValid(
        variable: 117,
        currentValue: $currentValue,
        newValue: 21,
        reason:
            "La persona tiene {$ageYears} años. En personas de 18 años "
            . 'o más no corresponde registrar 0, No aplica. Como no '
            . 'existe información para determinar si el riesgo es alto, '
            . 'bajo o moderado, se reemplazó por 21, Riesgo no evaluado.'
    );
}

private function normalizeDevelopmentScaleBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variables:
     *
     * 43 = Motricidad gruesa
     * 44 = Motricidad fino adaptativa
     * 45 = Personal social
     * 46 = Audición y lenguaje
     * 52 = Fecha de consulta de valoración integral
     */
    if (! in_array(
        $variable,
        [43, 44, 45, 46, 52],
        true
    )) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la escala abreviada de desarrollo.'
        );
    }

    $variables = $record['variables'] ?? [];

    $consultationDate = trim(
        (string) ($variables[52] ?? '')
    );

    $isDevelopmentAge =
        $ageYears >= 0
        && $ageYears <= 7;

    /*
     * Personas entre 0 y 7 años:
     *
     * Si la fecha está en 1845-01-01, no debe conservarse
     * como No aplica, porque la escala sí aplica por edad.
     *
     * Se cambia el bloque completo a:
     *
     * 52 = 1800-01-01
     * 43 = 21
     * 44 = 21
     * 45 = 21
     * 46 = 21
     */
    if (
        $isDevelopmentAge
        && $consultationDate === self::NO_APLICA_DATE
    ) {
        $targetValue = match ($variable) {
            52 => '1800-01-01',
            43, 44, 45, 46 => 21,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                "La persona tiene {$ageYears} años y está dentro "
                . 'del rango de aplicación de la escala abreviada '
                . 'de desarrollo. La fecha 1845-01-01 y los resultados '
                . 'en 0 no son válidos para este rango de edad. '
                . 'La fecha se normalizó a 1800-01-01 y los resultados '
                . 'de las variables 43, 44, 45 y 46 se normalizaron a 21.'
        );
    }

    /*
     * Si la fecha ya está en 1800-01-01 y la persona
     * tiene entre 0 y 7 años, la evaluación no fue realizada.
     *
     * Los cuatro resultados deben quedar en 21.
     */
    if (
        $isDevelopmentAge
        && $consultationDate === '1800-01-01'
    ) {
        $targetValue = match ($variable) {
            52 => '1800-01-01',
            43, 44, 45, 46 => 21,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                "La persona tiene {$ageYears} años y la fecha de "
                . 'valoración integral está registrada como '
                . '1800-01-01. La evaluación no fue realizada, '
                . 'por lo que las variables 43, 44, 45 y 46 '
                . 'deben registrarse en 21.'
        );
    }

    /*
     * Fecha real y edad entre 0 y 7 años:
     * desarrollo esperado para la edad.
     */
    if (
        $isDevelopmentAge
        && $this->isRealReportDate(
            $consultationDate
        )
    ) {
        if ($variable === 52) {
            return RuleDecision::valid(
                variable: 52,
                currentValue: $currentValue,
                reason:
                    'La fecha de consulta de valoración integral '
                    . 'es una fecha real válida.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 5,
            reason:
                "La persona tiene {$ageYears} años, está dentro "
                . 'del rango de 0 a 7 años y tiene una fecha real '
                . 'de valoración integral. El resultado de la escala '
                . 'se registra como 5, Desarrollo esperado para la edad.'
        );
    }

    /*
     * Fuera del rango de 0 a 7 años:
     * las variables 43 a 46 no aplican.
     *
     * La fecha no se modifica desde esta regla si no fue
     * reportada directamente como error.
     */
    if (! $isDevelopmentAge) {
        if ($variable === 52) {
            return RuleDecision::valid(
                variable: 52,
                currentValue: $currentValue,
                reason:
                    "La persona tiene {$ageYears} años y está fuera "
                    . 'del rango de aplicación de la escala abreviada '
                    . 'de desarrollo. La regla no modifica la fecha.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                "La persona tiene {$ageYears} años y está fuera "
                . 'del rango de 0 a 7 años. La escala abreviada '
                . 'de desarrollo debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Persona entre 0 y 7 años, pero sin una fecha reconocida.
     */
    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La persona está dentro del rango de 0 a 7 años, '
            . 'pero la variable 52 no contiene una fecha real, '
            . '1800-01-01 ni 1845-01-01 reconocible.'
    );
}
private function normalizeCervicalScreeningBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Esta regla trabaja únicamente con:
     *
     * 86 = Tamizaje de cáncer de cuello uterino
     * 87 = Fecha del tamizaje
     * 88 = Resultado del tamizaje
     */
    if (! in_array($variable, [86, 87, 88], true)) {
        return null;
    }

    $variables = $record['variables'] ?? [];

    $screeningType = trim(
        (string) ($variables[86] ?? '')
    );

    $screeningDate = trim(
        (string) ($variables[87] ?? '')
    );

    $screeningResult = trim(
        (string) ($variables[88] ?? '')
    );

    /*
     * Se considera que la variable 86 contiene información real
     * cuando tiene un valor diferente de:
     *
     * vacío
     * 0  = No aplica
     * 21 = Registro no evaluado
     */
    $hasRealScreeningType = !in_array(
        $screeningType,
        ['', '0', '21'],
        true
    );

    /*
     * Se considera fecha real cualquier fecha diferente
     * de las fechas especiales del reporte.
     */
    $hasRealScreeningDate = $this->isRealReportDate(
        $screeningDate
    );

    /*
     * Se considera resultado real cuando contiene un valor
     * diferente de:
     *
     * vacío
     * 0  = No aplica
     * 21 = Registro no evaluado
     */
    $hasRealScreeningResult = !in_array(
        $screeningResult,
        ['', '0', '21'],
        true
    );

    /*
     * Si cualquiera de las tres variables contiene información
     * real, se normaliza el bloque completo:
     *
     * 86 = 21
     * 87 = 1800-01-01
     * 88 = 21
     */
    $mustNormalize =
        $hasRealScreeningType
        || $hasRealScreeningDate
        || $hasRealScreeningResult;

    /*
     * También se normalizan combinaciones incompletas,
     * aunque ninguna haya sido reconocida como información real.
     *
     * El único bloque final permitido por esta regla es:
     *
     * 21 - 1800-01-01 - 21
     */
    $alreadyNormalized =
        $screeningType === '21'
        && $screeningDate === '1800-01-01'
        && $screeningResult === '21';

    if (! $mustNormalize && $alreadyNormalized) {
        return RuleDecision::valid(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El bloque de tamizaje de cáncer de cuello uterino '
                . 'ya se encuentra normalizado como '
                . '21 - 1800-01-01 - 21.',
            rule: self::class
        );
    }

    /*
     * Incluso si la combinación es 0, vacía o incompleta,
     * para estos errores se lleva al mismo bloque estándar.
     */
    $targetValue = match ($variable) {
        86 => 21,
        87 => '1800-01-01',
        88 => 21,
    };

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $targetValue,
        reason:
            'Se detectó información real, incompleta o inconsistente '
            . 'en alguna de las variables 86, 87 u 88. El bloque completo '
            . 'se normalizó como: variable 86 igual a 21, variable 87 '
            . 'igual a 1800-01-01 y variable 88 igual a 21.'
    );
}

    private function isRealReportDate(mixed $value): bool
    {
        $date = trim((string) $value);

        if ($date === '') {
            return false;
        }

        $nonRealDates = array_merge(
            self::NO_REALIZATION_DATES,
            [self::NO_APLICA_DATE]
        );

        if (in_array($date, $nonRealDates, true)) {
            return false;
        }

        $formats = [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'Y/m/d',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat(
                '!' . $format,
                $date
            );

            if (
                $parsed !== false
                && $parsed->format($format) === $date
            ) {
                return true;
            }
        }

        return false;
    }

/**
 * Error 506: solo cuando el registro dice expresamente que NO es
 * sintomático respiratorio (variable 18 = 2), y no existe una toma real.
 * Catálogo de la variable 113: 4 = No.
 */
private function bacilloscopyNotSymptomatic506(array $record): RuleDecision
{
    $v = $record['variables'] ?? [];
    $symptomatic = trim((string) ($v[18] ?? ''));
    $date = trim((string) ($v[112] ?? ''));
    $result = trim((string) ($v[113] ?? ''));

    if ($symptomatic !== '2') {
        return $this->manual(
            variable: 113,
            currentValue: $result,
            reason: 'Error 506: debe verificarse si el paciente es o no sintomático respiratorio antes de cambiar el resultado.'
        );
    }

    if ($date !== self::NO_APLICA_DATE) {
        return $this->manual(
            variable: 113,
            currentValue: $result,
            reason: 'Error 506: existe una fecha distinta de No aplica. Revisar la evidencia clínica antes de corregir el resultado.'
        );
    }

    return $this->automaticOrValid(
        variable: 113,
        currentValue: $result,
        newValue: 4,
        reason: 'No es sintomático respiratorio (18=2); la fecha de baciloscopia ya está como No aplica y el resultado se registra 4=No.'
    );
}

/**
 * Error 625: el mensaje de la EPS trae la fecha de nacimiento del
 * sistema, que puede ser distinta de la registrada en el TXT.
 *
 * Si la EPS calcula >=29 anios, existe el par NO APLICA y no hay
 * laboratorio medido, usar SIN DATO, sin inventar resultado ni fecha.
 * La discrepancia de nacimiento queda explicitamente en auditoria.
 */
private function hdlConditional625(array $record, array $error): RuleDecision
{
    $v = $record['variables'] ?? [];
    $result = trim((string) ($v[95] ?? ''));
    $hdlDate = trim((string) ($v[111] ?? ''));
    $risk = trim((string) ($v[114] ?? ''));
    $systemBirthText = trim((string) ($error['fecha_nacimiento_sistema'] ?? ''));
    $txtBirthText = trim((string) ($v[9] ?? ''));
    $cutoffText = trim((string) ($record['cutoff_date'] ?? ''));

    if (
        $systemBirthText === ''
        || $cutoffText === ''
    ) {
        return $this->manual(
            variable: 95,
            currentValue: $result,
            reason: 'Error 625: falta fecha de nacimiento del sistema de la EPS o fecha de corte; no se altera el bloque HDL.'
        );
    }

    $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', $systemBirthText);
    $cutoff = \DateTimeImmutable::createFromFormat('!Y-m-d', $cutoffText);

    if (
        ! $birth instanceof \DateTimeImmutable
        || ! $cutoff instanceof \DateTimeImmutable
        || $birth > $cutoff
    ) {
        return $this->manual(
            variable: 95,
            currentValue: $result,
            reason: 'Error 625: fecha de nacimiento de la EPS o fecha de corte invalida.'
        );
    }

    $epsAge = $birth->diff($cutoff)->y;
    $birthDifference = ($systemBirthText !== $txtBirthText)
        ? " ATENCION: nacimiento EPS {$systemBirthText} distinto del TXT {$txtBirthText}; verificar documento fuente sin cambiarlo automaticamente."
        : '';

    if (
        $epsAge >= 29
        && $result === '0'
        && $hdlDate === self::NO_APLICA_DATE
        && $risk === '21'
    ) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                95 => 998, // Riesgo no evaluado segun catalogo; NO es un HDL medido.
                111 => '1800-01-01', // Sin dato; NO representa toma real.
            ],
            reason: "Error 625: edad EPS {$epsAge} al corte; el par No aplica no procede para esta edad y no existe HDL clinico medido en el TXT. Se usa 998 (Riesgo no evaluado) y 1800-01-01 (No se tiene el dato)."
                . $birthDifference
                . ' La EPS debe validar que estos comodines sean aceptados para este rechazo.'
        );
    }

    if ($epsAge < 29 && $result === '0' && $hdlDate === self::NO_APLICA_DATE) {
        return $this->manual(
            variable: 95,
            currentValue: $result,
            reason: "Error 625: la EPS calcula {$epsAge} anios y el par HDL ya es No aplica. Riesgo cardiovascular={$risk}; verificar clasificacion real antes de modificarla."
                . $birthDifference
        );
    }

    return $this->manual(
        variable: 95,
        currentValue: $result,
        reason: "Error 625: edad EPS {$epsAge}, resultado HDL={$result}, fecha={$hdlDate}, riesgo={$risk}. No se sustituye un HDL clinico real ni se deducen riesgos."
            . $birthDifference
    );
}

/**
 * Peso: solo corrige cuando la IPS haya introducido un valor
 * documentado en config/informe202_dusakawi_verificados.php.
 * Tres verificaciones independientes: linea, tipo, documento y peso
 * actual esperado. Evita corregir por accidente otro periodo o fila.
 */
private function verifiedWeightOnly(array $record, array $error): RuleDecision
{
    $v = $record['variables'] ?? [];
    $current = trim((string) ($v[30] ?? ''));
    $line = trim((string) ($error['linea'] ?? $record['record_number'] ?? ''));
    $type = strtoupper(trim((string) ($v[3] ?? '')));
    $doc = trim((string) ($v[4] ?? ''));
    $key = "{$line}|{$type}|{$doc}";

    $configured = config('informe202_dusakawi_verificados.pesos', []);
    $entry = is_array($configured) ? ($configured[$key] ?? null) : null;

    if (! is_array($entry) || ($entry['peso_kg'] ?? null) === null || ($entry['soporte'] ?? '') === '') {
        return $this->manual(
            variable: 30,
            currentValue: $current,
            reason: "Peso de la fila {$line} no verificado. Para automatizarlo, registrar peso real y soporte clinico en config/informe202_dusakawi_verificados.php. No se infieren decimales."
        );
    }

    $expected = trim((string) ($entry['valor_actual'] ?? ''));
    $period = trim((string) ($entry['corte'] ?? ''));
    $cutoff = trim((string) ($record['cutoff_date'] ?? ''));
    $source = trim((string) $entry['soporte']);
    $new = str_replace(',', '.', trim((string) $entry['peso_kg']));

    if ($expected !== $current || $period !== $cutoff || ! is_numeric($new)) {
        return $this->manual(
            variable: 30,
            currentValue: $current,
            reason: "El peso verificado para {$key} no coincide con periodo/valor original o no es numerico. Validar configuracion."
        );
    }

    $years = $record['age']['years'] ?? null;
    $months = $record['age']['months'] ?? null;
    if (! is_numeric($months) && is_numeric($years)) {
        $months = (int) $years * 12;
    }
    if (! is_numeric($months)) {
        return $this->manual(variable: 30, currentValue: $current,
            reason: 'No se pudo comprobar la edad al corte para validar el peso confirmado.');
    }
    $ageMonths = (float) $months;
    $weight = (float) $new;

    $validRange = $ageMonths < 24
        ? ($weight >= 1.0 && $weight <= 15.0)
        : ($ageMonths >= 216 && $weight >= 36.0 && $weight <= 250.0);

    if (! $validRange) {
        return $this->manual(
            variable: 30,
            currentValue: $current,
            reason: "El peso confirmado {$new} kg no cumple el rango de validacion disponible para esta edad, o corresponde a un grupo sin rango configurado. Revisar."
        );
    }

    return $this->automaticOrValid(
        variable: 30,
        currentValue: $current,
        newValue: $new,
        reason: "Peso documentado y verificado por la IPS. Soporte: {$source}. Periodo {$period}; coincidencia de linea, documento y valor original comprobada."
    );
}

/**
 * Si el resultado neonatal es 21 (no evaluado), la fecha no debe
 * figurar como 1845-01-01 (no aplica), sino como 1800-01-01 (sin dato).
 * No se reemplaza ninguna fecha real.
 */
private function neonatalDateWithoutExamination(
    array $record,
    int $dateVariable,
    int $resultVariable,
    string $label
): RuleDecision {
    $v = $record['variables'] ?? [];
    $result = trim((string) ($v[$resultVariable] ?? ''));
    $date = trim((string) ($v[$dateVariable] ?? ''));

    if ($result === '21' && $date === self::NO_APLICA_DATE) {
        return $this->automaticOrValid(
            variable: $dateVariable,
            currentValue: $date,
            newValue: '1800-01-01',
            reason: 'El resultado de ' . $label
                . ' es 21 (no evaluado). Se utiliza el comodín Sin dato '
                . 'para la fecha, sin inventar una toma.'
        );
    }

    return $this->manual(
        variable: $dateVariable,
        currentValue: $date,
        reason: 'Revisar fecha y resultado de ' . $label
            . '; los datos no permiten una corrección automática segura.'
    );
}

private function normalizeRespiratorySymptomaticBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variables:
     *
     * 18  = Sintomático respiratorio
     * 112 = Fecha de baciloscopia diagnóstica
     * 113 = Resultado de baciloscopia diagnóstica
     *
     * Error507:
     * Si Sintomático respiratorio está registrado como
     * 21, Riesgo no evaluado:
     *
     * 112 = 1800-01-01, Sin dato
     * 113 = 21, Riesgo no evaluado
     */
    if (! in_array($variable, [112, 113], true)) {
        return null;
    }

    $variables = $record['variables'] ?? [];

    $respiratorySymptomatic = trim(
        (string) ($variables[18] ?? '')
    );

    $bacilloscopyDate = trim(
        (string) ($variables[112] ?? '')
    );

    $bacilloscopyResult = trim(
        (string) ($variables[113] ?? '')
    );

    /*
     * La regla automática solo aplica cuando la variable 18
     * está explícitamente registrada como 21:
     * Riesgo no evaluado.
     */
    if ($respiratorySymptomatic !== '21') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El Error507 fue reportado, pero la variable 18 '
                . 'Sintomático respiratorio no está registrada como '
                . '21, Riesgo no evaluado. Debe revisarse el registro.'
        );
    }

    /*
     * Si el sintomático respiratorio está en riesgo no evaluado,
     * la fecha debe registrarse como 1800-01-01.
     */
    if ($variable === 112) {
        return $this->automaticOrValid(
            variable: 112,
            currentValue: $currentValue,
            newValue: '1800-01-01',
            reason:
                'La variable 18 Sintomático respiratorio está registrada '
                . 'como 21, Riesgo no evaluado. La fecha de baciloscopia '
                . 'diagnóstica debe registrarse como 1800-01-01, Sin dato.'
        );
    }

    /*
     * El resultado de la baciloscopia debe quedar igualmente
     * como 21, Riesgo no evaluado.
     */
    return $this->automaticOrValid(
        variable: 113,
        currentValue: $currentValue,
        newValue: 21,
        reason:
            'La variable 18 Sintomático respiratorio está registrada '
            . 'como 21, Riesgo no evaluado. El resultado de baciloscopia '
            . 'diagnóstica debe registrarse como 21, Riesgo no evaluado.'
    );
}

private function correctContraceptiveSupplyDate(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 53:
     * Fecha de atención en salud para la asesoría
     * en anticoncepción.
     *
     * Variable 55:
     * Fecha de suministro de método anticonceptivo.
     *
     * Error128:
     * La fecha de suministro es posterior a la fecha
     * de corte del reporte.
     *
     * Corrección segura:
     * Si la variable 55 tiene el mismo mes y día que
     * la variable 53, pero un año diferente, se copia
     * la fecha válida de la variable 53.
     */
    if ($variable !== 55) {
        return null;
    }

    $variables = $record['variables'] ?? [];

    $consultationValue = trim(
        (string) ($variables[53] ?? '')
    );

    $supplyValue = trim(
        (string) ($variables[55] ?? '')
    );

    $consultationDate = $this->parseReportDate(
        $consultationValue
    );

    $supplyDate = $this->parseReportDate(
        $supplyValue
    );

    if ($consultationDate === null) {
        return $this->manual(
            variable: 55,
            currentValue: $currentValue,
            reason:
                'No fue posible interpretar la variable 53, '
                . 'Fecha de atención en salud para la asesoría '
                . 'en anticoncepción. No es seguro corregir '
                . 'automáticamente la variable 55.'
        );
    }

    if ($supplyDate === null) {
        return $this->manual(
            variable: 55,
            currentValue: $currentValue,
            reason:
                'La variable 55, Fecha de suministro del método '
                . 'anticonceptivo, no contiene una fecha válida. '
                . 'Debe revisarse manualmente.'
        );
    }

    /*
     * Fecha de corte disponible en el registro.
     */
    $cutoffValue =
        $record['cutoff_date']
        ?? $record['report_cutoff_date']
        ?? $record['fecha_corte']
        ?? null;

    $cutoffDate = $this->parseReportDate(
        $cutoffValue
    );

    /*
     * Si no existe fecha de corte, todavía podemos detectar
     * el error cuando el año de la variable 55 es posterior
     * al año de la variable 53 y el mes y día coinciden.
     */
    $sameMonthAndDay =
        $consultationDate->format('m-d')
        === $supplyDate->format('m-d');

    $differentYear =
        $consultationDate->format('Y')
        !== $supplyDate->format('Y');

    $supplyAfterConsultation =
        $supplyDate > $consultationDate;

    $supplyAfterCutoff =
        $cutoffDate !== null
        && $supplyDate > $cutoffDate;

    /*
     * Ejemplo:
     *
     * 53 = 2026-06-19
     * 55 = 2027-06-19
     *
     * Coinciden mes y día, pero el año de la variable 55
     * es posterior. Se copia la fecha de la variable 53.
     */
    if (
        $sameMonthAndDay
        && $differentYear
        && $supplyAfterConsultation
        && (
            $cutoffDate === null
            || $supplyAfterCutoff
        )
    ) {
        $newValue = $consultationDate->format(
            'Y-m-d'
        );

        return $this->automaticOrValid(
            variable: 55,
            currentValue: $currentValue,
            newValue: $newValue,
            reason:
                'La fecha de suministro del método anticonceptivo '
                . $supplyDate->format('Y-m-d')
                . ' tiene el mismo mes y día que la fecha de atención '
                . 'en asesoría en anticoncepción '
                . $consultationDate->format('Y-m-d')
                . ', pero contiene un año posterior. Se identificó '
                . 'un error de digitación en el año y se reemplazó '
                . 'la variable 55 por la fecha registrada en la '
                . 'variable 53.'
        );
    }

    /*
     * Si ambas fechas ya son iguales, el registro es válido.
     */
    if (
        $consultationDate->format('Y-m-d')
        === $supplyDate->format('Y-m-d')
    ) {
        return RuleDecision::valid(
            variable: 55,
            currentValue: $currentValue,
            reason:
                'La fecha de suministro del método anticonceptivo '
                . 'coincide con la fecha de atención en salud para '
                . 'la asesoría en anticoncepción.',
            rule: self::class
        );
    }

    /*
     * No se copia automáticamente cuando cambian también
     * el mes o el día, porque podría tratarse de una fecha
     * clínica diferente.
     */
    return $this->manual(
        variable: 55,
        currentValue: $currentValue,
        reason:
            'La fecha de suministro del método anticonceptivo es '
            . $supplyDate->format('Y-m-d')
            . ' y la fecha de atención en asesoría en anticoncepción es '
            . $consultationDate->format('Y-m-d')
            . '. Las fechas no coinciden únicamente en el año, por lo '
            . 'que no es seguro copiar automáticamente la variable 53.'
    );
}

private function parseReportDate(
    mixed $value
): ?\DateTimeImmutable {
    $date = trim(
        (string) $value
    );

    if ($date === '') {
        return null;
    }

    $formats = [
        'Y-m-d',
        'd/m/Y',
        'd-m-Y',
        'Y/m/d',
    ];

    foreach ($formats as $format) {
        $parsed = \DateTimeImmutable::createFromFormat(
            '!' . $format,
            $date
        );

        $errors = \DateTimeImmutable::getLastErrors();

        $hasErrors = is_array($errors)
            && (
                ($errors['warning_count'] ?? 0) > 0
                || ($errors['error_count'] ?? 0) > 0
            );

        if (
            $parsed !== false
            && ! $hasErrors
            && $parsed->format($format) === $date
        ) {
            return $parsed;
        }
    }

    return null;
}

private function normalizeNonPregnantFields(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Error244:
     *
     * Si la persona no está gestante, las variables relacionadas
     * con la gestación deben registrarse como No aplica.
     *
     * 14 = Gestante
     * 23 = Ácido fólico preconcepcional
     * 33 = Fecha probable de parto
     * 35 = Clasificación del riesgo gestacional
     * 56 = Fecha de primera consulta prenatal
     * 58 = Fecha del último control prenatal
     * 59 = Suministro de ácido fólico
     * 60 = Suministro de sulfato ferroso
     * 61 = Suministro de carbonato de calcio
     */
    $relatedVariables = [
        14,
        23,
        33,
        35,
        56,
        58,
        59,
        60,
        61,
    ];

    if (! in_array($variable, $relatedVariables, true)) {
        return null;
    }

    $gestante = trim(
        (string) (
            $record['variables'][14]
            ?? ''
        )
    );

    /*
     * 1 significa Sí gestante.
     *
     * Si está gestante, el Error244 no puede corregirse
     * poniendo las demás variables en No aplica.
     */
    if ($gestante === '1') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El Error244 fue reportado, pero la variable 14 '
                . 'está registrada como 1, Sí gestante. No es seguro '
                . 'cambiar las variables relacionadas a No aplica.'
        );
    }

    /*
     * La variable 14 solo se utiliza como condición.
     * Si contiene un valor reconocido de no gestante, se conserva.
     */
    if ($variable === 14) {
        if (in_array($gestante, ['0', '2', '21'], true)) {
            return RuleDecision::valid(
                variable: 14,
                currentValue: $currentValue,
                reason:
                    'La persona no está registrada como gestante. '
                    . 'Las variables relacionadas con la gestación '
                    . 'se normalizarán como No aplica.',
                rule: self::class
            );
        }

        return $this->manual(
            variable: 14,
            currentValue: $currentValue,
            reason:
                'La variable 14 Gestante no contiene un valor '
                . 'reconocido. No es seguro modificar automáticamente '
                . 'las variables relacionadas con la gestación.'
        );
    }

    /*
     * Si el valor de Gestante está vacío o no es reconocido,
     * tampoco se aplican cambios automáticos.
     */
    if (! in_array($gestante, ['0', '2', '21'], true)) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible confirmar que la persona no está '
                . 'gestante. La variable 14 debe revisarse antes de '
                . 'modificar las variables relacionadas.'
        );
    }

    /*
     * Variables de fecha:
     * No aplica = 1845-01-01.
     */
    if (in_array($variable, [33, 56, 58], true)) {
        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: self::NO_APLICA_DATE,
            reason:
                'La persona no está registrada como gestante. '
                . "La variable de fecha {$variable} debe registrarse "
                . 'como 1845-01-01, No aplica.'
        );
    }

    /*
     * Variables numéricas relacionadas:
     * No aplica = 0.
     */
    if (in_array($variable, [23, 35, 59, 60, 61], true)) {
        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: 0,
            reason:
                'La persona no está registrada como gestante. '
                . "La variable {$variable} debe registrarse "
                . 'como 0, No aplica.'
        );
    }

    return null;
}

private function normalizeMiniMentalBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 16:
     * Resultado prueba Mini-Mental State.
     *
     * Variable 52:
     * Fecha de consulta de valoración integral.
     *
     * Valores considerados:
     *
     * 0  = No aplica
     * 4  = Resultado válido
     * 5  = Resultado válido
     * 21 = Riesgo o resultado no evaluado
     */
    if (! in_array($variable, [16, 52], true)) {
        return null;
    }

    $variables = $record['variables'] ?? [];

    $result = trim(
        (string) ($variables[16] ?? '')
    );

    $consultationDate = trim(
        (string) ($variables[52] ?? '')
    );

    $hasRealDate = $this->isRealReportDate(
        $consultationDate
    );

    /*
     * Fecha 1845-01-01:
     * la valoración no aplica.
     *
     * Resultado = 0
     * Fecha = 1845-01-01
     */
    if ($consultationDate === self::NO_APLICA_DATE) {
        $targetValue = match ($variable) {
            16 => 0,
            52 => self::NO_APLICA_DATE,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La fecha de consulta de valoración integral está '
                . 'registrada como 1845-01-01, No aplica. El resultado '
                . 'de Mini-Mental debe registrarse como 0, No aplica.'
        );
    }

    /*
     * Fecha 1800-01-01:
     * la valoración no fue realizada o no tiene dato.
     *
     * Resultado = 21
     * Fecha = 1800-01-01
     */
    if ($consultationDate === '1800-01-01') {
        $targetValue = match ($variable) {
            16 => 21,
            52 => '1800-01-01',
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La fecha de consulta de valoración integral está '
                . 'registrada como 1800-01-01, Sin dato o no realización. '
                . 'El resultado de Mini-Mental debe registrarse como 21.'
        );
    }

    /*
     * Fecha real y resultado válido 4 o 5:
     * se conserva el bloque.
     */
    if (
        $hasRealDate
        && in_array($result, ['4', '5'], true)
    ) {
        return RuleDecision::valid(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'La fecha de consulta de valoración integral es real '
                . 'y el resultado de Mini-Mental contiene un valor '
                . 'válido de 4 o 5.',
            rule: self::class
        );
    }

    /*
     * Fecha real con resultado 0 o 21:
     *
     * No es coherente conservar una fecha real si no existe
     * un resultado clínico válido.
     *
     * Se normaliza a:
     * 16 = 21
     * 52 = 1800-01-01
     */
    if (
        $hasRealDate
        && in_array($result, ['0', '21', ''], true)
    ) {
        $targetValue = match ($variable) {
            16 => 21,
            52 => '1800-01-01',
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'Existe una fecha real de consulta de valoración integral, '
                . 'pero el resultado de Mini-Mental no contiene un resultado '
                . 'clínico válido de 4 o 5. Como no se puede inventar el '
                . 'resultado real, el bloque se normalizó a resultado 21 '
                . 'y fecha 1800-01-01.'
        );
    }

    /*
     * Resultado válido 4 o 5 sin una fecha real:
     *
     * No se puede conservar el resultado sin conocer la fecha
     * real de la valoración.
     *
     * Se normaliza a:
     * 16 = 21
     * 52 = 1800-01-01
     */
    if (
        in_array($result, ['4', '5'], true)
        && ! $hasRealDate
    ) {
        $targetValue = match ($variable) {
            16 => 21,
            52 => '1800-01-01',
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'El resultado de Mini-Mental contiene 4 o 5, pero no '
                . 'existe una fecha real de consulta de valoración integral. '
                . 'Como la fecha verdadera no puede deducirse, el bloque '
                . 'se normalizó a resultado 21 y fecha 1800-01-01.'
        );
    }

    /*
     * Resultado 0 sin una fecha reconocida:
     * se normaliza como No aplica.
     */
    if ($result === '0') {
        $targetValue = match ($variable) {
            16 => 0,
            52 => self::NO_APLICA_DATE,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'El resultado de Mini-Mental está registrado como 0, '
                . 'No aplica. La fecha de consulta debe registrarse '
                . 'como 1845-01-01, No aplica.'
        );
    }

    /*
     * Resultado 21 o vacío sin fecha reconocida:
     * se normaliza como Sin dato.
     */
    if (in_array($result, ['21', ''], true)) {
        $targetValue = match ($variable) {
            16 => 21,
            52 => '1800-01-01',
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'No existe una fecha real de consulta y el resultado '
                . 'de Mini-Mental no contiene un resultado clínico válido. '
                . 'El bloque se normalizó a resultado 21 y fecha '
                . '1800-01-01.'
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La combinación de resultado Mini-Mental y fecha de consulta '
            . 'no coincide con los casos automáticos reconocidos.'
    );
}


private function normalizeIdentificationTypeByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 3 = Tipo de identificación.
     * Variable 4 = Número de identificación.
     *
     * La edad permite orientar el tipo de documento, pero no permite
     * deducir un número de identificación diferente. Por seguridad,
     * esta regla conserva intacta la variable 4.
     */
    if ($variable !== 3) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (
        ! is_numeric($ageMonths)
        && ! is_numeric($ageYears)
    ) {
        return $this->manual(
            variable: 3,
            currentValue: $currentValue,
            reason:
                'El afiliado no fue identificado en el sistema, pero '
                . 'no fue posible calcular su edad. No es seguro cambiar '
                . 'automáticamente el tipo de identificación.'
        );
    }

    if (is_numeric($ageMonths)) {
        $months = (float) $ageMonths;

        if ($months < 84) {
            $newType = 'RC';
        } elseif ($months < 216) {
            $newType = 'TI';
        } else {
            $newType = 'CC';
        }

        $displayAge = round($months / 12, 2);
    } else {
        $years = (float) $ageYears;

        if ($years < 7) {
            $newType = 'RC';
        } elseif ($years < 18) {
            $newType = 'TI';
        } else {
            $newType = 'CC';
        }

        $displayAge = round($years, 2);
    }

    $identificationNumber = trim(
        (string) ($record['variables'][4] ?? '')
    );

    return $this->automaticOrValid(
        variable: 3,
        currentValue: $currentValue,
        newValue: $newType,
        reason:
            "La persona tiene aproximadamente {$displayAge} años. "
            . "El tipo de identificación se normalizó a {$newType}. "
            . "El número de identificación {$identificationNumber} "
            . 'se conservó sin cambios porque no puede deducirse '
            . 'automáticamente a partir de la edad.'
    );
}


private function miniMentalByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [16, 52], true)) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            $variable,
            $currentValue,
            'No fue posible calcular la edad para validar Mini-Mental.'
        );
    }

    if ($ageYears >= 60) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                16 => 21,
                52 => '1800-01-01',
            ],
            reason:
                "La persona tiene {$ageYears} años. Mini-Mental aplica por "
                . 'edad, pero no existe una valoración clínica real; el '
                . 'resultado queda en 21 y la fecha en 1800-01-01.'
        );
    }

    return $this->automaticBlock(
        record: $record,
        changes: [
            16 => 0,
            52 => self::NO_APLICA_DATE,
        ],
        reason:
            "La persona tiene {$ageYears} años y Mini-Mental no aplica por "
            . 'rango de edad. El bloque queda en resultado 0 y fecha '
            . '1845-01-01.'
    );
}

private function normalizeRespiratorySymptomaticPositiveBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [112, 113], true)) {
        return null;
    }

    $symptomatic = trim((string) ($record['variables'][18] ?? ''));

    if (! in_array($symptomatic, ['1', '2', '21'], true)) {
        return $this->manual(
            $variable,
            $currentValue,
            'No fue posible interpretar el estado de sintomático respiratorio.'
        );
    }

    return $this->automaticBlock(
        record: $record,
        changes: [
            112 => '1800-01-01',
            113 => 21,
        ],
        reason:
            'No existe una baciloscopia clínica confiable para el estado '
            . "respiratorio {$symptomatic}. El bloque se normaliza a fecha "
            . '1800-01-01 y resultado 21, riesgo no evaluado.'
    );
}

private function agudezaConResultadoSinFecha(
    int $variable,
    mixed $currentValue,
    array $record,
    int $resultVariable
): ?RuleDecision {
    if ($variable !== 62) {
        return null;
    }

    $result = trim((string) ($record['variables'][$resultVariable] ?? ''));
    $target = in_array($result, ['', '0', '21'], true)
        ? self::NO_APLICA_DATE
        : '1800-01-01';

    return $this->automaticOrValid(
        variable: 62,
        currentValue: $currentValue,
        newValue: $target,
        reason: 'Se registró un resultado de agudeza visual sin una fecha válida. Al no conocerse la fecha real, se usó el comodín coherente con el resultado.'
    );
}

private function hepatitisBSurfaceAntigenBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [78, 79], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][78] ?? ''));
    $result = trim((string) ($record['variables'][79] ?? ''));

    /*
     * Resultado 0 significa No aplica. En ese caso la fecha relacionada
     * tampoco puede conservarse como una fecha clínica real.
     */
    if ($result === '0' || $date === self::NO_APLICA_DATE) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                78 => self::NO_APLICA_DATE,
                79 => 0,
            ],
            reason:
                'El resultado del antígeno de superficie de hepatitis B '
                . 'está registrado como No aplica. La fecha relacionada '
                . 'también debe quedar en 1845-01-01.'
        );
    }

    if (in_array($date, self::NO_REALIZATION_DATES, true)) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                78 => $date,
                79 => 21,
            ],
            reason:
                'La fecha del antígeno de superficie de hepatitis B contiene '
                . 'un comodín de no realización. El resultado se registra '
                . 'como 21, no evaluado.'
        );
    }

    if ($result === '21') {
        return $this->automaticBlock(
            record: $record,
            changes: [
                78 => '1800-01-01',
                79 => 21,
            ],
            reason:
                'El resultado del antígeno de superficie de hepatitis B es '
                . '21, no evaluado. La fecha se normaliza a 1800-01-01.'
        );
    }

    if (
        $this->isRealReportDate($date)
        && in_array($result, ['4', '5'], true)
    ) {
        return RuleDecision::valid(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'Existe una fecha clínica real y un resultado válido del '
                . 'antígeno de superficie de hepatitis B.',
            rule: self::class
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La fecha y el resultado del antígeno de superficie de hepatitis B '
            . 'no forman una combinación segura para corrección automática.'
    );
}

private function creatinineClinicalResultRequired(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [106, 107], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][106] ?? ''));
    $rawResult = str_replace(
        ',',
        '.',
        trim((string) ($record['variables'][107] ?? ''))
    );

    if (
        $this->isRealReportDate($date)
        && is_numeric($rawResult)
        && (float) $rawResult >= 0.2
        && (float) $rawResult <= 25
    ) {
        return RuleDecision::valid(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'La fecha de creatinina es real y el resultado está entre '
                . '0.2 y 25.',
            rule: self::class
        );
    }

    if ($date === self::NO_APLICA_DATE) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                106 => self::NO_APLICA_DATE,
                107 => 0,
            ],
            reason:
                'La toma de creatinina está registrada como No aplica; '
                . 'el resultado relacionado se normaliza a 0.'
        );
    }

    if (in_array($date, self::NO_REALIZATION_DATES, true)) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                106 => $date,
                107 => 998,
            ],
            reason:
                'La fecha de creatinina contiene un comodín de no realización; '
                . 'el resultado relacionado se normaliza a 998.'
        );
    }

    /*
     * Una fecha real acompañada de 0, vacío o un valor fuera de rango
     * no permite recuperar el resultado clínico. Para no inventarlo,
     * se descarta la fecha aislada y se usa el bloque oficial "sin dato".
     */
    return $this->automaticBlock(
        record: $record,
        changes: [
            106 => '1800-01-01',
            107 => 998,
        ],
        reason:
            'La fecha de creatinina no tiene un resultado clínico válido. '
            . 'El bloque se registra como sin dato con fecha 1800-01-01 '
            . 'y resultado 998, evitando inventar un valor de laboratorio.'
    );
}

private function gestationalRiskWithoutPrenatalDate(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [35, 56, 58], true)) {
        return null;
    }

    $risk = trim((string) ($record['variables'][35] ?? ''));
    $firstPrenatalDate = trim((string) ($record['variables'][56] ?? ''));
    $lastControlDate = trim((string) ($record['variables'][58] ?? ''));

    if (in_array($risk, ['', '0'], true)) {
        if ($variable === 35) {
            return RuleDecision::valid(
                variable: 35,
                currentValue: $currentValue,
                reason:
                    'No existe una clasificación de riesgo gestacional '
                    . 'aplicable; la variable 35 se conserva.',
                rule: self::class
            );
        }

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: self::NO_APLICA_DATE,
            reason:
                'No existe una clasificación gestacional aplicable; '
                . 'la fecha prenatal se registra como No aplica.'
        );
    }

    if (
        $this->isRealReportDate($firstPrenatalDate)
        || $this->isRealReportDate($lastControlDate)
    ) {
        return RuleDecision::valid(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'Existe una clasificación de riesgo gestacional y al '
                . 'menos una fecha prenatal real válida.',
            rule: self::class
        );
    }

    /*
     * 4 y 5 representan una clasificación clínica confirmada y exigen
     * una fecha prenatal real. Si ninguna fecha existe, no se inventa:
     * el riesgo se degrada a 21 (no evaluado) y se conservan las fechas
     * como 1800-01-01 (sin dato), manteniendo coherencia con gestante=1.
     */
    return $this->automaticBlock(
        record: $record,
        changes: [
            35 => 21,
            56 => '1800-01-01',
            58 => '1800-01-01',
        ],
        reason:
            'No existe una fecha prenatal real que soporte la clasificación '
            . "gestacional {$risk}. Para no inventar una consulta, el riesgo "
            . 'se registra como 21, no evaluado, y las fechas permanecen '
            . 'como sin dato.'
    );
}

private function normalizeValeAllowedValues(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [40, 63], true)) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageYears)) {
        $olderThanTwelve = (int) floor((float) $ageYears) > 12;
    } elseif (is_numeric($ageMonths)) {
        $olderThanTwelve = (int) floor((float) $ageMonths / 12) > 12;
    } else {
        return $this->manual($variable, $currentValue, 'No fue posible calcular la edad para normalizar VALE.');
    }

    $target = $olderThanTwelve
        ? ($variable === 40 ? 0 : self::NO_APLICA_DATE)
        : ($variable === 40 ? 21 : '1800-01-01');

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason: $olderThanTwelve
            ? 'La persona es mayor de 12 años; VALE se registra como No aplica.'
            : 'La persona tiene 12 años o menos; el valor inválido de VALE se normalizó como no evaluado.'
    );
}

private function lactationSupportByGestation(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 51) {
        return null;
    }

    $gestante = trim((string) ($record['variables'][14] ?? ''));
    $target = $gestante === '1' ? '1800-01-01' : self::NO_APLICA_DATE;

    return $this->automaticOrValid(
        variable: 51,
        currentValue: $currentValue,
        newValue: $target,
        reason: $gestante === '1'
            ? 'La persona está gestante y no hay fecha real de promoción de lactancia; se registra sin dato.'
            : 'La persona no está gestante; la atención para promoción y apoyo de la lactancia se registra como No aplica.'
    );
}

private function hemoglobinGirlsTenToSeventeen(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [103, 104], true)) {
        return null;
    }

    $ageYears = $this->ageYears($record);
    $sex = $this->sex($record);

    if ($ageYears === null || $sex !== 'F' || $ageYears < 10 || $ageYears > 17) {
        return $this->manual($variable, $currentValue, 'El Error638 exige una mujer entre 10 y 17 años y no fue posible confirmar todas las condiciones.');
    }

    $target = $variable === 103 ? '1800-01-01' : 998;
    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason: 'En mujeres de 10 a 17 años la hemoglobina no puede quedar como No aplica. Sin dato clínico real, se normalizó a fecha sin dato y resultado 998.'
    );
}

private function specialDateResultBidirectional(
    int $variable,
    mixed $currentValue,
    array $record,
    int $dateVariable,
    int $resultVariable
): ?RuleDecision {
    if (! in_array($variable, [$dateVariable, $resultVariable], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][$dateVariable] ?? ''));
    $result = trim((string) ($record['variables'][$resultVariable] ?? ''));

    if (in_array($date, self::NO_REALIZATION_DATES, true)) {
        $target = $variable === $dateVariable ? $date : 998;
        return $this->automaticOrValid($variable, $currentValue, $target, 'La fecha de laboratorio es un comodín de no realización; el resultado debe ser 998.');
    }

    if ($result === '998') {
        $target = $variable === $dateVariable ? '1800-01-01' : 998;
        return $this->automaticOrValid($variable, $currentValue, $target, 'El resultado es 998; la fecha debe registrarse con un comodín de no realización.');
    }

    return $this->manual($variable, $currentValue, 'La combinación de fecha y resultado no coincide con los casos automáticos del error.');
}

private function infancySupplementByAge(
    int $variable,
    mixed $currentValue,
    array $record,
    int $expectedVariable,
    int $minMonths,
    int $maxMonths,
    string $label
): ?RuleDecision {
    if ($variable !== $expectedVariable) {
        return null;
    }

    $months = $record['age']['months'] ?? null;
    if (! is_numeric($months)) {
        return $this->manual($variable, $currentValue, "No fue posible calcular la edad para validar {$label}.");
    }

    $months = (float) $months;
    $applies = $months >= $minMonths && $months <= $maxMonths;
    $target = $applies ? 21 : 0;

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason: $applies
            ? "La persona está en el rango de edad de {$label}; No aplica no es válido y se registra 21, no evaluado."
            : "La persona está fuera del rango de edad de {$label}; se registra 0, No aplica."
    );
}

private function oralHealthByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [76, 102], true)) {
        return null;
    }

    $months = $record['age']['months'] ?? null;

    if (! is_numeric($months)) {
        return $this->manual(
            $variable,
            $currentValue,
            'No fue posible calcular la edad para validar atención en salud bucal.'
        );
    }

    if ((float) $months < 6) {
        return $this->automaticBlock(
            record: $record,
            changes: [
                76 => self::NO_APLICA_DATE,
                102 => 0,
            ],
            reason:
                'La persona es menor de 6 meses; la atención en salud bucal '
                . 'no aplica. El bloque queda en fecha 1845-01-01 y COP 0.'
        );
    }

    return $this->automaticBlock(
        record: $record,
        changes: [
            76 => '1800-01-01',
            102 => 21,
        ],
        reason:
            'La persona tiene 6 meses o más. La atención en salud bucal '
            . 'aplica por edad, pero no existe una atención clínica real; '
            . 'el bloque queda en fecha 1800-01-01 y COP 21.'
    );
}

private function futureServiceDate(
    int $variable,
    mixed $currentValue,
    string $code
): ?RuleDecision {
    $expected = $code === '125' ? 51 : 53;
    if ($variable !== $expected) {
        return null;
    }

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: '1800-01-01',
        reason: 'La fecha registrada es posterior al corte y no existe otra fecha confiable en el registro; se reemplazó por 1800-01-01, sin dato.'
    );
}

private function futureIntegralAssessmentDate(
    int $variable,
    mixed $currentValue
): ?RuleDecision {
    if ($variable !== 52) {
        return null;
    }

    return $this->automaticOrValid(52, $currentValue, '1800-01-01', 'La fecha de valoración integral es posterior al corte; se reemplazó por 1800-01-01, sin dato.');
}


private function cervicalIpsByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [86, 87, 88, 89, 90], true)) {
        return null;
    }

    $ageYears = $this->ageYears($record);

    if ($ageYears === null) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'el tamizaje de cáncer de cuello uterino.'
        );
    }

    /*
     * La validación exige ser mayor de 10 años.
     * Con 10 años cumplidos todavía no aplica.
     */
    if ($ageYears <= 10) {
        $target = match ($variable) {
            86 => 0,
            87 => self::NO_APLICA_DATE,
            88 => 0,
            89 => 0,
            90 => 0,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                "La persona tiene {$ageYears} años y no es mayor "
                . 'de 10 años. Todo el bloque de tamizaje de cáncer '
                . 'de cuello uterino debe registrarse como No aplica.'
        );
    }

    return RuleDecision::valid(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La persona es mayor de 10 años; se conserva el valor '
            . 'registrado en el bloque de tamizaje cervical.',
        rule: self::class
    );
}


private function auditoryNeonatalBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [37, 69], true)) {
        return null;
    }

    $result = trim((string) ($record['variables'][37] ?? ''));
    $date = trim((string) ($record['variables'][69] ?? ''));

    if ($result === '21') {
        $target = $variable === 37 ? 21 : '1800-01-01';

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'El resultado del tamizaje auditivo neonatal está '
                . 'registrado como 21. La fecha debe quedar con '
                . 'el comodín 1800-01-01.'
        );
    }

    if (in_array($date, self::NO_REALIZATION_DATES, true)) {
        $target = $variable === 37 ? 21 : $date;

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'La fecha de tamizaje auditivo neonatal contiene '
                . 'un comodín de no realización; el resultado debe '
                . 'registrarse como 21.'
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La combinación de fecha y resultado del tamizaje '
            . 'auditivo neonatal requiere revisión.'
    );
}

private function hemoglobinSpecialDateBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [103, 104], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][103] ?? ''));
    $result = trim((string) ($record['variables'][104] ?? ''));

    $recognizedDate = in_array($date, self::NO_REALIZATION_DATES, true);

    /*
     * Fechas especiales no reconocidas como 1803-01-01
     * se normalizan al comodín oficial 1800-01-01.
     */
    if (! $recognizedDate && preg_match('/^18\d{2}-01-01$/', $date)) {
        $recognizedDate = true;
        $date = '1800-01-01';
    }

    if ($recognizedDate || $result === '998') {
        $target = $variable === 103 ? '1800-01-01' : 998;

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'La hemoglobina no dispone de una toma o resultado '
                . 'clínico real. El bloque se normalizó a fecha '
                . '1800-01-01 y resultado 998.'
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La combinación de fecha y resultado de hemoglobina '
            . 'no coincide con los casos automáticos reconocidos.'
    );
}

private function normalizeGlycemiaDateResult(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [57, 105], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][105] ?? ''));
    $result = trim((string) ($record['variables'][57] ?? ''));

    /*
     * Un resultado 998 no puede acompañar una fecha real.
     * Sin un resultado clínico verdadero, se conserva 998 y
     * la fecha se normaliza a 1800-01-01.
     */
    if ($result === '998' || $this->isRealReportDate($date)) {
        $target = $variable === 105 ? '1800-01-01' : 998;

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'No existe un resultado clínico de glicemia utilizable. '
                . 'El bloque se normalizó a fecha 1800-01-01 y '
                . 'resultado 998 para mantener coherencia.'
        );
    }

    if (in_array($date, self::NO_REALIZATION_DATES, true)) {
        $target = $variable === 105 ? $date : 998;

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'La fecha de glicemia contiene un comodín de '
                . 'no realización; el resultado debe ser 998.'
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La combinación de fecha y resultado de glicemia '
            . 'requiere revisión.'
    );
}

/**
 * Errores 635 y 678: corregir una única pérdida mecánica de cero
 * inicial en el COP, sin crear información odontológica no reportada.
 * Para seis pacientes del período septiembre, la reconstrucción se
 * basa en el TXT ORIGINAL aportado, incluidos los valores 3200.
 */
private function normalizeCop635678(array $record, string $code): RuleDecision
{
    $v = $record['variables'] ?? [];
    $cop = trim((string) ($v[102] ?? ''));
    $date = trim((string) ($v[76] ?? ''));

    /*
     * Septiembre 2026: los seis COP completos existen en el TXT
     * original del mismo período. La conversión intermedia eliminó
     * únicamente ceros iniciales. Restaurar estos valores ya
     * documentados, verificando número de línea, tipo de documento,
     * identificación, fecha odontológica y valor truncado.
     * NO aplicar esta equivalencia a otros pacientes ni meses.
     */
    if (($record['cutoff_date'] ?? null) === '2026-09-30') {
        $sourceCop = [
            '18|CC|56062422|2025-12-18|80000002408' => '080000002408',
            '222|CC|15239801|2025-04-08|70021000426' => '070021000426',
            '274|CC|15237955|2025-08-25|3200' => '000000003200',
            '312|PT|6726768|2026-09-24|90003002012' => '090003002012',
            '319|PT|4701267|2025-05-19|3200' => '000000003200',
            '332|CC|1065661374|2026-05-05|30003002606' => '030003002606',
        ];

        $sourceKey = implode('|', [
            trim((string) ($record['record_number'] ?? '')),
            strtoupper(trim((string) ($v[3] ?? ''))),
            trim((string) ($v[4] ?? '')),
            $date,
            $cop,
        ]);
        if (isset($sourceCop[$sourceKey])) {
            return $this->automaticOrValid(
                102,
                $cop,
                $sourceCop[$sourceKey],
                'Error ' . $code . ': COP restaurado exactamente '
                . 'desde el TXT original de septiembre de 2026, '
                . 'sin inferir diagnóstico ni estado de edentulismo.'
            );
        }
    }

    if (in_array($cop, ['0', '21'], true)) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && ! in_array($date, array_merge(
                [self::NO_APLICA_DATE],
                self::NO_REALIZATION_DATES
            ), true)
        ) {
            return $this->manual(
                102,
                $cop,
                'Errores ' . $code . ': existe fecha real de atención odontológica, '
                . 'pero el COP es un comodín. Verificar resultado y fecha en '
                . 'la historia clínica.'
            );
        }

        return $this->automaticOrValid(
            102, $cop, $cop, 'COP con comodín permitido; revisar fecha si la EPS insiste.'
        );
    }

    if (preg_match('/^\d{12}$/D', $cop)) {
        return RuleDecision::valid(
            variable: 102,
            currentValue: $cop,
            reason: 'El COP ya tiene 12 dígitos; revisar su información odontológica si continúa el rechazo.',
            rule: self::class
        );
    }

    $hasRealDate = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) === 1
        && ! in_array($date, array_merge(
            [self::NO_APLICA_DATE],
            self::NO_REALIZATION_DATES
        ), true)
        && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

    if ($hasRealDate && preg_match('/^\d{11}$/D', $cop)) {
        $candidate = '0' . $cop;
        $pairs = str_split($candidate, 2);
        $ageMonths = $record['age']['months'] ?? null;
        $ageYears = $record['age']['years'] ?? null;
        $underFive = is_numeric($ageMonths)
            ? (float) $ageMonths < 60
            : (is_numeric($ageYears) && (float) $ageYears < 5);
        $limit = $underFive ? 20 : 32;
        $plausible = count($pairs) === 6;

        foreach ($pairs as $pair) {
            if ((int) $pair > $limit) {
                $plausible = false;
                break;
            }
        }

        if ($plausible) {
            return $this->automaticOrValid(
                variable: 102,
                currentValue: $cop,
                newValue: $candidate,
                reason: 'Error ' . $code . ': COP de 11 dígitos con fecha odontológica '
                    . 'real y seis componentes numéricos plausibles. Se restituye '
                    . 'el cero inicial perdido, conservando los demás dígitos.'
            );
        }
    }

    return $this->manual(
        variable: 102,
        currentValue: $cop,
        reason: 'Errores 635/678: COP ' . $cop . ' y fecha odontológica ' . $date
            . ' no permiten reconstrucción segura. Si el paciente es edéntulo '
            . 'confirmado, usar 000000000000; en caso contrario, recuperar '
            . 'los seis componentes COP de la historia clínica.'
    );
}

private function oralHealthCopBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [76, 102], true)) {
        return null;
    }

    /*
     * Para eliminar la contradicción entre Error597 y Error634,
     * el bloque final coherente cuando no existe atención real es:
     *
     * 76  = 1845-01-01
     * 102 = 0
     */
    $target = $variable === 76
        ? self::NO_APLICA_DATE
        : 0;

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason:
            'La atención odontológica y el COP deben registrar '
            . 'No aplica de forma conjunta. El bloque se normalizó '
            . 'a fecha 1845-01-01 y COP igual a 0.'
    );
}

private function cytologyQualityBlock(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [88, 89], true)) {
        return null;
    }

    $result = trim((string) ($record['variables'][88] ?? ''));

    /*
     * Cuando no existe un resultado citológico real y el bloque
     * está no evaluado, la calidad de muestra no debe contener
     * una clasificación clínica.
     */
    if (in_array($result, ['', '0', '21'], true)) {
        $target = $variable === 88 ? 21 : 999;

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'No existe un resultado citológico real. Se conserva '
                . 'el resultado como 21 y la calidad de muestra se '
                . 'normaliza a 999.'
        );
    }

    return RuleDecision::valid(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'Existe un resultado citológico informado y se conserva '
            . 'la calidad de la muestra.',
        rule: self::class
    );
}

private function validateCopComponentsByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 102) {
        return null;
    }

    $raw = trim((string) $currentValue);
    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (! is_numeric($ageMonths) && ! is_numeric($ageYears)) {
        return $this->manual(
            variable: 102,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar los '
                . 'componentes del COP por persona.'
        );
    }

    $isUnderFive = is_numeric($ageMonths)
        ? (float) $ageMonths < 60
        : (float) $ageYears < 5;

    $maxComponent = $isUnderFive ? 20 : 28;

    if (! preg_match('/^\d{12}$/', $raw)) {
        return $this->manual(
            variable: 102,
            currentValue: $currentValue,
            reason:
                'El COP por persona debe contener 12 dígitos, organizados '
                . 'en seis componentes de dos dígitos. El valor actual '
                . "'{$raw}' no tiene una estructura válida y no puede "
                . 'corregirse sin información odontológica confiable.'
        );
    }

    $components = str_split($raw, 2);

    foreach ($components as $component) {
        $value = (int) $component;

        if ($value < 0 || $value > $maxComponent) {
            return $this->manual(
                variable: 102,
                currentValue: $currentValue,
                reason:
                    "La persona está en un rango de edad cuyo valor máximo "
                    . "por componente COP es {$maxComponent}. El componente "
                    . "'{$component}' está fuera del rango permitido."
            );
        }
    }

    return RuleDecision::valid(
        variable: 102,
        currentValue: $currentValue,
        reason:
            "Los seis componentes del COP están entre 00 y "
            . str_pad((string) $maxComponent, 2, '0', STR_PAD_LEFT)
            . ' para la edad de la persona.',
        rule: self::class
    );
}

private function normalizeInfantWeight(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 30) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;

    if (! is_numeric($ageMonths)) {
        return $this->manual(
            variable: 30,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar el peso.'
        );
    }

    $rawWeight = str_replace(
        ',',
        '.',
        trim((string) $currentValue)
    );

    if (! is_numeric($rawWeight)) {
        return $this->manual(
            variable: 30,
            currentValue: $currentValue,
            reason:
                'El peso no contiene un valor numérico válido.'
        );
    }

    $weight = (float) $rawWeight;

    if ((float) $ageMonths >= 24) {
        return RuleDecision::valid(
            variable: 30,
            currentValue: $currentValue,
            reason:
                'La regla de peso infantil aplica únicamente '
                . 'a menores de 2 años.',
            rule: self::class
        );
    }

    if ($weight >= 1 && $weight <= 15) {
        return RuleDecision::valid(
            variable: 30,
            currentValue: $currentValue,
            reason:
                'El peso ya está dentro del rango permitido.',
            rule: self::class
        );
    }

    /*
     * Intenta recuperar un decimal omitido.
     *
     * 90  -> 9.0
     * 110 -> 11.0
     */
    $candidateOneDecimal = $weight / 10;

    if (
        $candidateOneDecimal >= 1
        && $candidateOneDecimal <= 15
    ) {
        return $this->automaticOrValid(
            variable: 30,
            currentValue: $currentValue,
            newValue: $candidateOneDecimal,
            reason:
                'El peso estaba fuera del rango permitido y se detectó '
                . 'una posible omisión del separador decimal. '
                . "Se normalizó {$weight} a {$candidateOneDecimal} kg."
        );
    }

    /*
     * Intenta recuperar dos decimales omitidos.
     *
     * 200 -> 2.00
     * 350 -> 3.50
     */
    $candidateTwoDecimals = $weight / 100;

    if (
        $candidateTwoDecimals >= 1
        && $candidateTwoDecimals <= 15
    ) {
        return $this->automaticOrValid(
            variable: 30,
            currentValue: $currentValue,
            newValue: $candidateTwoDecimals,
            reason:
                'El peso estaba fuera del rango permitido y se detectó '
                . 'una posible omisión del separador decimal. '
                . "Se normalizó {$weight} a {$candidateTwoDecimals} kg."
        );
    }

    return $this->manual(
        variable: 30,
        currentValue: $currentValue,
        reason:
            "La persona es menor de 2 años y el peso {$weight} kg "
            . 'está fuera del rango de 1 a 15 kg. No fue posible '
            . 'obtener un valor válido mediante una corrección '
            . 'determinística del separador decimal.'
    );
}


private function protegerContraceptionCounselingByAge(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if ($variable !== 53) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageMonths)) {
        $isTenOrOlder = (float) $ageMonths >= 120;
        $displayAge = round((float) $ageMonths / 12, 2);
    } elseif (is_numeric($ageYears)) {
        $isTenOrOlder = (float) $ageYears >= 10;
        $displayAge = round((float) $ageYears, 2);
    } else {
        return $this->manual(
            variable: 53,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar la fecha '
                . 'de asesoría en anticoncepción reportada por Proteger.'
        );
    }

    $target = $isTenOrOlder
        ? '1800-01-01'
        : self::NO_APLICA_DATE;

    return $this->automaticOrValid(
        variable: 53,
        currentValue: $currentValue,
        newValue: $target,
        reason: $isTenOrOlder
            ? "La persona tiene aproximadamente {$displayAge} años. En Proteger, desde los 10 años la asesoría en anticoncepción no puede quedar como No aplica; se registró 1800-01-01, Sin dato."
            : "La persona tiene aproximadamente {$displayAge} años y es menor de 10 años; la asesoría en anticoncepción se registra como 1845-01-01, No aplica."
    );
}

private function protegerOralHealthNoAplica(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [76, 102], true)) {
        return null;
    }

    $cop = trim((string) ($record['variables'][102] ?? ''));

    if ($cop !== '0') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El Error226 de Proteger exige que el COP esté registrado '
                . 'como 0 para normalizar la fecha de atención en salud '
                . 'bucal como No aplica.'
        );
    }

    $target = $variable === 76
        ? self::NO_APLICA_DATE
        : 0;

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason:
            'En Proteger, el COP por persona está registrado como 0, '
            . 'No aplica. La fecha de atención en salud bucal debe quedar '
            . 'como 1845-01-01 y el COP debe conservarse en 0.'
    );
}

private function protegerOralHealthCopWithoutVisit(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    if (! in_array($variable, [76, 102], true)) {
        return null;
    }

    $date = trim((string) ($record['variables'][76] ?? ''));
    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (is_numeric($ageMonths)) {
        $isSixMonthsOrOlder = (float) $ageMonths >= 6;
    } elseif (is_numeric($ageYears)) {
        $isSixMonthsOrOlder = (float) $ageYears >= 0.5;
    } else {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar el COP '
                . 'por persona reportado por Proteger.'
        );
    }

    if (! $isSixMonthsOrOlder) {
        $target = $variable === 76
            ? self::NO_APLICA_DATE
            : 0;

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $target,
            reason:
                'La persona es menor de 6 meses. La atención en salud '
                . 'bucal y el COP deben registrarse como No aplica.'
        );
    }

    if (! in_array($date, self::NO_REALIZATION_DATES, true)) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El Error343 de Proteger exige un comodín de no realización '
                . 'o sin dato en la fecha de salud bucal. La fecha actual no '
                . 'coincide con los comodines reconocidos.'
        );
    }

    $target = $variable === 76
        ? $date
        : 0;

    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $target,
        reason:
            'En Proteger, desde los 6 meses, cuando la fecha de atención '
            . 'en salud bucal contiene un comodín de no realización o sin '
            . 'dato, el COP por persona debe registrarse como 0.'
    );
}

private function protegerOralHealthCop(
    int $variable,
    mixed $currentValue,
    array $record
): ?RuleDecision {
    /*
     * Variable 76:
     * Fecha de atención en salud bucal por profesional
     * en odontología.
     *
     * Variable 102:
     * COP por persona.
     *
     * Primero se valida la edad.
     *
     * Menor de 6 meses:
     * 76  = 1845-01-01
     * 102 = 0
     *
     * Desde los 6 meses:
     * 1800-01-01 <-> 21
     *
     * Una fecha real solo puede conservarse cuando el COP
     * contiene una estructura odontológica real de 12 dígitos.
     */
    if (! in_array($variable, [76, 102], true)) {
        return null;
    }

    $ageMonths = $record['age']['months'] ?? null;
    $ageYears = $record['age']['years'] ?? null;

    if (
        ! is_numeric($ageMonths)
        && ! is_numeric($ageYears)
    ) {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'No fue posible calcular la edad para validar '
                . 'la atención en salud bucal y el COP por persona.'
        );
    }

    $months = is_numeric($ageMonths)
        ? (float) $ageMonths
        : (float) $ageYears * 12;

    $variables = $record['variables'] ?? [];

    $oralHealthDate = trim(
        (string) ($variables[76] ?? '')
    );

    $cop = trim(
        (string) ($variables[102] ?? '')
    );

    $hasRealDate = $this->isRealReportDate(
        $oralHealthDate
    );

    /*
     * El COP se considera un resultado odontológico estructurado
     * cuando contiene exactamente 12 números.
     */
    $hasTwelveDigitCop =
        preg_match('/^\d{12}$/', $cop) === 1;

    /*
     * PRIMERA CONDICIÓN:
     * Menores de 6 meses.
     *
     * La atención odontológica no aplica.
     */
    if ($months < 6) {
        $targetValue = match ($variable) {
            76 => self::NO_APLICA_DATE,
            102 => 0,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La persona es menor de 6 meses. La atención '
                . 'en salud bucal no aplica; la variable 76 debe '
                . 'quedar en 1845-01-01 y la variable 102 en 0.'
        );
    }

    /*
     * Desde aquí, la persona tiene 6 meses o más.
     * Por edad, la atención en salud bucal sí aplica.
     */

    /*
     * CASO 1:
     *
     * 76 = 1800-01-01
     * 102 debe ser 21.
     *
     * La variable 76 ya está correcta y no se cambia.
     */
    if ($oralHealthDate === '1800-01-01') {
        $targetValue = match ($variable) {
            76 => '1800-01-01',
            102 => 21,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La persona tiene 6 meses o más y la fecha de '
                . 'atención en salud bucal está correctamente '
                . 'registrada como 1800-01-01. El COP debe '
                . 'registrarse como 21, riesgo no evaluado.'
        );
    }

    /*
     * CASO 2:
     *
     * 102 = 21
     * y 76 contiene una fecha real o 1845-01-01.
     *
     * Se conserva 102 = 21 y se cambia 76 a 1800-01-01.
     */
    if (
        $cop === '21'
        && (
            $hasRealDate
            || $oralHealthDate === self::NO_APLICA_DATE
        )
    ) {
        $targetValue = match ($variable) {
            76 => '1800-01-01',
            102 => 21,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La persona tiene 6 meses o más y el COP está '
                . 'registrado como 21, riesgo no evaluado. '
                . 'La fecha de atención no puede conservarse como '
                . 'fecha real ni como 1845-01-01; debe registrarse '
                . 'como 1800-01-01.'
        );
    }

    /*
     * CASO 3:
     *
     * 102 contiene 12 números, pero 76 no tiene fecha real.
     *
     * Ejemplo:
     * 102 = 140017010032
     * 76  = 1845-01-01, vacío u otro comodín.
     *
     * Al no existir una fecha real que respalde el COP,
     * el bloque se normaliza:
     *
     * 76  = 1800-01-01
     * 102 = 21
     */
    if (
        $hasTwelveDigitCop
        && ! $hasRealDate
    ) {
        $targetValue = match ($variable) {
            76 => '1800-01-01',
            102 => 21,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La persona tiene 6 meses o más y el COP contiene '
                . "12 dígitos ({$cop}), pero la variable 76 no "
                . 'contiene una fecha real de atención odontológica. '
                . 'El bloque se normalizó a fecha 1800-01-01 '
                . 'y COP 21, riesgo no evaluado.'
        );
    }

    /*
     * CASO 4:
     *
     * Desde los 6 meses, la combinación:
     *
     * 76  = 1845-01-01
     * 102 = 0
     *
     * no es válida porque No aplica no corresponde por edad.
     */
    if (
        $oralHealthDate === self::NO_APLICA_DATE
        || $cop === '0'
    ) {
        $targetValue = match ($variable) {
            76 => '1800-01-01',
            102 => 21,
        };

        return $this->automaticOrValid(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $targetValue,
            reason:
                'La persona tiene 6 meses o más, por lo que la '
                . 'atención en salud bucal sí aplica. El bloque no '
                . 'puede permanecer como No aplica; se normalizó '
                . 'a fecha 1800-01-01 y COP 21.'
        );
    }

    /*
     * CASO VÁLIDO:
     *
     * Fecha real y COP de 12 dígitos.
     */
    if (
        $hasRealDate
        && $hasTwelveDigitCop
    ) {
        return RuleDecision::valid(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'La persona tiene 6 meses o más, existe una fecha '
                . 'real de atención odontológica y el COP contiene '
                . '12 dígitos. El bloque es coherente.',
            rule: self::class
        );
    }

    return $this->manual(
        variable: $variable,
        currentValue: $currentValue,
        reason:
            'La combinación de edad, fecha de atención en salud '
            . 'bucal y COP por persona no coincide con los casos '
            . 'automáticos reconocidos.'
    );
}

    /**
     * @param array<int, mixed> $changes
     */
    private function automaticBlock(
        array $record,
        array $changes,
        string $reason
    ): RuleDecision {
        return RuleDecision::automaticMany(
            changes: $changes,
            currentValues: $record['variables'] ?? [],
            reason: $reason,
            rule: self::class
        );
    }

    private function automaticOrValid(
        int $variable,
        mixed $currentValue,
        mixed $newValue,
        string $reason
    ): RuleDecision {
        if (
            trim((string) $currentValue)
            === trim((string) $newValue)
        ) {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $currentValue,
                reason:
                    $reason . ' El campo ya contiene el valor correcto.',
                rule: self::class
            );
        }

        return RuleDecision::automatic(
            variable: $variable,
            currentValue: $currentValue,
            newValue: $newValue,
            reason: $reason,
            rule: self::class
        );
    }
private function normalizePersonName(
    int $variable,
    mixed $currentValue
): RuleDecision {
    $originalValue = trim(
        (string) $currentValue
    );

    if ($originalValue === '') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El nombre o apellido está vacío y no puede '
                . 'corregirse automáticamente.'
        );
    }

    $normalizedValue = Str::of($originalValue)
        ->ascii()
        ->upper()
        ->replaceMatches('/\s+/', '')
        ->replaceMatches('/[^A-ZÑ]/', '')
        ->toString();

    if ($normalizedValue === '') {
        return $this->manual(
            variable: $variable,
            currentValue: $currentValue,
            reason:
                'El nombre o apellido no contiene caracteres válidos '
                . 'después de la normalización.'
        );
    }



    return $this->automaticOrValid(
        variable: $variable,
        currentValue: $currentValue,
        newValue: $normalizedValue,
        reason:
            'Se eliminaron espacios y caracteres no permitidos '
            . 'del nombre o apellido para cumplir la estructura.'
    );
}

    
}