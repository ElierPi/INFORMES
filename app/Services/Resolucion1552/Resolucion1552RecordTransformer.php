<?php

namespace App\Services\Resolucion1552;

use App\Data\Importing\ImportedRecord;
use App\Data\Resolucion1552\Resolucion1552Record;
use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

class Resolucion1552RecordTransformer
{
    public function transform(
        ImportedRecord $record
    ): Resolucion1552Record {
        $warnings = [];
        $errors = [];

        /*
        |--------------------------------------------------------------------------
        | Identificación del prestador
        |--------------------------------------------------------------------------
        */

        $providerNit = $this->normalizeNumericCode(
            $record->providerNit
        );

        $providerCode = $this->normalizeNumericCode(
            $record->providerCode
        );

        $providerName = $this->normalizeText(
            $record->providerName
        );

        /*
        |--------------------------------------------------------------------------
        | Usuario
        |--------------------------------------------------------------------------
        */

        $patientName = $this->normalizeText(
            $record->patientName
        );

        $documentType = $this->normalizeDocumentType(
            value: $record->documentType,
            sourceRow: $record->sourceRow,
            warnings: $warnings,
            errors: $errors
        );

        $documentNumber = $this->normalizeDocumentNumber(
            $record->documentNumber
        );

        /*
        |--------------------------------------------------------------------------
        | Ubicación y servicio
        |--------------------------------------------------------------------------
        */

        $municipalityCode = $this->normalizeNumericCode(
            $record->municipalityCode
        );

        $cupsCode = $this->normalizeCode(
            $record->cupsCode
        );

        $specialty = $this->normalizeText(
            $record->specialty
        );

        /*
        |--------------------------------------------------------------------------
        | Fechas
        |--------------------------------------------------------------------------
        */

        $requestDate = $this->normalizeDate(
            value: $record->requestDate,
            field: 'request_date',
            sourceRow: $record->sourceRow,
            warnings: $warnings,
            errors: $errors
        );

        $assignmentDate = $this->normalizeDate(
            value: $record->assignmentDate,
            field: 'assignment_date',
            sourceRow: $record->sourceRow,
            warnings: $warnings,
            errors: $errors
        );

        $appointmentDate = $this->normalizeDate(
            value: $record->appointmentDate,
            field: 'appointment_date',
            sourceRow: $record->sourceRow,
            warnings: $warnings,
            errors: $errors
        );

        /*
        |--------------------------------------------------------------------------
        | Indicadores
        |--------------------------------------------------------------------------
        */

        $opportunityDays = $this->normalizeDecimal(
            value: $record->opportunityDays,
            field: 'opportunity_days',
            sourceRow: $record->sourceRow,
            errors: $errors
        );

        $specialistHours = $this->normalizeDecimal(
            value: $record->specialistHours,
            field: 'specialist_hours',
            sourceRow: $record->sourceRow,
            errors: $errors
        );

        /*
        |--------------------------------------------------------------------------
        | Régimen y teléfono
        |--------------------------------------------------------------------------
        */

        $regime = $this->normalizeRegime(
            value: $record->regime,
            sourceRow: $record->sourceRow,
            warnings: $warnings,
            errors: $errors
        );

        $phone = $this->normalizePhone(
            $record->phone
        );

        /*
        |--------------------------------------------------------------------------
        | Validación básica de campos obligatorios
        |--------------------------------------------------------------------------
        */

        $transformedData = [
            'provider_nit' => $providerNit,
            'provider_code' => $providerCode,
            'provider_name' => $providerName,
            'patient_name' => $patientName,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'municipality_code' => $municipalityCode,
            'cups_code' => $cupsCode,
            'specialty' => $specialty,
            'request_date' => $requestDate,
            'assignment_date' => $assignmentDate,
            'appointment_date' => $appointmentDate,
            'opportunity_days' => $opportunityDays,
            'specialist_hours' => $specialistHours,
            'regime' => $regime,
            'phone' => $phone,
        ];

        $this->validateRequiredFields(
            data: $transformedData,
            sourceRow: $record->sourceRow,
            errors: $errors
        );

        /*
        |--------------------------------------------------------------------------
        | Validaciones lógicas iniciales
        |--------------------------------------------------------------------------
        */

        $this->validateDateSequence(
            requestDate: $requestDate,
            assignmentDate: $assignmentDate,
            appointmentDate: $appointmentDate,
            sourceRow: $record->sourceRow,
            warnings: $warnings,
            errors: $errors
        );

        $this->validateCodeLengths(
            providerNit: $providerNit,
            providerCode: $providerCode,
            municipalityCode: $municipalityCode,
            cupsCode: $cupsCode,
            sourceRow: $record->sourceRow,
            warnings: $warnings
        );

        return new Resolucion1552Record(
            sourceRow: $record->sourceRow,

            providerNit: $providerNit,
            providerCode: $providerCode,
            providerName: $providerName,

            patientName: $patientName,
            documentType: $documentType,
            documentNumber: $documentNumber,

            municipalityCode: $municipalityCode,
            cupsCode: $cupsCode,
            specialty: $specialty,

            requestDate: $requestDate,
            assignmentDate: $assignmentDate,
            appointmentDate: $appointmentDate,

            opportunityDays: $opportunityDays,
            specialistHours: $specialistHours,

            regime: $regime,
            phone: $phone,

            warnings: [
                ...$record->warnings,
                ...$warnings,
            ],

            errors: [
                ...$record->errors,
                ...$errors,
            ],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    private function normalizeDocumentType(
        ?string $value,
        int $sourceRow,
        array &$warnings,
        array &$errors
    ): string {
        $normalized = $this->normalizeCatalogKey($value);

        if ($normalized === '') {
            return '';
        }

        $catalog = config(
            'resolucion1552_catalogs.document_types',
            []
        );

        if (isset($catalog[$normalized])) {
            return (string) $catalog[$normalized];
        }

        $errors[] = $this->issue(
            type: 'invalid_catalog_value',
            field: 'document_type',
            message: sprintf(
                'El tipo de documento "%s" no está reconocido.',
                (string) $value
            ),
            sourceRow: $sourceRow,
            value: $value
        );

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    private function normalizeRegime(
        ?string $value,
        int $sourceRow,
        array &$warnings,
        array &$errors
    ): string {
        $normalized = $this->normalizeCatalogKey($value);

        if ($normalized === '') {
            return '';
        }

        $catalog = config(
            'resolucion1552_catalogs.regimes',
            []
        );

        if (isset($catalog[$normalized])) {
            return (string) $catalog[$normalized];
        }

        $errors[] = $this->issue(
            type: 'invalid_catalog_value',
            field: 'regime',
            message: sprintf(
                'El régimen "%s" no está reconocido.',
                (string) $value
            ),
            sourceRow: $sourceRow,
            value: $value
        );

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    private function normalizeDate(
        mixed $value,
        string $field,
        int $sourceRow,
        array &$warnings,
        array &$errors
    ): string {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format(
                $this->dateOutputFormat()
            );
        }

        $original = trim((string) $value);

        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'Y/m/d',
            'd/m/y',
            'd-m-y',
            'm/d/Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat(
                    $format,
                    $original
                );

                if (
                    $date !== false
                    && $date->format($format) === $original
                ) {
                    return $date->format(
                        $this->dateOutputFormat()
                    );
                }
            } catch (Throwable) {
                // Se prueba el siguiente formato.
            }
        }

        try {
            $date = Carbon::parse($original);

            $normalized = $date->format(
                $this->dateOutputFormat()
            );

            $warnings[] = $this->issue(
                type: 'date_format_converted',
                field: $field,
                message: sprintf(
                    'La fecha "%s" fue convertida automáticamente a "%s".',
                    $original,
                    $normalized
                ),
                sourceRow: $sourceRow,
                value: $original
            );

            return $normalized;
        } catch (Throwable) {
            $errors[] = $this->issue(
                type: 'invalid_date',
                field: $field,
                message: sprintf(
                    'La fecha "%s" no tiene un formato válido.',
                    $original
                ),
                sourceRow: $sourceRow,
                value: $original
            );

            return $original;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function normalizeDecimal(
        ?string $value,
        string $field,
        int $sourceRow,
        array &$errors
    ): string {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $normalized = trim($value);
        $normalized = str_replace(' ', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            $errors[] = $this->issue(
                type: 'invalid_number',
                field: $field,
                message: sprintf(
                    'El valor "%s" no es numérico.',
                    $value
                ),
                sourceRow: $sourceRow,
                value: $value
            );

            return $value;
        }

        $number = (float) $normalized;

        if ($number < 0) {
            $errors[] = $this->issue(
                type: 'negative_number',
                field: $field,
                message: sprintf(
                    'El campo %s no puede contener un valor negativo.',
                    $field
                ),
                sourceRow: $sourceRow,
                value: $value
            );
        }

        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return rtrim(
            rtrim(
                number_format(
                    $number,
                    4,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }

    /**
     * @param array<string, string> $data
     * @param array<int, array<string, mixed>> $errors
     */
    private function validateRequiredFields(
        array $data,
        int $sourceRow,
        array &$errors
    ): void {
        $requiredFields = config(
            'resolucion1552_catalogs.required_fields',
            []
        );

        if (
            config(
                'resolucion1552_catalogs.phone_required',
                false
            )
        ) {
            $requiredFields[] = 'phone';
        }

        foreach ($requiredFields as $field) {
            $value = $data[$field] ?? '';

            if ($value !== '') {
                continue;
            }

            $errors[] = $this->issue(
                type: 'required',
                field: $field,
                message: sprintf(
                    'El campo %s es obligatorio.',
                    $field
                ),
                sourceRow: $sourceRow,
                value: null
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     * @param array<int, array<string, mixed>> $errors
     */
    private function validateDateSequence(
        string $requestDate,
        string $assignmentDate,
        string $appointmentDate,
        int $sourceRow,
        array &$warnings,
        array &$errors
    ): void {
        $request = $this->parseNormalizedDate(
            $requestDate
        );

        $assignment = $this->parseNormalizedDate(
            $assignmentDate
        );

        $appointment = $this->parseNormalizedDate(
            $appointmentDate
        );

        if (
            $request !== null
            && $assignment !== null
            && $assignment->lt($request)
        ) {
            $errors[] = $this->issue(
                type: 'invalid_date_sequence',
                field: 'assignment_date',
                message:
                    'La fecha de asignación no puede ser anterior a la fecha de solicitud.',
                sourceRow: $sourceRow,
                value: $assignmentDate
            );
        }

        if (
            $assignment !== null
            && $appointment !== null
            && $appointment->lt($assignment)
        ) {
            $errors[] = $this->issue(
                type: 'invalid_date_sequence',
                field: 'appointment_date',
                message:
                    'La fecha de la cita no puede ser anterior a la fecha de asignación.',
                sourceRow: $sourceRow,
                value: $appointmentDate
            );
        }

        if (
            $request !== null
            && $appointment !== null
            && $appointment->diffInDays($request) > 365
        ) {
            $warnings[] = $this->issue(
                type: 'unusual_date_difference',
                field: 'appointment_date',
                message:
                    'La diferencia entre la solicitud y la cita supera los 365 días.',
                sourceRow: $sourceRow,
                value: $appointmentDate
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $warnings
     */
    private function validateCodeLengths(
        string $providerNit,
        string $providerCode,
        string $municipalityCode,
        string $cupsCode,
        int $sourceRow,
        array &$warnings
    ): void {
        if (
            $providerNit !== ''
            && strlen($providerNit) < 8
        ) {
            $warnings[] = $this->issue(
                type: 'unusual_length',
                field: 'provider_nit',
                message:
                    'El NIT del prestador tiene una longitud menor a la esperada.',
                sourceRow: $sourceRow,
                value: $providerNit
            );
        }

        if (
            $providerCode !== ''
            && strlen($providerCode) < 10
        ) {
            $warnings[] = $this->issue(
                type: 'unusual_length',
                field: 'provider_code',
                message:
                    'El código del prestador tiene una longitud menor a la esperada.',
                sourceRow: $sourceRow,
                value: $providerCode
            );
        }

        if (
            $municipalityCode !== ''
            && strlen($municipalityCode) !== 5
        ) {
            $warnings[] = $this->issue(
                type: 'unusual_length',
                field: 'municipality_code',
                message:
                    'El código del municipio normalmente debe tener 5 caracteres.',
                sourceRow: $sourceRow,
                value: $municipalityCode
            );
        }

        if (
            $cupsCode !== ''
            && strlen($cupsCode) < 6
        ) {
            $warnings[] = $this->issue(
                type: 'unusual_length',
                field: 'cups_code',
                message:
                    'El código CUPS tiene una longitud menor a la esperada.',
                sourceRow: $sourceRow,
                value: $cupsCode
            );
        }
    }

    private function normalizeText(
        ?string $value
    ): string {
        if ($value === null) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return mb_strtoupper(
            $value,
            'UTF-8'
        );
    }

    private function normalizeCode(
        ?string $value
    ): string {
        if ($value === null) {
            return '';
        }

        return mb_strtoupper(
            preg_replace(
                '/\s+/u',
                '',
                trim($value)
            ) ?? trim($value),
            'UTF-8'
        );
    }

    private function normalizeNumericCode(
        ?string $value
    ): string {
        if ($value === null) {
            return '';
        }

        return preg_replace(
            '/[^0-9]/',
            '',
            trim($value)
        ) ?? '';
    }

    private function normalizeDocumentNumber(
        ?string $value
    ): string {
        if ($value === null) {
            return '';
        }

        $value = trim($value);

        return preg_replace(
            '/[\s.\-]/',
            '',
            $value
        ) ?? $value;
    }

    private function normalizePhone(
        ?string $value
    ): string {
        if ($value === null) {
            return '';
        }

        return preg_replace(
            '/[^0-9]/',
            '',
            trim($value)
        ) ?? '';
    }

    private function normalizeCatalogKey(
        ?string $value
    ): string {
        if ($value === null) {
            return '';
        }

        $value = mb_strtoupper(
            trim($value),
            'UTF-8'
        );

        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;
    }

    private function dateOutputFormat(): string
    {
        return (string) config(
            'resolucion1552_catalogs.date_output_format',
            'd/m/Y'
        );
    }

    private function parseNormalizedDate(
        string $value
    ): ?Carbon {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat(
                $this->dateOutputFormat(),
                $value
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(
        string $type,
        string $field,
        string $message,
        int $sourceRow,
        mixed $value
    ): array {
        return [
            'type' => $type,
            'field' => $field,
            'message' => $message,
            'source_row' => $sourceRow,
            'value' => $value,
        ];
    }
}