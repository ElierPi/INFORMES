<?php

namespace App\Services\Sigires\Cronicos;

use DateTimeImmutable;

class SigiresPrecursorasRecordBuilder
{
    /**
     * @return array{
     *     values: array<int, mixed>,
     *     statuses: array<int, array{status: string, source: string, note: string}>,
     *     pending: array<int, array<string, mixed>>,
     *     warnings: array<int, string>,
     *     ready: bool
     * }
     */
    public function build(
        array $base,
        array $history,
        string $cutoffDate,
        string $eapbCode
    ): array {
        $values = array_fill(0, (int) config(
            'sigires_cronicos.profile.field_count',
            143
        ), null);
        $statuses = [];
        $warnings = [];

        $baseDocumentType = $this->cleanCode(
            $base['document_type'] ?? null
        );
        $historyDocumentType = $this->cleanCode(
            $history['document_type_history']
                ?? $history['document_type_file']
                ?? null
        );

        if (
            $baseDocumentType !== ''
            && $historyDocumentType !== ''
            && $baseDocumentType !== $historyDocumentType
        ) {
            $warnings[] =
                "El tipo de documento de la base ({$baseDocumentType}) ".
                "no coincide con la historia clínica ({$historyDocumentType}).";
        }

        $documentType = $baseDocumentType !== ''
            ? $baseDocumentType
            : $historyDocumentType;

        $documentNumber = $this->cleanCode(
            $base['document_number']
                ?? $history['document_number']
                ?? null
        );

        $attentionDate = $history['attention_date'] ?? null;
        $birthDate = $this->normalizeDate(
            $base['birth_date'] ?? $history['birth_date'] ?? null
        );

        $sex = $this->normalizeSex(
            $base['sex'] ?? $history['sex'] ?? null
        );

        $providerCode = $this->digits(
            $base['provider_code'] ?? null
        );

        $this->set(
            $values,
            $statuses,
            'CAMPO_1',
            $this->cleanText($base['first_name'] ?? null),
            'auto',
            'Base regional',
            'Primer nombre reportado en la base.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_2',
            $this->cleanText($base['second_name'] ?? null) ?: 'NONE',
            'auto',
            'Base regional',
            'Se usa NONE cuando no existe segundo nombre.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_3',
            $this->cleanText($base['first_surname'] ?? null),
            'auto',
            'Base regional',
            'Primer apellido reportado en la base.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_4',
            $this->cleanText($base['second_surname'] ?? null) ?: 'NOAP',
            'auto',
            'Base regional',
            'Se usa NOAP cuando no existe segundo apellido.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_5',
            $documentType,
            'auto',
            'Base regional',
            'Se prioriza el tipo de documento oficial de la base.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_6',
            $documentNumber,
            'auto',
            'Base regional',
            'Número de identificación.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_7',
            $birthDate,
            'auto',
            'Base regional / historia clínica',
            'Fecha de nacimiento normalizada.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_8',
            $sex,
            'auto',
            'Base regional / historia clínica',
            'Sexo normalizado a M o F.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_9',
            null,
            'pending',
            'Sin dato',
            'Debe confirmarse el régimen de afiliación.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_10',
            $this->cleanCode($eapbCode),
            'review',
            'Dato ingresado por el usuario',
            'Confirmar código EAPB de seis caracteres.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_11',
            null,
            'pending',
            'Sin dato',
            'Debe confirmarse la pertenencia étnica.'
        );

        $populationGroup = $this->resolvePopulationGroup(
            $history['population_group'] ?? null
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_12',
            $populationGroup,
            $populationGroup === null ? 'pending' : 'review',
            'Historia clínica',
            $populationGroup === null
                ? 'Debe confirmarse el grupo poblacional.'
                : 'Código inferido del texto de la historia clínica.'
        );

        $municipality = $this->resolveMunicipality(
            $history['city'] ?? null
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_13',
            $municipality,
            $municipality === null ? 'pending' : 'review',
            'Historia clínica',
            $municipality === null
                ? 'Debe confirmarse el municipio DIVIPOLA.'
                : 'Código DIVIPOLA inferido desde la ciudad.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_14',
            $history['phone'] ?? '0',
            isset($history['phone']) ? 'auto' : 'review',
            'Historia clínica',
            isset($history['phone'])
                ? 'Teléfono extraído de la historia clínica.'
                : 'No se encontró teléfono; se propone 0.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_15',
            null,
            'pending',
            'Sin dato',
            'Debe confirmarse la fecha de afiliación a la EAPB.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_16',
            $providerCode,
            'auto',
            'Base regional',
            'Código de habilitación de la IPS de seguimiento.'
        );

        $baseRenalProgram = $this->isMarked(
            $base['renal_program'] ?? null
        );
        $historyRenalProgram = (bool) (
            $history['renal_program_mentioned'] ?? false
        );
        $pendingProgramEntry = (bool) (
            $history['renal_program_entry_pending'] ?? false
        );

        $programEntryDate = match (true) {
            $baseRenalProgram && is_string($attentionDate) => $attentionDate,
            $pendingProgramEntry => '1800-01-01',
            default => null,
        };

        $this->set(
            $values,
            $statuses,
            'CAMPO_17',
            $programEntryDate,
            $programEntryDate === null ? 'pending' : 'review',
            'Base regional / historia clínica',
            $programEntryDate === null
                ? 'Debe confirmarse la fecha de ingreso al programa renal.'
                : ($pendingProgramEntry
                    ? 'La historia indica que estaba por ingresar al programa.'
                    : 'Fecha propuesta desde la última atención; requiere confirmación.')
        );

        $hta = $this->isMarked($base['hta'] ?? null)
            || (bool) ($history['hta'] ?? false);
        $dm = $this->isMarked($base['dm'] ?? null)
            || (bool) ($history['dm'] ?? false);
        $ercConfirmed = $this->isMarked($base['erc'] ?? null)
            || (bool) ($history['erc'] ?? false);

        $this->setDiagnosisBlock(
            $values,
            $statuses,
            'CAMPO_18',
            'CAMPO_19',
            'CAMPO_19_1',
            $hta,
            1,
            2
        );

        $dmType = $dm
            ? (int) ($history['dm_type'] ?? 4)
            : 2;
        $this->set(
            $values,
            $statuses,
            'CAMPO_20',
            $dmType,
            'auto',
            'Base regional / diagnósticos de la historia clínica',
            $dm
                ? 'Tipo de diabetes inferido desde el código diagnóstico.'
                : 'No se identificó diagnóstico de diabetes.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_21',
            $dm ? '1800-01-01' : '1845-01-01',
            'review',
            'Regla normativa',
            $dm
                ? 'Diagnóstico confirmado, pero fecha histórica desconocida.'
                : 'No aplica por ausencia de diagnóstico de diabetes.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_21_1',
            $dm ? 0 : 98,
            'review',
            'Regla operativa Sanitas',
            'Costo propuesto en 0 cuando aplica y 98 cuando no aplica.'
        );

        $labs = is_array($history['labs'] ?? null)
            ? $history['labs']
            : [];

        $renalClassification = $this->resolveRenalClassification(
            $ercConfirmed,
            $history,
            $labs
        );
        $renalStage = $this->resolveRenalStage(
            $renalClassification,
            $history,
            $labs
        );

        $etiology = match (true) {
            $renalClassification !== 1 => 98,
            $dm => 7,
            $hta => 8,
            default => 6,
        };
        $this->set(
            $values,
            $statuses,
            'CAMPO_22',
            $etiology,
            $renalClassification === 1 ? 'review' : 'auto',
            'Regla clínica',
            $renalClassification === 1
                ? 'Etiología propuesta por comorbilidad; debe confirmarse.'
                : 'No aplica por no existir ERC confirmada.'
        );

        $this->setMeasurement(
            $values,
            $statuses,
            'CAMPO_23',
            $history['weight'] ?? null,
            'Historia clínica',
            'Último peso encontrado.'
        );
        $this->setMeasurement(
            $values,
            $statuses,
            'CAMPO_24',
            $history['height'] ?? null,
            'Historia clínica',
            'Última talla encontrada.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_25',
            $history['pas'] ?? 999,
            isset($history['pas']) ? 'auto' : 'review',
            'Historia clínica / comodín normativo',
            isset($history['pas'])
                ? 'Tensión arterial sistólica.'
                : 'No se encontró TAS; se propone 999.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_26',
            $history['pad'] ?? 999,
            isset($history['pad']) ? 'auto' : 'review',
            'Historia clínica / comodín normativo',
            isset($history['pad'])
                ? 'Tensión arterial diastólica.'
                : 'No se encontró TAD; se propone 999.'
        );

        $this->setLaboratoryPair(
            $values,
            $statuses,
            'CAMPO_27',
            'CAMPO_27_1',
            $labs['creatinine'] ?? [],
            99,
            '1800-01-01',
            'Creatinina'
        );

        if ($dm) {
            $this->setLaboratoryPair(
                $values,
                $statuses,
                'CAMPO_28',
                'CAMPO_28_1',
                $labs['hba1c'] ?? [],
                99,
                '1800-01-01',
                'Hemoglobina glicosilada'
            );
        } else {
            $this->set(
                $values,
                $statuses,
                'CAMPO_28',
                98,
                'auto',
                'Regla normativa',
                'No aplica por ausencia de diabetes.'
            );
            $this->set(
                $values,
                $statuses,
                'CAMPO_28_1',
                '1845-01-01',
                'auto',
                'Regla normativa',
                'No aplica por ausencia de diabetes.'
            );
        }

        $this->setLaboratoryPair(
            $values,
            $statuses,
            'CAMPO_29',
            'CAMPO_29_1',
            $labs['albuminuria'] ?? [],
            9999,
            '1800-01-01',
            'Albuminuria'
        );
        $this->setLaboratoryPair(
            $values,
            $statuses,
            'CAMPO_30',
            'CAMPO_30_1',
            $labs['rac'] ?? [],
            9999,
            '1800-01-01',
            'Relación albuminuria/creatininuria'
        );
        $this->setLaboratoryPair(
            $values,
            $statuses,
            'CAMPO_31',
            'CAMPO_31_1',
            $labs['cholesterol_total'] ?? [],
            999,
            '1845-01-01',
            'Colesterol total'
        );
        $this->setLaboratoryPair(
            $values,
            $statuses,
            'CAMPO_32',
            'CAMPO_32_1',
            $labs['hdl'] ?? [],
            999,
            '1845-01-01',
            'Colesterol HDL'
        );
        $this->setLaboratoryPair(
            $values,
            $statuses,
            'CAMPO_33',
            'CAMPO_33_1',
            $labs['ldl'] ?? [],
            999,
            '1845-01-01',
            'Colesterol LDL'
        );

        if ($renalClassification === 1) {
            $this->setLaboratoryPair(
                $values,
                $statuses,
                'CAMPO_34',
                'CAMPO_34_1',
                [],
                9999,
                '1800-01-01',
                'Parathormona'
            );
        } else {
            $this->set(
                $values,
                $statuses,
                'CAMPO_34',
                9988,
                'auto',
                'Regla normativa',
                'No aplica sin ERC confirmada.'
            );
            $this->set(
                $values,
                $statuses,
                'CAMPO_34_1',
                '1845-01-01',
                'auto',
                'Regla normativa',
                'No aplica sin ERC confirmada.'
            );
        }

        $tfg = $this->labValue($labs['tfg'] ?? []);
        $this->set(
            $values,
            $statuses,
            'CAMPO_35',
            $tfg ?? 999,
            $tfg === null ? 'review' : 'auto',
            'Historia clínica / regla normativa',
            $tfg === null
                ? 'No se encontró TFG válida; se propone 999.'
                : 'Última TFG identificada.'
        );

        $this->setMedicationStatus(
            $values,
            $statuses,
            'CAMPO_36',
            (bool) ($history['receives_ieca'] ?? false),
            'IECA'
        );
        $this->setMedicationStatus(
            $values,
            $statuses,
            'CAMPO_37',
            (bool) ($history['receives_ara2'] ?? false),
            'ARA II'
        );

        $this->set(
            $values,
            $statuses,
            'CAMPO_38',
            $renalClassification,
            $ercConfirmed ? 'auto' : 'review',
            'Diagnósticos y laboratorios',
            $this->renalClassificationNote($renalClassification)
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_39',
            $renalStage,
            $ercConfirmed ? 'auto' : 'review',
            'Diagnósticos y TFG',
            'Estadio propuesto de acuerdo con diagnóstico o TFG.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_40',
            $renalClassification === 1 && $renalStage === 5
                ? '1800-01-01'
                : '1845-01-01',
            'review',
            'Regla normativa',
            'Fecha histórica de ERC estadio 5 no disponible.'
        );

        $inRenalProgram = $baseRenalProgram || $historyRenalProgram;
        $this->set(
            $values,
            $statuses,
            'CAMPO_41',
            $inRenalProgram ? 1 : 2,
            'review',
            'Base regional / historia clínica',
            $inRenalProgram
                ? 'Se identificó seguimiento o mención de programa renal.'
                : 'No se identificó evidencia de programa renal.'
        );

        $this->setPrecursorasDefaults(
            $values,
            $statuses,
            $renalClassification,
            $renalStage,
            $labs,
            $eapbCode,
            $cutoffDate
        );

        $this->setAdditionalFields(
            $values,
            $statuses,
            $history,
            $labs,
            $attentionDate,
            $renalClassification,
            $renalStage
        );

        $pending = $this->collectPending($values, $statuses);

        return [
            'values' => $values,
            'statuses' => $statuses,
            'pending' => $pending,
            'warnings' => $warnings,
            'ready' => $pending === [],
        ];
    }

