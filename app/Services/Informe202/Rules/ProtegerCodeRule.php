<?php

namespace App\Services\Informe202\Rules;

use App\Services\Informe202\Engine\RuleDecision;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Throwable;

class ProtegerCodeRule implements RuleInterface
{
    private const NO_APLICA_DATE = '1845-01-01';

    private const NO_DATA_DATE = '1800-01-01';

    public function supports(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): bool {
        return $this->origin($error) === 'proteger'
            && $this->resolvePattern($error) !== null;
    }

    public function evaluate(
        int $variable,
        array $definition,
        array $record,
        array $error
    ): ?RuleDecision {
        $pattern = $this->resolvePattern($error);

        if ($pattern === null) {
            return null;
        }

        return match ($pattern) {
            'mini_mental_sin_fecha_valoracion' => $this->normalizeMiniMentalWithoutIntegralDate(
                variable: $variable,
                record: $record
            ),
            'tacto_rectal_no_evaluado' => $this->tactoRectalNoEvaluado(
                variable: $variable,
                record: $record
            ),
            'tacto_rectal_mujer' => $this->tactoRectalMujer(
                variable: $variable,
                record: $record
            ),
            'sangre_oculta_edad' => $this->screeningByAge(
                variable: $variable,
                record: $record,
                resultVariable: 24,
                dateVariable: 67,
                label: 'prueba de sangre oculta'
            ),
            'peso_sin_medicion' => $this->measurementNotTaken(
                variable: $variable,
                record: $record,
                dateVariable: 29,
                resultVariable: 30,
                specialValue: 999,
                label: 'peso'
            ),
            'peso_fuera_rango' => $this->normalizeWeight(
                variable: $variable,
                record: $record
            ),
            'talla_sin_medicion' => $this->measurementNotTaken(
                variable: $variable,
                record: $record,
                dateVariable: 31,
                resultVariable: 32,
                specialValue: 999,
                label: 'talla'
            ),
            'talla_fuera_rango' => $this->normalizeHeight(
                variable: $variable,
                record: $record
            ),
            'fecha_probable_parto' => $this->normalizeExpectedDeliveryDate(
                variable: $variable,
                record: $record
            ),
            'colonoscopia_edad' => $this->screeningByAge(
                variable: $variable,
                record: $record,
                resultVariable: 36,
                dateVariable: 66,
                label: 'colonoscopia de tamizaje'
            ),
            'vale_no_evaluado' => $this->normalizeVale(
                variable: $variable,
                record: $record
            ),
            'metodo_anticonceptivo_edad' => $this->normalizeContraceptiveSupply(
                variable: $variable,
                record: $record
            ),
            'salud_bucal_cop' => $this->normalizeOralHealth(
                variable: $variable,
                record: $record
            ),
            'hepatitis_b_fecha_sin_resultado' => $this->normalizeHepatitisB(
                variable: $variable,
                record: $record
            ),
            'resultado_vih' => $this->normalizeHivResult(
                variable: $variable,
                record: $record
            ),
            'tamizaje_cervical_sin_fecha' => $this->normalizeCervicalScreening(
                variable: $variable,
                record: $record
            ),
            'mamografia_no_aplica' => $this->normalizeMammography(
                variable: $variable,
                record: $record
            ),
            'fortificacion_por_edad' => $this->normalizeFortification(
                variable: $variable,
                record: $record
            ),
            default => null,
        };
    }

