<?php

namespace App\Data\Resolucion1552;

class Resolucion1552Record
{
    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    public function __construct(
        public readonly int $sourceRow,

        public readonly string $providerNit,
        public readonly string $providerCode,
        public readonly string $providerName,

        public readonly string $patientName,
        public readonly string $documentType,
        public readonly string $documentNumber,

        public readonly string $municipalityCode,
        public readonly string $cupsCode,
        public readonly string $specialty,

        public readonly string $requestDate,
        public readonly string $assignmentDate,
        public readonly string $appointmentDate,

        public readonly string $opportunityDays,
        public readonly string $specialistHours,

        public readonly string $regime,
        public readonly string $phone,

        public readonly array $warnings = [],
        public readonly array $errors = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function errorsCount(): int
    {
        return count($this->errors);
    }

    public function warningsCount(): int
    {
        return count($this->warnings);
    }

    public function toArray(): array
    {
        return [
            'source_row' => $this->sourceRow,

            'data' => [
                'provider_nit' => $this->providerNit,
                'provider_code' => $this->providerCode,
                'provider_name' => $this->providerName,

                'patient_name' => $this->patientName,
                'document_type' => $this->documentType,
                'document_number' => $this->documentNumber,

                'municipality_code' => $this->municipalityCode,
                'cups_code' => $this->cupsCode,
                'specialty' => $this->specialty,

                'request_date' => $this->requestDate,
                'assignment_date' => $this->assignmentDate,
                'appointment_date' => $this->appointmentDate,

                'opportunity_days' => $this->opportunityDays,
                'specialist_hours' => $this->specialistHours,

                'regime' => $this->regime,
                'phone' => $this->phone,
            ],

            'valid' => $this->isValid(),
            'warnings_count' => $this->warningsCount(),
            'errors_count' => $this->errorsCount(),

            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
/**
 * Devuelve los valores utilizados en el archivo oficial.
 *
 * @return array<string, string>
 */
public function officialValues(): array
{
    return [
        'provider_code' => $this->providerCode,
        'document_type' => $this->documentType,
        'document_number' => $this->documentNumber,
        'regime' => $this->regime,

        'phone' => $this->phone !== ''
            ? $this->phone
            : '9999999999',

        'specialty' => $this->specialty,
        'request_date' => $this->requestDate,
        'assignment_date' => $this->assignmentDate,
        'appointment_date' => $this->appointmentDate,
        'specialist_hours' => $this->specialistHours,
    ];
}
}