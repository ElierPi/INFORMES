<?php

namespace App\Services\Informe202\Engine;

use Illuminate\Support\Str;

class VariableResolver
{
    /**
     * Alias comunes usados en reportes de las EPS.
     *
     * La clave es una forma posible del nombre y el valor
     * es el número oficial de la variable 202.
     */
    private array $aliases = [
        'tipo de registro' => 0,
        'consecutivo' => 1,
        'codigo habilitacion ips primaria' => 2,
        'tipo identificacion' => 3,
        'numero identificacion' => 4,
        'primer apellido' => 5,
        'segundo apellido' => 6,
        'primer nombre' => 7,
        'segundo nombre' => 8,
        'fecha nacimiento' => 9,
        'sexo' => 10,
        'pertenencia etnica' => 11,
        'ocupacion' => 12,
        'nivel educativo' => 13,

        'gestante' => 14,
        'gestacion' => 14,
        'sifilis gestacional' => 15,
        'mini mental' => 16,
        'hipotiroidismo congenito' => 17,
        'sintomatico respiratorio' => 18,
        'consumo tabaco' => 19,
        'lepra' => 20,
        'obesidad desnutricion' => 21,
        'tacto rectal resultado' => 22,
        'resultado tacto rectal' => 22,
        'resultado del tacto rectal' => 22,
        'acido folico preconcepcional' => 23,
        'sangre oculta resultado' => 24,
        'resultado sangre oculta' => 24,

        'agudeza visual izquierda' => 27,
        'agudeza visual ojo izquierdo' => 27,
        'agudeza visual lejana ojo izquierdo' => 27,
        'agudeza visual derecha' => 28,
        'agudeza visual ojo derecho' => 28,
        'agudeza visual lejana ojo derecho' => 28,

        'fecha peso' => 29,
        'peso' => 30,
        'fecha talla' => 31,
        'talla' => 32,
        'fecha probable parto' => 33,
        'fecha probable de parto' => 33,
        'resultado colonoscopia tamizaje' => 36,
        'resultado de colonoscopia tamizaje' => 36,
        'riesgo gestacional' => 35,

        'resultado tamizaje vale' => 40,
        'tamizaje vale resultado' => 40,
        'fecha tamizaje vale' => 63,
        'suministro metodo anticonceptivo' => 54,
        'suministro de metodo anticonceptivo' => 54,
        'fecha suministro metodo anticonceptivo' => 55,
        'fecha de suministro de metodo anticonceptivo' => 55,

        'fecha valoracion agudeza visual' => 62,
        'fecha tacto rectal' => 64,

        'fecha ldl' => 72,
        'fecha toma ldl' => 72,
        'fecha psa' => 73,
        'fecha toma psa' => 73,

        'resultado hepatitis b' => 79,
        'resultado antigeno superficie hepatitis b' => 79,
        'resultado de antigeno de superficie hepatitis b' => 79,
        'fecha sifilis' => 80,
        'resultado sifilis' => 81,
        'fecha vih' => 82,
        'resultado vih' => 83,
        'resultado prueba para vih' => 83,
        'resultado de prueba para vih' => 83,
        'fecha tsh neonatal' => 84,
        'resultado tsh neonatal' => 85,

        'tamizaje cancer cuello uterino' => 86,
        'fecha tamizaje cancer cuello uterino' => 87,
        'resultado tamizaje cancer cuello uterino' => 88,

        'resultado ldl' => 92,
        'resultado hdl' => 95,
        'fecha mamografia' => 96,
        'fecha toma mamografia' => 96,
        'fecha de toma de mamografia' => 96,
        'resultado mamografia' => 97,

        'resultado trigliceridos' => 98,
        'trigliceridos resultado' => 98,
        'trigliceridos' => 98,

        'fecha hemoglobina' => 103,
        'resultado hemoglobina' => 104,
        'fecha glicemia basal' => 105,
        'resultado glicemia basal' => 57,

        'fecha creatinina' => 106,
        'fecha toma creatinina' => 106,
        'resultado creatinina' => 107,
        'creatinina resultado' => 107,
        'creatinina' => 107,

        'resultado psa' => 109,
        'fecha hepatitis c' => 110,
        'fecha hdl' => 111,
        'fecha baciloscopia' => 112,
        'resultado baciloscopia' => 113,
        'riesgo cardiovascular' => 114,
        'riesgo metabolico' => 117,
        'fecha trigliceridos' => 118,
        'fecha toma trigliceridos' => 118,
        'fecha atencion salud bucal' => 76,
        'fecha de atencion en salud bucal' => 76,
        'cop por persona' => 102,
        'fortificacion casera' => 70,
        'suministro de fortificacion casera' => 70,
    ];

    public function resolve(array $error): ?int
    {
        /*
         * El parser de la EPS puede entregar directamente
         * el número de variable normalizado.
         */
        if (
            isset($error['variable'])
            && is_numeric($error['variable'])
        ) {
            $variable = (int) $error['variable'];

            return $this->isValidVariable($variable)
                ? $variable
                : null;
        }

        $field = $this->normalize(
            (string) ($error['campo'] ?? '')
        );

        $message = $this->normalize(
            (string) ($error['mensaje'] ?? '')
        );

        $combined = trim("{$field} {$message}");

        /*
         * Busca textos como:
         * variable 107
         * campo 98
         * columna 54
         */
        if (
            preg_match(
                '/(?:variable|campo|columna)\s*[:#-]?\s*(\d{1,3})/i',
                $combined,
                $matches
            )
        ) {
            $variable = (int) $matches[1];

            if ($this->isValidVariable($variable)) {
                return $variable;
            }
        }

        /*
         * Coincidencia exacta con alias.
         */
        if (isset($this->aliases[$field])) {
            return $this->aliases[$field];
        }

        /*
         * Coincidencia parcial con alias.
         */
        if ($field !== '') {
            foreach ($this->aliases as $alias => $variable) {
                if (
                    str_contains($field, $alias)
                    || str_contains($alias, $field)
                ) {
                    return $variable;
                }
            }
        }

        /*
         * Finalmente compara con el catálogo oficial.
         */
        return $this->resolveByCatalogName($field);
    }

    private function resolveByCatalogName(
        string $fieldName
    ): ?int {
        if ($fieldName === '') {
            return null;
        }

        $bestVariable = null;
        $bestScore = 0.0;

        foreach (
            config('resolucion202.fields', [])
            as $variable => $definition
        ) {
            $catalogName = $this->normalize(
                (string) ($definition['name'] ?? '')
            );

            if ($catalogName === '') {
                continue;
            }

            if ($fieldName === $catalogName) {
                return (int) $variable;
            }

            if (
                str_contains($catalogName, $fieldName)
                || str_contains($fieldName, $catalogName)
            ) {
                return (int) $variable;
            }

            $percentage = 0.0;

            similar_text(
                $fieldName,
                $catalogName,
                $percentage
            );

            if (
                $percentage >= 60
                && $percentage > $bestScore
            ) {
                $bestScore = $percentage;
                $bestVariable = (int) $variable;
            }
        }

        return $bestVariable;
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower($value);

        $value = preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $value
        );

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $value ?? ''
            ) ?? ''
        );
    }

    private function isValidVariable(
        int $variable
    ): bool {
        return $variable >= 0
            && $variable <= 118;
    }
}