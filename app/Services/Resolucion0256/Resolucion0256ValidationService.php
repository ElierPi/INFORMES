<?php

namespace App\Services\Resolucion0256;

use App\Services\Reports\Validation\ConfigurableValidator;
use DateTimeImmutable;

class Resolucion0256ValidationService
{
    public function __construct(
        private readonly ConfigurableValidator $validator
    ) {
    }

    public function validate(array $data): array
    {
        $errors = [
            ...$this->validateType2Rules($data),
            ...$this->validateType4Rules($data),
            ...$this->validateType5Rules($data),
            ...$this->validateType6Rules($data),
            ...$this->validatePeriod($data),
        ];

        return $this->validator->validate(
            data: $data,
            configKey: 'resolucion0256',
            additionalErrors: $errors
        );
    }

    private function validateType2Rules(array $data): array
    {
        $errors = [];

        foreach (($data['tipo2']['records'] ?? []) as $record) {
            $v = $record['values'] ?? [];
            $row = $record['excel_row'] ?? null;

            $assigned = trim((string) ($v[13] ?? ''));
            $assignmentDate = trim((string) ($v[14] ?? ''));

            if ($assigned === '1' && $assignmentDate === '') {
                $errors[] = $this->error(
                    'Tipo 2', $row, 15,
                    'Fecha de asignación de la cita',
                    $assignmentDate,
                    'La fecha es obligatoria cuando la cita fue asignada.'
                );
            }

            if ($assigned === '2' && $assignmentDate !== '') {
                $errors[] = $this->error(
                    'Tipo 2', $row, 15,
                    'Fecha de asignación de la cita',
                    $assignmentDate,
                    'La fecha debe quedar vacía cuando la cita no fue asignada.'
                );
            }

            if (
                $assigned === '1'
                && $this->dateIsBefore($assignmentDate, $v[12] ?? null)
            ) {
                $errors[] = $this->error(
                    'Tipo 2', $row, 15,
                    'Fecha de asignación de la cita',
                    $assignmentDate,
                    'La fecha de asignación no puede ser anterior a la solicitud.'
                );
            }
        }

        return $errors;
    }

    private function validateType4Rules(array $data): array
    {
        $errors = [];

        foreach (($data['tipo4']['records'] ?? []) as $record) {
            $v = $record['values'] ?? [];
            $row = $record['excel_row'] ?? null;

            $performed = trim((string) ($v[15] ?? ''));
            $cause = trim((string) ($v[16] ?? ''));
            $rescheduled = trim((string) ($v[17] ?? ''));

            if ($performed === '1' && $cause !== '') {
                $errors[] = $this->error(
                    'Tipo 4', $row, 17,
                    'Causa de no realización',
                    $cause,
                    'La causa debe quedar vacía cuando el procedimiento sí se realizó.'
                );
            }

            if ($performed === '2' && $cause === '') {
                $errors[] = $this->error(
                    'Tipo 4', $row, 17,
                    'Causa de no realización',
                    $cause,
                    'Debe indicar la causa cuando el procedimiento no se realizó.'
                );
            }

            if ($performed === '1' && $rescheduled !== '2') {
                $errors[] = $this->error(
                    'Tipo 4', $row, 18,
                    'Se reprogramó el procedimiento',
                    $rescheduled,
                    'Cuando el procedimiento se realizó, debe reportarse NO (2).'
                );
            }

            if ($this->dateIsBefore($v[14] ?? null, $v[13] ?? null)) {
                $errors[] = $this->error(
                    'Tipo 4', $row, 15,
                    'Fecha de programación',
                    $v[14] ?? null,
                    'La fecha de programación no puede ser anterior a la solicitud.'
                );
            }
        }

        return $errors;
    }

    private function validateType5Rules(array $data): array
    {
        $errors = [];

        foreach (($data['tipo5']['records'] ?? []) as $record) {
            $v = $record['values'] ?? [];
            $row = $record['excel_row'] ?? null;

            $serviceFalls = array_sum(array_map(
                'intval',
                array_slice($v, 4, 4)
            ));

            $classificationFalls =
                (int) ($v[8] ?? 0)
                + (int) ($v[9] ?? 0);

            if ($serviceFalls !== $classificationFalls) {
                $errors[] = $this->error(
                    'Tipo 5', $row, null,
                    'Coherencia de caídas',
                    "{$serviceFalls} / {$classificationFalls}",
                    'La suma de caídas por servicio debe coincidir con eventos adversos más incidentes.'
                );
            }
        }

        return $errors;
    }

    private function validateType6Rules(array $data): array
    {
        $errors = [];

        foreach (($data['tipo6']['records'] ?? []) as $record) {
            $v = $record['values'] ?? [];
            $row = $record['excel_row'] ?? null;

            $classification =
                trim((string) ($v[11] ?? '')).' '.
                trim((string) ($v[12] ?? ''));

            $attention =
                trim((string) ($v[13] ?? '')).' '.
                trim((string) ($v[14] ?? ''));

            $classificationDate = $this->dateTime($classification);
            $attentionDate = $this->dateTime($attention);

            if (
                $attentionDate
                && $classificationDate
                && $attentionDate < $classificationDate
            ) {
                $errors[] = $this->error(
                    'Tipo 6', $row, null,
                    'Fecha y hora de atención',
                    $attention,
                    'La atención no puede ser anterior a la clasificación TRIAGE II.'
                );
            }
        }

        return $errors;
    }

    private function validatePeriod(array $data): array
    {
        $record = $data['tipo1']['records'][0] ?? null;

        if (! is_array($record)) {
            return [];
        }

        $v = $record['values'] ?? [];
        $start = $v[4] ?? null;
        $end = $v[5] ?? null;

        if (! $this->dateIsBefore($end, $start)) {
            return [];
        }

        return [
            $this->error(
                'Tipo 1',
                $record['excel_row'] ?? null,
                6,
                'Fecha final del período',
                $end,
                'La fecha final no puede ser anterior a la fecha inicial.'
            ),
        ];
    }

    private function dateIsBefore(mixed $candidate, mixed $reference): bool
    {
        $candidateDate = $this->date((string) $candidate);
        $referenceDate = $this->date((string) $reference);

        return $candidateDate && $referenceDate
            ? $candidateDate < $referenceDate
            : false;
    }

    private function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function dateTime(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            trim($value)
        );

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private function error(
        string $sheet,
        ?int $row,
        ?int $column,
        ?string $field,
        mixed $value,
        string $message
    ): array {
        return compact(
            'sheet',
            'row',
            'column',
            'field',
            'value',
            'message'
        );
    }
}