    private function resolvePattern(array $error): ?string
    {
        $code = $this->normalizeCode(
            $error['codigo_original']
                ?? $error['codigo']
                ?? null
        );

        $message = $this->normalizeText(
            (string) ($error['campo'] ?? '')
            . ' '
            . (string) ($error['mensaje'] ?? '')
        );

        return match (true) {
            $code === '17'
                || str_contains(
                    $message,
                    'resultado de prueba mini mental state'
                )
                    && str_contains(
                        $message,
                        'fecha de consulta de valoracion integral valida'
                    )
                => 'mini_mental_sin_fecha_valoracion',

            $code === '30'
                || str_contains($message, 'si resultado de tacto rectal es riesgo no evaluado')
                => 'tacto_rectal_no_evaluado',

            $code === '31'
                || str_contains($message, 'si es sexo f debe registrar no aplica en la fecha y el resultado de tacto rectal')
                => 'tacto_rectal_mujer',

            $code === '35'
                || str_contains($message, 'fecha prueba sangre oculta')
                    && str_contains($message, 'edad debe ser entre 50')
                => 'sangre_oculta_edad',

            $code === '53'
                || str_contains($message, 'si no registra el peso')
                    && str_contains($message, 'no debe registrar fecha')
                => 'peso_sin_medicion',

            $code === '54'
                || str_contains($message, 'peso de la persona debe ser mayor a 0 2')
                => 'peso_fuera_rango',

            $code === '60'
                || str_contains($message, 'si no registra la talla')
                    && str_contains($message, 'no debe registrar fecha')
                => 'talla_sin_medicion',

            $code === '61'
                || str_contains($message, 'talla de la persona debe ser mayor a 20')
                => 'talla_fuera_rango',

            $code === '63'
                || str_contains($message, 'fecha probable de parto')
                    && str_contains($message, 'fecha de corte mas 280 dias')
                => 'fecha_probable_parto',

            $code === '71'
                || str_contains($message, 'fecha de colonoscopia')
                    && str_contains($message, 'edad debe estar entre 50 y 75')
                => 'colonoscopia_edad',

            $code === '86'
                || str_contains($message, 'resultado de tamizaje vale es 21')
                => 'vale_no_evaluado',

            in_array($code, ['150', '151'], true)
                || str_contains($message, 'suministro de metodo anticonceptivo')
                    && (
                        str_contains($message, 'edad esta entre 10 y 59')
                        || str_contains($message, 'resgistro no aplica')
                        || str_contains($message, 'registro no aplica')
                    )
                => 'metodo_anticonceptivo_edad',

            in_array($code, ['223', '226', '343'], true)
                || str_contains($message, 'cop por persona')
                    && str_contains($message, 'atencion en salud bucal')
                || str_contains($message, 'si registra fecha atencion en salud bucal')
                    && str_contains($message, 'mayor o igual a 6 meses')
                => 'salud_bucal_cop',

            $code === '234'
                || str_contains($message, 'fecha antigeno de superficie hepatitis b')
                    && str_contains($message, 'debe registrar resultado')
                => 'hepatitis_b_fecha_sin_resultado',

            $code === '253'
                && str_contains($message, 'resultado de prueba para vih')
                || str_contains($message, 'error en valores permitidos resultado de la prueba para vih')
                => 'resultado_vih',

            in_array($code, ['263', '267'], true)
                || str_contains($message, 'si registra tamizaje cancer de cuello uterino debe registrar fecha')
                || str_contains($message, 'si registro 21 en tamizaje cancer de cuello uterino')
                    && str_contains($message, '21 en el resultado')
                => 'tamizaje_cervical_sin_fecha',

            in_array($code, ['316', '317'], true)
                || str_contains($message, 'mujer mayor o igual de 50 anos')
                    && str_contains($message, 'mamografia')
                => 'mamografia_no_aplica',

            $code === '401'
                || str_contains($message, 'fortificacion casera')
                    && str_contains($message, 'no es valido registrar no aplica')
                => 'fortificacion_por_edad',

            default => null,
        };
    }

    /**
     * Proteger código 17.
     *
     * Si hay un resultado de prueba mini-mental state en la variable 16
     * pero la variable 52 no contiene una fecha real de Consulta de
     * Valoración Integral, no se inventa la fecha: se normaliza el
     * resultado mini-mental a 0 (No aplica).
     */
    private function normalizeMiniMentalWithoutIntegralDate(
        int $variable,
        array $record
    ): RuleDecision {
        $variables = $record['variables'] ?? [];

        $currentResult = trim(
            (string) ($variables[16] ?? '')
        );

        $integralDateRaw = trim(
            (string) ($variables[52] ?? '')
        );

        /*
         * Solo se considera fecha real si es una fecha válida y además
         * no corresponde a los comodines 1800-01-01 / 1845-01-01.
         */
        $integralDate = $this->parseDate(
            $integralDateRaw
        );

        $hasRealIntegralDate =
            $integralDate instanceof DateTimeImmutable
            && ! in_array(
                $integralDateRaw,
                [
                    self::NO_DATA_DATE,
                    self::NO_APLICA_DATE,
                ],
                true
            );

        /*
         * Si ya está en 0, la fila ya cumple la regla.
         */
        if ($currentResult === '0') {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $variables[$variable] ?? null,
                reason:
                    'El resultado mini-mental ya está en 0 (No aplica).',
                rule: self::class
            );
        }