    private function setDiagnosisBlock(
        array &$values,
        array &$statuses,
        string $diagnosisCode,
        string $dateCode,
        string $costCode,
        bool $applies,
        int $yesValue,
        int $noValue
    ): void {
        $this->set(
            $values,
            $statuses,
            $diagnosisCode,
            $applies ? $yesValue : $noValue,
            'auto',
            'Base regional / diagnósticos de la historia clínica',
            $applies
                ? 'Diagnóstico identificado.'
                : 'No se identificó el diagnóstico.'
        );
        $this->set(
            $values,
            $statuses,
            $dateCode,
            $applies ? '1800-01-01' : '1845-01-01',
            'review',
            'Regla normativa',
            $applies
                ? 'Diagnóstico confirmado con fecha histórica desconocida.'
                : 'No aplica por ausencia del diagnóstico.'
        );
        $this->set(
            $values,
            $statuses,
            $costCode,
            $applies ? 0 : 98,
            'review',
            'Regla operativa Sanitas',
            'Costo propuesto en 0 cuando aplica y 98 cuando no aplica.'
        );
    }

    private function setMeasurement(
        array &$values,
        array &$statuses,
        string $code,
        mixed $value,
        string $source,
        string $note
    ): void {
        $this->set(
            $values,
            $statuses,
            $code,
            $value,
            $value === null ? 'pending' : 'auto',
            $source,
            $value === null
                ? "{$note} Debe revisarse manualmente."
                : $note
        );
    }

