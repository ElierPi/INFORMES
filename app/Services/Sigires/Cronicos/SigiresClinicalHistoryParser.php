<?php

namespace App\Services\Sigires\Cronicos;

use DateTimeImmutable;

class SigiresClinicalHistoryParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(array $history, string $cutoffDate): array
    {
        $text = (string) ($history['text'] ?? '');
        $normalized = $this->normalizeText($text);
        $cutoff = $this->parseDate($cutoffDate);

        $documentType = $this->firstMatch(
            '/IDENTIFICACION:\s*(RC|TI|CC|CE|PA|MS|AS|CD|SC|PE|PT|SI|DE|CN)\b/i',
            $normalized
        ) ?? ($history['document_type'] ?? null);

        $documentNumber = $this->firstMatch(
            '/IDENTIFICACION:\s*(?:RC|TI|CC|CE|PA|MS|AS|CD|SC|PE|PT|SI|DE|CN)\s*([0-9A-Z]+)/i',
            $normalized
        ) ?? (string) ($history['document_number'] ?? '');

        $attentionDates = $this->allDates(
            '/FECHA DE ATENCION:\s*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{4})/i',
            $normalized,
            $cutoff
        );

        $attentionDate = $attentionDates === []
            ? null
            : max($attentionDates);

        $birthDate = $this->normalizeDate(
            $this->firstMatch(
                '/FECHA DE NACIMIENTO:\s*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{4})/i',
                $normalized
            )
        );

        $sexText = $this->firstMatch(
            '/SEXO:\s*(MASCULINO|FEMENINO|M|F)\b/i',
            $normalized
        );

        $sex = match (true) {
            is_string($sexText) && str_starts_with($sexText, 'M') => 'M',
            is_string($sexText) && str_starts_with($sexText, 'F') => 'F',
            default => null,
        };

        $diagnoses = $this->extractDiagnoses($text);
        $diagnosisCodes = array_column($diagnoses, 'code');

        $hta = $this->containsDiagnosis(
            $diagnosisCodes,
            '/^(I1[0-5]|I674|O10|O10[0-9]|P292)/'
        ) || str_contains($normalized, 'ANTECEDENTE DE HTA');

        $dmCodes = array_values(array_filter(
            $diagnosisCodes,
            fn (string $code): bool => preg_match(
                '/^(E1[0-4]|O24[0-3]|P702)/',
                $code
            ) === 1
        ));

        $dm = $dmCodes !== []
            || str_contains($normalized, 'DM TIPO')
            || str_contains($normalized, 'DIABETES MELLITUS');

        $dmType = match (true) {
            $this->containsDiagnosis($dmCodes, '/^E10/') => 1,
            $this->containsDiagnosis($dmCodes, '/^E11/') => 3,
            $dm => 4,
            default => 2,
        };

        $ercCodes = array_values(array_filter(
            $diagnosisCodes,
            fn (string $code): bool => str_starts_with($code, 'N18')
        ));

        $erc = $ercCodes !== []
            || str_contains($normalized, 'ENFERMEDAD RENAL CRONICA');

        $ercStage = $this->resolveRenalStage($ercCodes, $normalized);

        $labs = [
            'creatinine' => $this->extractLatestLab(
                $normalized,
                ['CREATININA'],
                [0.05, 30],
                $cutoff,
                $attentionDate
            ),
            'hba1c' => $this->extractLatestLab(
                $normalized,
                ['HEMOGLOBINA GLICOSILADA', 'HBA1C', 'HB1AC'],
                [1, 30],
                $cutoff,
                $attentionDate
            ),
            'albuminuria' => $this->extractLatestLab(
                $normalized,
                ['MICROALBUMINURIA', 'MICROALBUMINA', 'ALBUMINURIA'],
                [0, 100000],
                $cutoff,
                $attentionDate
            ),
            'rac' => $this->extractLatestLab(
                $normalized,
                [
                    'RELACION ALBUMINURIA/CREATINURIA',
                    'RELACION ALBUMINURIA CREATINURIA',
                    'RAC',
                ],
                [0, 100000],
                $cutoff,
                $attentionDate
            ),
            'cholesterol_total' => $this->extractLatestLab(
                $normalized,
                ['COLESTEROL TOTAL', 'COLESTEROLTOTAL'],
                [10, 1000],
                $cutoff,
                $attentionDate
            ),
            'hdl' => $this->extractLatestLab(
                $normalized,
                ['COLESTEROL HDL', 'HDL'],
                [1, 500],
                $cutoff,
                $attentionDate
            ),
            'ldl' => $this->extractLatestLab(
                $normalized,
                ['COLESTEROL LDL', 'LDL'],
                [1, 1000],
                $cutoff,
                $attentionDate
            ),
            'triglycerides' => $this->extractLatestLab(
                $normalized,
                ['TRIGLICERIDOS', 'TRIGLICERIDO', 'TRIGLICERIOS'],
                [1, 5000],
                $cutoff,
                $attentionDate
            ),
            'hemoglobin' => $this->extractLatestLab(
                $normalized,
                ['HB', 'HEMOGLOBINA'],
                [1, 30],
                $cutoff,
                $attentionDate,
                ['GLICOSILADA']
            ),
            'albumin' => $this->extractLatestLab(
                $normalized,
                ['ALBUMINA SERICA'],
                [0.1, 10],
                $cutoff,
                $attentionDate
            ),
            'phosphorus' => $this->extractLatestLab(
                $normalized,
                ['FOSFORO'],
                [0.1, 30],
                $cutoff,
                $attentionDate
            ),
            'tfg' => $this->extractLatestLab(
                $normalized,
                ['TFG', 'TASA DE FILTRACION'],
                [0, 999],
                $cutoff,
                $attentionDate
            ),
        ];

        $medications = $this->extractMedications($normalized);

        return [
            'file_name' => (string) ($history['file_name'] ?? ''),
            'document_type_file' => $history['document_type'] ?? null,
            'document_type_history' => $documentType,
            'document_number' => $this->normalizeDocumentNumber($documentNumber),
            'attention_date' => $attentionDate,
            'birth_date' => $birthDate,
            'sex' => $sex,
            'city' => $this->cleanCapture($this->firstMatch(
                '/CIUDAD:\s*([A-Z ]{2,40})/i',
                $normalized
            )),
            'address' => $this->cleanCapture($this->firstMatch(
                '/DIRECCION:\s*(.+?)(?:\s{2,}ZONA:|\s+ZONA:)/i',
                $normalized
            )),
            'phone' => $this->cleanPhone($this->firstMatch(
                '/^TELEFONOS:\s*([0-9,\s-]+)/im',
                $normalized
            )),
            'population_group' => $this->cleanCapture($this->firstMatch(
                '/GRUPO POBLACIONAL:\s*([^\r\n]+)/i',
                $text
            )),
            'specialty' => $this->cleanCapture($this->firstMatch(
                '/ESPECIALIDAD:\s*([^\r\n]+)/i',
                $text
            )),
            'diagnoses' => $diagnoses,
            'diagnosis_codes' => $diagnosisCodes,
            'hta' => $hta,
            'dm' => $dm,
            'dm_type' => $dmType,
            'erc' => $erc,
            'erc_stage' => $ercStage,
            'pas' => $this->extractNumber(
                '/\bPAS:\s*(\d{2,3}(?:[.,]\d+)?)/i',
                $normalized
            ),
            'pad' => $this->extractNumber(
                '/P\.?\s*A\.?\s*D\.?:\s*(\d{2,3}(?:[.,]\d+)?)/i',
                $normalized
            ),
            'weight' => $this->extractNumber(
                '/\bPESO:\s*(\d+(?:[.,]\d+)?)\s*KG/i',
                $normalized
            ),
            'height' => $this->extractNumber(
                '/\bTALLA:\s*(\d+(?:[.,]\d+)?)\s*CM/i',
                $normalized
            ),
            'waist' => $this->extractNumber(
                '/CIRCUNFERENCIA(?: DE)? CINTURA[:\s]+(\d+(?:[.,]\d+)?)/i',
                $normalized
            ),
            'labs' => $labs,
            'medications' => $medications,
            'receives_ara2' => $this->containsAny(
                $medications,
                [
                    'LOSARTAN',
                    'VALSARTAN',
                    'IRBESARTAN',
                    'CANDESARTAN',
                    'TELMISARTAN',
                    'OLMESARTAN',
                ]
            ),
            'receives_ieca' => $this->containsAny(
                $medications,
                [
                    'ENALAPRIL',
                    'CAPTOPRIL',
                    'LISINOPRIL',
                    'RAMIPRIL',
                    'PERINDOPRIL',
                ]
            ),
            'urinalysis_result' => $this->resolveUrinalysisResult($normalized),
            'renal_program_mentioned' =>
                str_contains($normalized, 'NEFROPROTECCION')
                || str_contains($normalized, 'PROGRAMA RENAL')
                || str_contains($normalized, 'PROGRAMA DE CRONICO'),
            'renal_program_entry_pending' =>
                str_contains($normalized, 'PARA INGRESAR A PROGRAMA')
                || str_contains($normalized, 'INGRESAR A PROGRAMA DE CRONICO'),
            'specialties' => $this->extractSpecialties(
                $normalized,
                $attentionDate,
                $cutoff
            ),
        ];
    }

    /**
     * @return array<int, array{code: string, description: string}>
     */
    private function extractDiagnoses(string $text): array
    {
        $diagnoses = [];
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $line) {
            if (preg_match_all(
                '/\(([A-Z]\d{2,3}[A-Z]?)\)\s*([^(\r\n]+)/i',
                $line,
                $matches,
                PREG_SET_ORDER
            ) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $code = mb_strtoupper(trim($match[1]));
                $description = trim($match[2], " \t\n\r\0\x0B.");

                if ($code === '' || $description === '') {
                    continue;
                }

                $diagnoses[$code] = [
                    'code' => $code,
                    'description' => $description,
                ];
            }
        }

        return array_values($diagnoses);
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array{0: float|int, 1: float|int}  $range
     * @param  array<int, string>  $excludedTerms
     * @return array{value: ?float, date: ?string, evidence: ?string}
     */
    private function extractLatestLab(
        string $text,
        array $labels,
        array $range,
        ?DateTimeImmutable $cutoff,
        ?string $fallbackDate,
        array $excludedTerms = []
    ): array {
        $datedCandidates = [];
        $undatedCandidates = [];

        foreach ($labels as $label) {
            $offset = 0;

            while (($position = mb_stripos($text, $label, $offset)) !== false) {
                $before = mb_substr($text, max(0, $position - 40), 40);
                $segment = $this->labSection(
                    $text,
                    $position,
                    $label
                );
                $offset = $position + mb_strlen($label);

                $skip = false;

                foreach ($excludedTerms as $term) {
                    if (str_contains(
                        mb_substr($text, max(0, $position - 25), 80),
                        $term
                    )) {
                        $skip = true;

                        break;
                    }
                }

                if ($skip) {
                    continue;
                }

                if (preg_match_all(
                    '/(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})\s*:?\s*'.
                    '(?:MENOR\s+A\s+|<\s*)?(\d+(?:[.,]\d+)?)/',
                    $segment,
                    $matches,
                    PREG_SET_ORDER
                ) !== false) {
                    foreach ($matches as $match) {
                        $date = $this->parseDate($match[1]);
                        $value = $this->toFloat($match[2]);

                        if (
                            ! $date instanceof DateTimeImmutable
                            || ($cutoff instanceof DateTimeImmutable && $date > $cutoff)
                            || ! $this->isInRange($value, $range)
                        ) {
                            continue;
                        }

                        $datedCandidates[] = [
                            'date' => $date,
                            'value' => $value,
                            'evidence' => $this->evidence(
                                $before.$segment,
                                $label
                            ),
                        ];
                    }
                }

                if (preg_match(
                    '/^'.preg_quote($label, '/').'\s*:?\s*'.
                    '(?:MENOR\s+A\s+|<\s*)?(\d+(?:[.,]\d+)?)/',
                    $segment,
                    $match
                ) === 1) {
                    $value = $this->toFloat($match[1]);

                    if ($this->isInRange($value, $range)) {
                        $undatedCandidates[] = [
                            'value' => $value,
                            'evidence' => $this->evidence(
                                $before.$segment,
                                $label
                            ),
                        ];
                    }
                }
            }
        }

        if ($datedCandidates !== []) {
            usort(
                $datedCandidates,
                fn (array $left, array $right): int =>
                    $left['date'] <=> $right['date']
            );

            $candidate = end($datedCandidates);

            return [
                'value' => $candidate['value'],
                'date' => $candidate['date']->format('Y-m-d'),
                'evidence' => $candidate['evidence'],
            ];
        }

        if ($undatedCandidates !== []) {
            $candidate = end($undatedCandidates);

            return [
                'value' => $candidate['value'],
                'date' => $fallbackDate,
                'evidence' => $candidate['evidence'],
            ];
        }

        return [
            'value' => null,
            'date' => null,
            'evidence' => null,
        ];
    }


    private function labSection(
        string $text,
        int $position,
        string $label
    ): string {
        $segment = mb_substr($text, $position, 500);
        $labelLength = mb_strlen($label);
        $afterLabel = mb_substr($segment, $labelLength);

        // Los formatos de evolución suelen organizar los laboratorios como
        // "08. CREATININA: ... 10. PARCIAL DE ORINA: ...". El extractor
        // anterior recorría 500 caracteres completos y terminaba tomando
        // valores de la siguiente variable (por ejemplo HbA1c como
        // creatinina o TFG). Limitamos la búsqueda al bloque de la variable.
        if (preg_match(
            '/(?:\r?\n|\s)\d{1,2}\.\s*[A-Z][A-Z0-9 \/()_-]{2,60}:?/',
            $afterLabel,
            $match,
            PREG_OFFSET_CAPTURE
        ) === 1) {
            $byteOffset = $match[0][1];
            $prefix = substr($afterLabel, 0, $byteOffset);

            return mb_substr($segment, 0, $labelLength).$prefix;
        }

        // Algunos PDF conservan el salto de línea pero no la numeración.
        // En esos casos se detiene ante una nueva etiqueta conocida.
        if (preg_match(
            '/\r?\n\s*(?:CREATININA|HEMOGLOBINA GLICOSILADA|HBA1C|HB1AC|'.
            'MICROALBUMINURIA|MICROALBUMINA|ALBUMINURIA|'.
            'RELACION ALBUMINURIA[ \/]CREATINURIA|RAC|'.
            'COLESTEROL TOTAL|COLESTEROL HDL|COLESTEROL LDL|'.
            'TRIGLICERIDOS?|TRIGLICERIOS|HEMOGLOBINA|HB|'.
            'ALBUMINA SERICA|FOSFORO|TFG|TASA DE FILTRACION)\s*:/',
            $afterLabel,
            $match,
            PREG_OFFSET_CAPTURE
        ) === 1) {
            $byteOffset = $match[0][1];
            $prefix = substr($afterLabel, 0, $byteOffset);

            return mb_substr($segment, 0, $labelLength).$prefix;
        }

        return mb_substr($segment, 0, 220);
    }

    /**
     * @param  array<int, string>  $ercCodes
     */
    private function resolveRenalStage(
        array $ercCodes,
        string $text
    ): ?int {
        foreach ($ercCodes as $code) {
            if (preg_match('/^N18([1-5])/', $code, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        if (preg_match(
            '/ESTADIO(?:\s+RENAL)?\s+(I{1,3}|IV|V|[1-5])\b/',
            $text,
            $matches
        ) === 1) {
            return match ($matches[1]) {
                'I' => 1,
                'II' => 2,
                'III' => 3,
                'IV' => 4,
                'V' => 5,
                default => (int) $matches[1],
            };
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractMedications(string $text): array
    {
        $known = [
            'LOSARTAN',
            'VALSARTAN',
            'IRBESARTAN',
            'CANDESARTAN',
            'TELMISARTAN',
            'OLMESARTAN',
            'ENALAPRIL',
            'CAPTOPRIL',
            'LISINOPRIL',
            'RAMIPRIL',
            'PERINDOPRIL',
            'METFORMINA',
            'INSULINA',
            'AMLODIPINO',
            'HIDROCLOROTIAZIDA',
            'ATORVASTATINA',
        ];

        return array_values(array_filter(
            $known,
            fn (string $medication): bool => str_contains($text, $medication)
        ));
    }

    /**
     * @return array<string, array{mentioned: bool, date: ?string}>
     */
    private function extractSpecialties(
        string $text,
        ?string $attentionDate,
        ?DateTimeImmutable $cutoff
    ): array {
        $definitions = [
            'nephrology' => ['NEFROLOG'],
            'internal_medicine' => ['MEDICINA INTERNA', 'INTERNISTA'],
            'nutrition' => ['NUTRICION'],
            'psychology' => ['PSICOLOG'],
            'social_work' => ['TRABAJO SOCIAL'],
            'nursing' => ['ENFERMER'],
            'endocrinology' => ['ENDOCRINOLOG'],
            'ophthalmology' => ['OFTALMOLOG', 'OPTOMETR', 'FOTO DE RETINA'],
        ];

        $result = [];

        foreach ($definitions as $key => $terms) {
            $mentioned = false;
            $dates = [];

            foreach ($terms as $term) {
                $offset = 0;

                while (($position = mb_stripos($text, $term, $offset)) !== false) {
                    $mentioned = true;
                    $around = mb_substr($text, max(0, $position - 100), 220);
                    $offset = $position + mb_strlen($term);

                    if (preg_match_all(
                        '/\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}/',
                        $around,
                        $matches
                    ) !== false) {
                        foreach ($matches[0] as $dateText) {
                            $date = $this->parseDate($dateText);

                            if (
                                $date instanceof DateTimeImmutable
                                && (! $cutoff instanceof DateTimeImmutable || $date <= $cutoff)
                            ) {
                                $dates[] = $date;
                            }
                        }
                    }
                }
            }

            if ($dates !== []) {
                usort(
                    $dates,
                    fn (DateTimeImmutable $left, DateTimeImmutable $right): int =>
                        $left <=> $right
                );
            }

            $result[$key] = [
                'mentioned' => $mentioned,
                'date' => $dates !== []
                    ? end($dates)->format('Y-m-d')
                    : ($mentioned ? $attentionDate : null),
            ];
        }

        return $result;
    }

    private function resolveUrinalysisResult(string $text): ?int
    {
        if (
            ! str_contains($text, 'UROANALISIS')
            && ! str_contains($text, 'PARCIAL DE ORINA')
        ) {
            return null;
        }

        if (
            str_contains($text, 'NO PATOLOGICO')
            || str_contains($text, 'SIN EVIDENCIA DE IVU')
            || str_contains($text, 'NORMAL')
        ) {
            return 0;
        }

        if (
            str_contains($text, 'PATOLOGICO')
            || str_contains($text, 'ALTERADO')
        ) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  array<int, string>  $codes
     */
    private function containsDiagnosis(array $codes, string $pattern): bool
    {
        foreach ($codes as $code) {
            if (preg_match($pattern, $code) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, string>  $needles
     */
    private function containsAny(array $values, array $needles): bool
    {
        return array_intersect($values, $needles) !== [];
    }

    private function firstMatch(string $pattern, string $text): ?string
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    private function extractNumber(string $pattern, string $text): ?float
    {
        $value = $this->firstMatch($pattern, $text);

        return $value === null ? null : $this->toFloat($value);
    }

    /**
     * @return array<int, string>
     */
    private function allDates(
        string $pattern,
        string $text,
        ?DateTimeImmutable $cutoff
    ): array {
        if (preg_match_all($pattern, $text, $matches) === false) {
            return [];
        }

        $dates = [];

        foreach ($matches[1] ?? [] as $value) {
            $date = $this->parseDate((string) $value);

            if (
                $date instanceof DateTimeImmutable
                && (! $cutoff instanceof DateTimeImmutable || $date <= $cutoff)
            ) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return array_values(array_unique($dates));
    }

    private function normalizeDate(?string $value): ?string
    {
        return $this->parseDate($value)?->format('Y-m-d');
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $date instanceof DateTimeImmutable
                && (! is_array($errors)
                    || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $range
     */
    private function isInRange(float $value, array $range): bool
    {
        return $value >= (float) $range[0]
            && $value <= (float) $range[1];
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '.', trim($value));
    }

    private function evidence(string $text, string $label): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $position = mb_stripos($text, $label);

        if ($position === false) {
            return mb_substr(trim($text), 0, 220);
        }

        return mb_substr(
            trim($text),
            max(0, $position - 25),
            220
        );
    }

    private function cleanCapture(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return $value === '' ? null : $value;
    }

    private function cleanPhone(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $numbers = preg_replace('/[^0-9 ]+/', ' ', $value) ?? $value;
        $numbers = preg_replace('/\s+/', ' ', trim($numbers)) ?? trim($numbers);

        return $numbers === '' ? null : $numbers;
    }

    private function normalizeDocumentNumber(string $value): string
    {
        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            mb_strtoupper(trim($value))
        ) ?? '';
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtoupper($text);
        $text = strtr($text, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    }
}