        /*
         * Si sí existe una fecha real de valoración integral, no se debe
         * borrar un resultado clínico válido.
         */
        if ($hasRealIntegralDate) {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $variables[$variable] ?? null,
                reason:
                    'Existe una fecha real de Consulta de Valoración Integral; '
                    . 'se conserva el resultado mini-mental.',
                rule: self::class
            );
        }

        /*
         * No hay fecha real: no inventamos fecha.
         * Se cambia únicamente la variable 16 a 0.
         */
        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                16 => 0,
            ],
            reason:
                'Hay resultado de prueba mini-mental state pero no existe '
                . 'una fecha real de Consulta de Valoración Integral '
                . '(variable 52). No se inventa una fecha; el resultado '
                . 'mini-mental se normalizó a 0 (No aplica).'
        );
    }

    private function tactoRectalNoEvaluado(
        int $variable,
        array $record
    ): RuleDecision {
        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                22 => 21,
                64 => self::NO_DATA_DATE,
            ],
            reason:
                'El tacto rectal está registrado como riesgo no evaluado. '
                . 'La fecha relacionada se normalizó a 1800-01-01.'
        );
    }

    private function tactoRectalMujer(
        int $variable,
        array $record
    ): RuleDecision {
        if ($this->sex($record) !== 'F') {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    'La regla de tacto rectal como No aplica por sexo '
                    . 'solo puede ejecutarse cuando el sexo es F.'
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                22 => 0,
                64 => self::NO_APLICA_DATE,
            ],
            reason:
                'Para sexo F, el resultado y la fecha del tacto rectal '
                . 'deben registrarse conjuntamente como No aplica.'
        );
    }

    private function screeningByAge(
        int $variable,
        array $record,
        int $resultVariable,
        int $dateVariable,
        string $label
    ): RuleDecision {
        $ageMonths = $this->ageMonths($record);

        if ($ageMonths === null) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    "No fue posible calcular la edad para validar {$label}."
            );
        }

        $insideRange = $ageMonths >= 600
            && $ageMonths < 912;

        return $this->block(
            variable: $variable,
            record: $record,
            changes: $insideRange
                ? [
                    $resultVariable => 21,
                    $dateVariable => self::NO_DATA_DATE,
                ]
                : [
                    $resultVariable => 0,
                    $dateVariable => self::NO_APLICA_DATE,
                ],
            reason: $insideRange
                ? "La persona está entre 50 y 75 años. {$label} queda como no evaluado."
                : "La persona está fuera del rango de 50 a 75 años. {$label} queda como No aplica."
        );
    }

    private function measurementNotTaken(
        int $variable,
        array $record,
        int $dateVariable,
        int $resultVariable,
        int $specialValue,
        string $label
    ): RuleDecision {
        $result = trim(
            (string) ($record['variables'][$resultVariable] ?? '')
        );

        if ($result !== (string) $specialValue) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    "La fecha de {$label} solo puede cambiarse automáticamente "
                    . "cuando el resultado sea {$specialValue}."
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                $dateVariable => self::NO_DATA_DATE,
                $resultVariable => $specialValue,
            ],
            reason:
                "No existe una medición de {$label}. La fecha se normalizó "
                . 'a 1800-01-01 y se conservó el comodín correspondiente.'
        );
    }

    private function normalizeWeight(
        int $variable,
        array $record
    ): RuleDecision {
        $current = $record['variables'][30] ?? null;
        $numeric = $this->extractNumeric($current);

        if ($numeric === null) {
            return $this->manual(
                variable: 30,
                record: $record,
                reason:
                    'El peso no contiene un número que pueda normalizarse de forma segura.'
            );
        }

        $candidate = $numeric;

        if ($candidate <= 0.2 || $candidate > 250) {
            $candidate = null;

            foreach ([10, 100, 1000, 10000] as $divisor) {
                $value = $numeric / $divisor;

                if ($value > 0.2 && $value <= 250) {
                    $candidate = $value;
                    break;
                }
            }
        }

        if ($candidate === null) {
            return $this->manual(
                variable: 30,
                record: $record,
                reason:
                    'El peso está fuera del rango 0.2–250 kg y no se detectó '
                    . 'un desplazamiento decimal determinístico.'
            );
        }

        $normalized = $this->formatDecimal(
            value: $candidate,
            maximumDecimals: 2
        );

        if (mb_strlen($normalized) > 5) {
            return $this->manual(
                variable: 30,
                record: $record,
                reason:
                    'El peso normalizado todavía supera la longitud máxima de 5 caracteres.'
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [30 => $normalized],
            reason:
                'Se eliminaron unidades o se corrigió un desplazamiento '
                . 'decimal evidente en el peso, conservando un valor entre 0.2 y 250 kg.'
        );
    }

    private function normalizeHeight(
        int $variable,
        array $record
    ): RuleDecision {
        $current = $record['variables'][32] ?? null;
        $numeric = $this->extractNumeric($current);

        if ($numeric === null) {
            return $this->manual(
                variable: 32,
                record: $record,
                reason:
                    'La talla no contiene un número que pueda normalizarse de forma segura.'
            );
        }

        if ($numeric <= 20 || $numeric > 225) {
            foreach ([10, 100, 1000] as $divisor) {
                $candidate = $numeric / $divisor;

                if ($candidate > 20 && $candidate <= 225) {
                    $numeric = $candidate;
                    break;
                }
            }
        }

        $normalized = (int) round(
            $numeric,
            0,
            PHP_ROUND_HALF_UP
        );

        if ($normalized <= 20 || $normalized > 225) {
            return $this->manual(
                variable: 32,
                record: $record,
                reason:
                    'La talla está fuera del rango 20–225 cm y no se '
                    . 'detectó una corrección determinística.'
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [32 => $normalized],
            reason:
                'Se eliminaron unidades y la talla se convirtió a centímetros '
                . 'enteros, como exige la estructura 202.'
        );
    }

    private function normalizeExpectedDeliveryDate(
        int $variable,
        array $record
    ): RuleDecision {
        $current = $record['variables'][33] ?? null;
        $date = $this->parseDate($current);
        $cutoff = $this->parseDate(
            $record['cutoff_date']
                ?? $record['report_cutoff_date']
                ?? $record['fecha_corte']
                ?? null
        );

        if ($date === null || $cutoff === null) {
            return $this->manual(
                variable: 33,
                record: $record,
                reason:
                    'No fue posible interpretar la fecha probable de parto '
                    . 'o la fecha de corte del informe.'
            );
        }

        $maximum = $cutoff->modify('+280 days');

        if ($date <= $maximum) {
            return RuleDecision::valid(
                variable: 33,
                currentValue: $current,
                reason:
                    'La fecha probable de parto ya está dentro de los 280 días posteriores al corte.',
                rule: self::class
            );
        }

        $candidate = $date;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $candidate->modify('-1 year');

            if ($candidate >= $cutoff && $candidate <= $maximum) {
                return $this->block(
                    variable: $variable,
                    record: $record,
                    changes: [33 => $candidate->format('Y-m-d')],
                    reason:
                        'La fecha probable de parto excedía el corte más 280 días. '
                        . 'Se corrigió un desplazamiento evidente de un año, '
                        . 'conservando el mismo mes y día.'
                );
            }
        }

        return $this->manual(
            variable: 33,
            record: $record,
            reason:
                'La fecha probable de parto supera el límite y no se pudo '
                . 'deducir un año correcto de forma segura.'
        );
    }

    private function normalizeVale(
        int $variable,
        array $record
    ): RuleDecision {
        $ageMonths = $this->ageMonths($record);

        if ($ageMonths === null) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    'No fue posible calcular la edad para validar el tamizaje VALE.'
            );
        }

        $isUnderThirteen = $ageMonths < 156;

        return $this->block(
            variable: $variable,
            record: $record,
            changes: $isUnderThirteen
                ? [40 => 21, 63 => self::NO_DATA_DATE]
                : [40 => 0, 63 => self::NO_APLICA_DATE],
            reason: $isUnderThirteen
                ? 'La persona es menor de 13 años. VALE queda como no evaluado y sin fecha real.'
                : 'La persona tiene 13 años o más. VALE queda como No aplica.'
        );
    }

    private function normalizeContraceptiveSupply(
        int $variable,
        array $record
    ): RuleDecision {
        $ageMonths = $this->ageMonths($record);

        if ($ageMonths === null) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    'No fue posible calcular la edad para validar el suministro anticonceptivo.'
            );
        }

        if ($ageMonths < 120 || $ageMonths >= 720) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    'El Error150 se recibió para una edad fuera del rango '
                    . 'de 10 a 59 años; el registro debe revisarse.'
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                54 => 21,
                55 => self::NO_DATA_DATE,
            ],
            reason:
                'Entre 10 y 59 años el suministro anticonceptivo no puede '
                . 'registrarse como No aplica. Sin dato clínico, el bloque '
                . 'queda como 21 y 1800-01-01.'
        );
    }

    private function normalizeOralHealth(
        int $variable,
        array $record
    ): RuleDecision {
        $ageMonths = $this->ageMonths($record);

        if ($ageMonths === null) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    'No fue posible calcular la edad para validar salud bucal y COP.'
            );
        }

        /*
         * Lineamiento 202:
         * - Menor de 6 meses: no aplica (1845-01-01 / 0).
         * - Desde los 6 meses, si no hay fecha o medición disponible:
         *   fecha sin dato y COP no evaluado (1800-01-01 / 21).
         */
        $underSixMonths = $ageMonths < 6;

        return $this->block(
            variable: $variable,
            record: $record,
            changes: $underSixMonths
                ? [
                    76 => self::NO_APLICA_DATE,
                    102 => 0,
                ]
                : [
                    76 => self::NO_DATA_DATE,
                    102 => 21,
                ],
            reason: $underSixMonths
                ? 'La persona es menor de 6 meses. Salud bucal y COP quedan como No aplica.'
                : 'La persona tiene 6 meses o más y no hay una atención o medición disponible. '
                    . 'La fecha queda sin dato y el COP como riesgo no evaluado.'
        );
    }

    private function normalizeHepatitisB(
        int $variable,
        array $record
    ): RuleDecision {
        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                78 => self::NO_DATA_DATE,
                79 => 21,
            ],
            reason:
                'Existe una fecha real sin resultado clínico utilizable. '
                . 'El bloque de hepatitis B se normalizó como no evaluado, '
                . 'sin inventar un resultado reactivo o no reactivo.'
        );
    }

    private function normalizeHivResult(
        int $variable,
        array $record
    ): RuleDecision {
        $current = trim(
            (string) ($record['variables'][83] ?? '')
        );

        $normalized = $this->normalizeText($current);

        $target = match ($normalized) {
            'negativo', 'no reactivo', 'no reactivo negativo' => 5,
            'positivo', 'reactivo' => 4,
            'no aplica' => 0,
            'sin dato', 'no evaluado', 'riesgo no evaluado' => 21,
            default => null,
        };

        if ($target === null) {
            return $this->manual(
                variable: 83,
                record: $record,
                reason:
                    'El resultado textual de VIH no coincide con un valor '
                    . 'permitido que pueda convertirse automáticamente.'
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [83 => $target],
            reason:
                'El resultado textual de VIH se convirtió al código numérico '
                . 'permitido por la Resolución 202.'
        );
    }

    private function normalizeCervicalScreening(
        int $variable,
        array $record
    ): RuleDecision {
        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                86 => 21,
                87 => self::NO_DATA_DATE,
                88 => 21,
                89 => 999,
                90 => 999,
            ],
            reason:
                'El tamizaje cervical tenía método y resultado sin fecha real. '
                . 'Como no debe inventarse la fecha, el bloque completo se '
                . 'normalizó como no evaluado y sin dato.'
        );
    }

    private function normalizeMammography(
        int $variable,
        array $record
    ): RuleDecision {
        $ageMonths = $this->ageMonths($record);

        if (
            $this->sex($record) !== 'F'
            || $ageMonths === null
            || $ageMonths < 600
        ) {
            return $this->manual(
                variable: $variable,
                record: $record,
                reason:
                    'La regla automática de mamografía exige sexo F y '
                    . 'edad igual o superior a 50 años.'
            );
        }

        return $this->block(
            variable: $variable,
            record: $record,
            changes: [
                96 => self::NO_DATA_DATE,
                97 => 21,
            ],
            reason:
                'En una mujer de 50 años o más la mamografía no puede '
                . 'quedar como No aplica. Sin dato clínico, se registra '
                . 'fecha 1800-01-01 y resultado 21.'
        );
    }

    private function normalizeFortification(
        int $variable,
        array $record
    ): RuleDecision {
        return $this->block(
            variable: $variable,
            record: $record,
            changes: [70 => 21],
            reason:
                'Proteger indicó que No aplica no es válido para la edad '
                . 'reportada. Sin información clínica adicional, la '
                . 'fortificación se registra como 21, no evaluada.'
        );
    }

    private function block(
        int $variable,
        array $record,
        array $changes,
        string $reason
    ): RuleDecision {
        $currentValues = $record['variables'] ?? [];
        $different = false;

        foreach ($changes as $targetVariable => $newValue) {
            $current = $currentValues[$targetVariable] ?? null;

            if ((string) $current !== (string) $newValue) {
                $different = true;
                break;
            }
        }

        if (! $different) {
            return RuleDecision::valid(
                variable: $variable,
                currentValue: $currentValues[$variable] ?? null,
                reason: $reason . ' El bloque ya se encontraba normalizado.',
                rule: self::class
            );
        }

        return RuleDecision::automaticMany(
            changes: $changes,
            currentValues: $currentValues,
            reason: $reason,
            rule: self::class
        );
    }

    private function manual(
        int $variable,
        array $record,
        string $reason
    ): RuleDecision {
        return RuleDecision::manual(
            variable: $variable,
            currentValue: $record['variables'][$variable] ?? null,
            reason: $reason,
            rule: self::class
        );
    }

    private function origin(array $error): string
    {
        return Str::of(
            (string) ($error['origen'] ?? '')
        )
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
    }

    private function normalizeCode(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        return (string) ((int) $value);
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function ageMonths(array $record): ?float
    {
        $months = $record['age']['months'] ?? null;

        if (is_numeric($months)) {
            return (float) $months;
        }

        $years = $record['age']['years'] ?? null;

        return is_numeric($years)
            ? (float) $years * 12
            : null;
    }

    private function sex(array $record): string
    {
        return mb_strtoupper(
            trim(
                (string) (
                    $record['variables'][10]
                    ?? $record['sex']
                    ?? ''
                )
            ),
            'UTF-8'
        );
    }

    private function extractNumeric(mixed $value): ?float
    {
        $text = str_replace(
            ',',
            '.',
            trim((string) $value)
        );

        if (! preg_match('/-?\d+(?:\.\d+)?/', $text, $matches)) {
            return null;
        }

        return is_numeric($matches[0])
            ? (float) $matches[0]
            : null;
    }

    private function formatDecimal(
        float $value,
        int $maximumDecimals
    ): string {
        return rtrim(
            rtrim(
                number_format(
                    $value,
                    $maximumDecimals,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = DateTimeImmutable::createFromFormat(
                    '!' . $format,
                    $text
                );

                if (
                    $date instanceof DateTimeImmutable
                    && $date->format($format) === $text
                ) {
                    return $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