    /**
     * @param  array<string, mixed>  $lab
     */
    private function setLaboratoryPair(
        array &$values,
        array &$statuses,
        string $valueCode,
        string $dateCode,
        array $lab,
        mixed $missingValue,
        string $missingDate,
        string $label
    ): void {
        $value = $this->labValue($lab);
        $date = $this->labDate($lab);

        $this->set(
            $values,
            $statuses,
            $valueCode,
            $value ?? $missingValue,
            $value === null ? 'review' : 'auto',
            'Historia clínica / comodín normativo',
            $value === null
                ? "{$label} no encontrado; se propone el comodín."
                : "{$label} extraído de la historia clínica."
        );
        $this->set(
            $values,
            $statuses,
            $dateCode,
            $date ?? ($value === null ? $missingDate : null),
            $date === null ? 'review' : 'auto',
            'Historia clínica / comodín normativo',
            $date === null
                ? "No se encontró fecha válida para {$label}."
                : "Fecha de {$label} extraída de la historia clínica."
        );
    }

    private function setMedicationStatus(
        array &$values,
        array &$statuses,
        string $code,
        bool $found,
        string $label
    ): void {
        $this->set(
            $values,
            $statuses,
            $code,
            $found ? 1 : 2,
            $found ? 'auto' : 'review',
            'Medicamentos de la historia clínica',
            $found
                ? "Se identificó un medicamento {$label}."
                : "No se identificó {$label}; se propone no formulado y debe confirmarse."
        );
    }

