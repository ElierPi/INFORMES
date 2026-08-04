<?php

namespace App\Services\Importing;

class HeaderDetector
{
    public function __construct(
        private readonly AliasResolver $aliasResolver
    ) {
    }

    /**
     * @param array<int, array{
     *     column_index: int,
     *     column_letter: string,
     *     original: string,
     *     normalized: string
     * }> $headers
     *
     * @param array<string, array<int, string>> $definitions
     *
     * @return array{
     *     mapping: array<string, array<string, mixed>|null>,
     *     unmapped_columns: array<int, array<string, mixed>>,
     *     score: float,
     *     matched_fields: int
     * }
     */
    public function detect(
        array $headers,
        array $definitions
    ): array {
        $candidates = [];

        /*
        |--------------------------------------------------------------------------
        | Construir todos los posibles emparejamientos
        |--------------------------------------------------------------------------
        */

        foreach ($definitions as $field => $aliases) {
            foreach ($headers as $header) {
                if ($header['original'] === '') {
                    continue;
                }

                $resolution = $this->aliasResolver->resolve(
                    $header['original'],
                    $aliases
                );

                if (! $resolution['matched']) {
                    continue;
                }

                $candidates[] = [
                    'field' => $field,
                    'column_index' => $header['column_index'],
                    'column_letter' => $header['column_letter'],
                    'header' => $header['original'],
                    'normalized_header' => $header['normalized'],
                    'confidence' => $resolution['confidence'],
                    'matched_alias' => $resolution['alias'],
                    'match_type' => $resolution['match_type'],
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Ordenar desde la coincidencia más segura
        |--------------------------------------------------------------------------
        */

        usort(
            $candidates,
            static fn (array $a, array $b): int =>
                $b['confidence'] <=> $a['confidence']
        );

        $mapping = array_fill_keys(
            array_keys($definitions),
            null
        );

        $assignedFields = [];
        $assignedColumns = [];

        /*
        |--------------------------------------------------------------------------
        | Asignación uno a uno
        |--------------------------------------------------------------------------
        |
        | Un campo solo recibe una columna.
        | Una columna solo puede pertenecer a un campo.
        |
        */

        foreach ($candidates as $candidate) {
            $field = $candidate['field'];
            $columnIndex = $candidate['column_index'];

            if (isset($assignedFields[$field])) {
                continue;
            }

            if (isset($assignedColumns[$columnIndex])) {
                continue;
            }

            $mapping[$field] = [
                'column_index' => $columnIndex,
                'column_letter' => $candidate['column_letter'],
                'header' => $candidate['header'],
                'normalized_header' =>
                    $candidate['normalized_header'],
                'confidence' => $candidate['confidence'],
                'matched_alias' => $candidate['matched_alias'],
                'match_type' => $candidate['match_type'],
                'requires_confirmation' =>
                    $candidate['confidence'] < 85,
            ];

            $assignedFields[$field] = true;
            $assignedColumns[$columnIndex] = true;
        }

        $unmappedColumns = array_values(
            array_filter(
                $headers,
                static fn (array $header): bool =>
                    $header['original'] !== ''
                    && ! isset(
                        $assignedColumns[
                            $header['column_index']
                        ]
                    )
            )
        );

        $matched = array_filter(
            $mapping,
            static fn ($value): bool =>
                is_array($value)
        );

        $score = array_sum(
            array_map(
                static fn (array $item): float =>
                    (float) $item['confidence'],
                $matched
            )
        );

        return [
            'mapping' => $mapping,
            'unmapped_columns' => $unmappedColumns,
            'score' => round($score, 2),
            'matched_fields' => count($matched),
        ];
    }
}