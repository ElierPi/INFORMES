<?php

namespace App\Services\Informe202;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class AgeCalculator
{
    public function calculate(
        mixed $birthDate,
        mixed $cutoffDate
    ): array {
        $birth = $this->parseDate(
            $birthDate,
            'fecha de nacimiento'
        );

        $cutoff = $this->parseDate(
            $cutoffDate,
            'fecha de corte'
        );

        if ($birth->greaterThan($cutoff)) {
            throw new RuntimeException(
                'La fecha de nacimiento es posterior a la fecha de corte.'
            );
        }

        return [
            'birth_date' => $birth->format('Y-m-d'),
            'cutoff_date' => $cutoff->format('Y-m-d'),

            /*
             * Edad cumplida.
             */
            'years' => $birth->diffInYears($cutoff),

            /*
             * Total de meses transcurridos.
             * Es importante para reglas como 6–23 meses.
             */
            'months' => $birth->diffInMonths($cutoff),

            /*
             * Total de días transcurridos.
             * Es importante para reglas neonatales.
             */
            'days' => $birth->diffInDays($cutoff),
        ];
    }

    public function normalizeDate(mixed $value): string
    {
        return $this->parseDate(
            $value,
            'fecha'
        )->format('Y-m-d');
    }

    private function parseDate(
        mixed $value,
        string $fieldName
    ): CarbonImmutable {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)
                ->startOfDay();
        }

        /*
         * Las fechas de Excel pueden venir como números seriales.
         */
        if (
            is_numeric($value)
            && (float) $value > 0
        ) {
            try {
                return CarbonImmutable::instance(
                    ExcelDate::excelToDateTimeObject(
                        (float) $value
                    )
                )->startOfDay();
            } catch (\Throwable) {
                throw new RuntimeException(
                    "La {$fieldName} no es válida."
                );
            }
        }

        $text = trim((string) $value);

        if ($text === '') {
            throw new RuntimeException(
                "La {$fieldName} está vacía."
            );
        }

        foreach ([
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'Y/m/d',
        ] as $format) {
            $date = CarbonImmutable::createFromFormat(
                $format,
                $text
            );

            if (
                $date !== false
                && $date->format($format) === $text
            ) {
                return $date->startOfDay();
            }
        }

        throw new RuntimeException(
            "La {$fieldName} '{$text}' no tiene un formato válido."
        );
    }
}