    private function setPrecursorasDefaults(
        array &$values,
        array &$statuses,
        int $renalClassification,
        int $renalStage,
        array $labs,
        string $eapbCode,
        string $cutoffDate
    ): void {
        $defaults = [
            'CAMPO_42' => 98,
            'CAMPO_43' => 97,
            'CAMPO_44' => '1845-01-01',
            'CAMPO_45' => '1845-01-01',
            'CAMPO_46' => 98,
            'CAMPO_47' => 98,
            'CAMPO_48' => 98,
            'CAMPO_49' => 98,
            'CAMPO_50' => 98,
            'CAMPO_51' => 98,
            'CAMPO_52' => 98,
            'CAMPO_53' => 98,
            'CAMPO_54' => 98,
            'CAMPO_55' => '1811-01-01',
            'CAMPO_56' => '1811-01-01',
            'CAMPO_57' => 2,
            'CAMPO_58' => 98,
        ];

        foreach ($defaults as $code => $value) {
            $this->set(
                $values,
                $statuses,
                $code,
                $value,
                'auto',
                'Perfil PRECURSORAS',
                'Valor no aplica porque el paciente no está en TRR.'
            );
        }

        foreach ([
            'CAMPO_59' => 'hemoglobin',
            'CAMPO_60' => 'albumin',
            'CAMPO_61' => 'phosphorus',
        ] as $code => $labKey) {
            $labValue = $this->labValue($labs[$labKey] ?? []);

            $this->set(
                $values,
                $statuses,
                $code,
                $labValue ?? ($renalClassification === 1 ? 99 : 98),
                $labValue === null ? 'review' : 'auto',
                'Historia clínica / regla normativa',
                $labValue === null
                    ? 'No se encontró resultado; se propone el comodín correspondiente.'
                    : 'Resultado extraído de la historia clínica.'
            );
        }

        $transplantEvaluation = $renalClassification === 0
            ? 98
            : ($renalStage === 5 ? 99 : 97);

        for ($position = 62; $position <= 62; $position++) {
            $this->set(
                $values,
                $statuses,
                'CAMPO_'.$position,
                $transplantEvaluation,
                'auto',
                'Perfil PRECURSORAS',
                'No aplica o no ha sido valorado según clasificación renal.'
            );
        }

        for ($index = 1; $index <= 11; $index++) {
            $this->set(
                $values,
                $statuses,
                'CAMPO_62_'.$index,
                $transplantEvaluation,
                'auto',
                'Perfil PRECURSORAS',
                'No aplica o no ha sido valorado para trasplante.'
            );
        }

        $transplantDefaults = [
            'CAMPO_63' => '1849-01-01',
            'CAMPO_63_1' => 90,
            'CAMPO_64' => 5,
            'CAMPO_65' => 98,
            'CAMPO_66' => 98,
            'CAMPO_67' => 98,
            'CAMPO_68' => 98,
            'CAMPO_69' => 98,
        ];

        for ($index = 1; $index <= 7; $index++) {
            $transplantDefaults['CAMPO_69_'.$index] = '1845-01-01';
        }

        $transplantDefaults += [
            'CAMPO_70' => 98,
            'CAMPO_70_1' => 98,
            'CAMPO_70_2' => 98,
            'CAMPO_70_3' => 98,
            'CAMPO_70_4' => 98,
            'CAMPO_70_5' => 98,
            'CAMPO_70_6' => 98,
            'CAMPO_70_7' => 98,
            'CAMPO_70_8' => 98,
            'CAMPO_70_9' => 98,
            'CAMPO_71' => 98,
            'CAMPO_72' => '1845-01-01',
            'CAMPO_73' => '1845-01-01',
            'CAMPO_74' => 98,
            'CAMPO_75' => 98,
        ];

        foreach ($transplantDefaults as $code => $value) {
            $this->set(
                $values,
                $statuses,
                $code,
                $value,
                'auto',
                'Perfil PRECURSORAS',
                'Valor no aplica por ausencia de trasplante renal.'
            );
        }

        $this->set(
            $values,
            $statuses,
            'CAMPO_76',
            null,
            'pending',
            'Sin dato',
            'Debe confirmarse el tiempo de prestación de servicios.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_77',
            0,
            'review',
            'Regla operativa Sanitas',
            'Costo total propuesto en 0.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_78',
            $this->cleanCode($eapbCode),
            'review',
            'Dato ingresado por el usuario',
            'Se propone la misma EAPB como origen; debe confirmarse.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_79',
            98,
            'review',
            'Regla normativa',
            'Se propone sin novedad respecto al reporte anterior.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_80',
            98,
            'auto',
            'Regla normativa',
            'No aplica para paciente vivo.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_80_1',
            '1845-01-01',
            'auto',
            'Regla normativa',
            'No aplica para paciente vivo.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_81',
            null,
            'pending',
            'Sin dato',
            'Debe confirmarse el código único BDUA/BDEX/PVS.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPO_82',
            $this->normalizeDate($cutoffDate),
            'auto',
            'Fecha de corte',
            'Fecha de corte del reporte.'
        );
    }

