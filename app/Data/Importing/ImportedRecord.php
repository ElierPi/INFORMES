<?php

namespace App\Data\Importing;

class ImportedRecord
{
    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    public function __construct(
        public readonly int $sourceRow,

        public readonly ?string $providerNit,
        public readonly ?string $providerCode,
        public readonly ?string $providerName,

        public readonly ?string $patientName,
        public readonly ?string $documentType,
        public readonly ?string $documentNumber,

        public readonly ?string $municipalityCode,
        public readonly ?string $cupsCode,
        public readonly ?string $specialty,

        public readonly ?string $requestDate,
        public readonly ?string $assignmentDate,
        public readonly ?string $appointmentDate,

        public readonly ?string $opportunityDays,
        public readonly ?string $specialistHours,

        public readonly ?string $regime,
        public readonly ?string $phone,

        public readonly array $warnings = [],
        public readonly array $errors = [],
    ) {
    }

    public static function fromArray(
        array $record
    ): self {
        $data = $record['data'] ?? [];

        return new self(
            sourceRow: (int) (
                $record['source_row'] ?? 0
            ),

            providerNit:
                self::stringValue(
                    $data['provider_nit'] ?? null
                ),

            providerCode:
                self::stringValue(
                    $data['provider_code'] ?? null
                ),

            providerName:
                self::stringValue(
                    $data['provider_name'] ?? null
                ),

            patientName:
                self::stringValue(
                    $data['patient_name'] ?? null
                ),

            documentType:
                self::stringValue(
                    $data['document_type'] ?? null
                ),

            documentNumber:
                self::stringValue(
                    $data['document_number'] ?? null
                ),

            municipalityCode:
                self::stringValue(
                    $data['municipality_code'] ?? null
                ),

            cupsCode:
                self::stringValue(
                    $data['cups_code'] ?? null
                ),

            specialty:
                self::stringValue(
                    $data['specialty'] ?? null
                ),

            requestDate:
                self::stringValue(
                    $data['request_date'] ?? null
                ),

            assignmentDate:
                self::stringValue(
                    $data['assignment_date'] ?? null
                ),

            appointmentDate:
                self::stringValue(
                    $data['appointment_date'] ?? null
                ),

            opportunityDays:
                self::stringValue(
                    $data['opportunity_days'] ?? null
                ),

            specialistHours:
                self::stringValue(
                    $data['specialist_hours'] ?? null
                ),

            regime:
                self::stringValue(
                    $data['regime'] ?? null
                ),

            phone:
                self::stringValue(
                    $data['phone'] ?? null
                ),

            warnings:
                $record['warnings'] ?? [],

            errors:
                $record['errors'] ?? [],
        );
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function isValid(): bool
    {
        return ! $this->hasErrors();
    }

    public function toArray(): array
    {
        return [
            'source_row' => $this->sourceRow,

            'data' => [
                'provider_nit' =>
                    $this->providerNit,

                'provider_code' =>
                    $this->providerCode,

                'provider_name' =>
                    $this->providerName,

                'patient_name' =>
                    $this->patientName,

                'document_type' =>
                    $this->documentType,

                'document_number' =>
                    $this->documentNumber,

                'municipality_code' =>
                    $this->municipalityCode,

                'cups_code' =>
                    $this->cupsCode,

                'specialty' =>
                    $this->specialty,

                'request_date' =>
                    $this->requestDate,

                'assignment_date' =>
                    $this->assignmentDate,

                'appointment_date' =>
                    $this->appointmentDate,

                'opportunity_days' =>
                    $this->opportunityDays,

                'specialist_hours' =>
                    $this->specialistHours,

                'regime' => $this->regime,

                'phone' => $this->phone,
            ],

            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }

    private static function stringValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }
}