    private function setAdditionalFields(
        array &$values,
        array &$statuses,
        array $history,
        array $labs,
        ?string $attentionDate,
        int $renalClassification,
        int $renalStage
    ): void {
        $this->set(
            $values,
            $statuses,
            'CAMPOA_1',
            isset($history['weight']) ? $attentionDate : '1845-01-01',
            'review',
            'Historia clínica',
            'Fecha propuesta de la última medición de peso.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_2',
            isset($history['height']) ? $attentionDate : '1845-01-01',
            'review',
            'Historia clínica',
            'Fecha propuesta de la última medición de talla.'
        );

        $this->set(
            $values,
            $statuses,
            'CAMPOA_3',
            $this->labDate($labs['triglycerides'] ?? []) ?? '1845-01-01',
            'review',
            'Historia clínica',
            'Fecha del último triglicérido o dato no disponible.'
        );
        $triglycerides = $this->labValue($labs['triglycerides'] ?? []);
        $this->set(
            $values,
            $statuses,
            'CAMPOA_4',
            $triglycerides === null
                ? 9999
                : min(5000, $triglycerides),
            $triglycerides === null ? 'review' : 'auto',
            'Historia clínica',
            'Resultado de triglicéridos.'
        );

        $this->set(
            $values,
            $statuses,
            'CAMPOA_5',
            $this->labDate($labs['tfg'] ?? []) ?? '1845-01-01',
            'review',
            'Historia clínica',
            'Fecha de la última TFG registrada.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_6',
            $renalClassification === 1 && $renalStage < 5
                ? '1845-01-01'
                : '1800-01-01',
            'review',
            'Regla Sanitas',
            'Fecha de diagnóstico ERC 1-4 no disponible o no aplica.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_7',
            $this->labDate($labs['hemoglobin'] ?? []) ?? '1845-01-01',
            'review',
            'Historia clínica',
            'Fecha de última hemoglobina.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_8',
            $this->labDate($labs['albumin'] ?? []) ?? '1845-01-01',
            'review',
            'Historia clínica',
            'Fecha de última albúmina sérica.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_9',
            $this->labDate($labs['phosphorus'] ?? [])
                ?? ($renalClassification === 1 ? '1845-01-01' : '1800-01-01'),
            'review',
            'Historia clínica / regla Sanitas',
            'Fecha de último fósforo.'
        );

        $specialties = is_array($history['specialties'] ?? null)
            ? $history['specialties']
            : [];

        $this->setSpecialtyDate(
            $values,
            $statuses,
            'CAMPOA_10',
            $specialties['nephrology'] ?? [],
            $renalStage === 5 ? '1845-01-01' : '1800-01-01',
            'Nefrología'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_11',
            $attentionDate ?? '1845-01-01',
            'review',
            'Historia clínica',
            'Se propone la última atención médica encontrada.'
        );

        $specialtyCode = $this->resolveProgramSpecialtyCode(
            $history['specialty'] ?? null
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_12',
            $specialtyCode,
            'review',
            'Historia clínica',
            'Especialidad de la última valoración del programa.'
        );

        $specialtyMap = [
            'CAMPOA_13' => ['endocrinology', 'Endocrinología'],
            'CAMPOA_14' => ['ophthalmology', 'Oftalmología/retina'],
            'CAMPOA_15' => ['nursing', 'Enfermería'],
            'CAMPOA_16' => ['nutrition', 'Nutrición'],
            'CAMPOA_17' => ['social_work', 'Trabajo Social'],
            'CAMPOA_18' => [null, 'Química farmacéutica'],
            'CAMPOA_19' => ['psychology', 'Psicología'],
        ];

        foreach ($specialtyMap as $code => [$key, $label]) {
            $this->setSpecialtyDate(
                $values,
                $statuses,
                $code,
                $key === null ? [] : ($specialties[$key] ?? []),
                '1845-01-01',
                $label
            );
        }

        $urinalysis = $history['urinalysis_result'] ?? null;
        $this->set(
            $values,
            $statuses,
            'CAMPOA_20',
            $urinalysis === null
                ? '1845-01-01'
                : ($attentionDate ?? '1845-01-01'),
            'review',
            'Historia clínica',
            'Fecha de último parcial de orina.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_21',
            $urinalysis,
            $urinalysis === null ? 'review' : 'auto',
            'Historia clínica',
            $urinalysis === null
                ? 'No se encontró resultado de parcial de orina.'
                : 'Resultado de parcial de orina interpretado.'
        );
        $this->set(
            $values,
            $statuses,
            'CAMPOA_22',
            $history['waist'] ?? 999,
            isset($history['waist']) ? 'auto' : 'review',
            'Historia clínica / comodín Sanitas',
            isset($history['waist'])
                ? 'Circunferencia de cintura.'
                : 'No se encontró; se propone 999.'
        );
    }

    private function setSpecialtyDate(
        array &$values,
        array &$statuses,
        string $code,
        array $specialty,
        string $fallback,
        string $label
    ): void {
        $date = is_string($specialty['date'] ?? null)
            ? $specialty['date']
            : null;

        $this->set(
            $values,
            $statuses,
            $code,
            $date ?? $fallback,
            $date === null ? 'review' : 'auto',
            'Historia clínica / regla Sanitas',
            $date === null
                ? "No se encontró fecha de {$label}; se propone comodín."
                : "Fecha de {$label} encontrada."
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectPending(
        array $values,
        array $statuses
    ): array {
        $pending = [];
        $fields = config('sigires_cronicos.fields', []);

        foreach ($fields as $position => $definition) {
            $required = (bool) ($definition['required'] ?? false);
            $value = $values[$position] ?? null;
            $status = $statuses[$position]['status'] ?? null;

            if (
                $required
                && ($value === null || $value === '' || $status === 'pending')
            ) {
                $pending[] = [
                    'position' => (int) $position + 1,
                    'code' => $definition['code'] ?? '',
                    'field' => $definition['description'] ?? '',
                    'reason' => $statuses[$position]['note']
                        ?? 'Campo obligatorio pendiente.',
                ];
            }
        }

        return $pending;
    }

    private function resolveRenalClassification(
        bool $ercConfirmed,
        array $history,
        array $labs
    ): int {
        if ($ercConfirmed) {
            return 1;
        }

        $creatinine = $this->labValue($labs['creatinine'] ?? []);
        $rac = $this->labValue($labs['rac'] ?? []);
        $albuminuria = $this->labValue($labs['albuminuria'] ?? []);
        $tfg = $this->labValue($labs['tfg'] ?? []);

        if (
            $creatinine !== null
            && ($rac !== null || $albuminuria !== null)
            && ($tfg === null || $tfg >= 60)
        ) {
            return 0;
        }

        if (
            $creatinine !== null
            || $rac !== null
            || $albuminuria !== null
        ) {
            return 3;
        }

        return 3;
    }

    private function resolveRenalStage(
        int $classification,
        array $history,
        array $labs
    ): int {
        if ($classification === 0) {
            return 98;
        }

        if ($classification !== 1) {
            return 99;
        }

        $explicit = $history['erc_stage'] ?? null;

        if (is_numeric($explicit) && (int) $explicit >= 1 && (int) $explicit <= 5) {
            return (int) $explicit;
        }

        $tfg = $this->labValue($labs['tfg'] ?? []);

        return match (true) {
            $tfg === null => 99,
            $tfg >= 90 => 1,
            $tfg >= 60 => 2,
            $tfg >= 30 => 3,
            $tfg >= 15 => 4,
            default => 5,
        };
    }

    private function renalClassificationNote(int $classification): string
    {
        return match ($classification) {
            0 => 'No se identificó ERC confirmada y existe estudio renal suficiente.',
            1 => 'Se identificó diagnóstico confirmado de ERC.',
            2 => 'Paciente indeterminado para ERC.',
            3 => 'Paciente no estudiado o con estudio renal incompleto.',
            default => 'Clasificación renal pendiente.',
        };
    }

    private function resolvePopulationGroup(?string $value): ?int
    {
        $normalized = $this->cleanText($value);

        if ($normalized === '') {
            return null;
        }

        foreach (config('sigires_cronicos.population_groups', []) as $label => $code) {
            if (str_contains($normalized, $this->cleanText($label))) {
                return (int) $code;
            }
        }

        return null;
    }

    private function resolveMunicipality(?string $city): ?string
    {
        $normalized = $this->cleanText($city);

        foreach (config('sigires_cronicos.municipalities', []) as $name => $code) {
            if ($normalized !== '' && str_contains($normalized, $this->cleanText($name))) {
                return (string) $code;
            }
        }

        return null;
    }

    private function resolveProgramSpecialtyCode(?string $specialty): int
    {
        $specialty = $this->cleanText($specialty);

        return match (true) {
            str_contains($specialty, 'MEDICINA FAMILIAR') => 2,
            str_contains($specialty, 'MEDICINA INTERNA')
                || str_contains($specialty, 'INTERNISTA') => 3,
            str_contains($specialty, 'GERIATR') => 4,
            str_contains($specialty, 'MEDICINA GENERAL') => 1,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $lab
     */
    private function labValue(array $lab): ?float
    {
        return is_numeric($lab['value'] ?? null)
            ? (float) $lab['value']
            : null;
    }

    /**
     * @param  array<string, mixed>  $lab
     */
    private function labDate(array $lab): ?string
    {
        $date = $lab['date'] ?? null;

        return is_string($date) && $date !== ''
            ? $date
            : null;
    }

    private function set(
        array &$values,
        array &$statuses,
        string $code,
        mixed $value,
        string $status,
        string $source,
        string $note
    ): void {
        $position = $this->positionForCode($code);

        if ($position === null) {
            return;
        }

        $values[$position] = $this->normalizeValue($value);
        $statuses[$position] = [
            'status' => $status,
            'source' => $source,
            'note' => $note,
        ];
    }

    private function positionForCode(string $code): ?int
    {
        foreach (config('sigires_cronicos.fields', []) as $position => $definition) {
            if (($definition['code'] ?? null) === $code) {
                return (int) $position;
            }
        }

        return null;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $date instanceof DateTimeImmutable
                && (! is_array($errors)
                    || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeSex(mixed $value): ?string
    {
        $value = $this->cleanText($value);

        return match (true) {
            $value === 'M' || str_starts_with($value, 'MASCUL') => 'M',
            $value === 'F' || str_starts_with($value, 'FEMEN') => 'F',
            default => null,
        };
    }

    private function isMarked(mixed $value): bool
    {
        $value = $this->cleanText($value);

        return in_array($value, ['1', 'SI', 'S', 'X', 'TRUE'], true);
    }

    private function cleanText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = mb_strtoupper(trim((string) $value));
        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function cleanCode(mixed $value): string
    {
        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            $this->cleanText($value)
        ) ?? '';
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
