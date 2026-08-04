<?php

namespace App\Services\Importing;

class AliasResolver
{
    public function __construct(
        private readonly HeaderNormalizer $normalizer
    ) {
    }

    /**
     * Busca la mejor coincidencia entre un encabezado y sus alias.
     *
     * @param array<int, string> $aliases
     *
     * @return array{
     *     matched: bool,
     *     confidence: float,
     *     alias: string|null,
     *     match_type: string|null
     * }
     */
    public function resolve(
        string $header,
        array $aliases
    ): array {
        $normalizedHeader = $this->normalizer->normalize($header);

        if ($normalizedHeader === '') {
            return $this->emptyResult();
        }

        $best = $this->emptyResult();

        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizer->normalize($alias);

            if ($normalizedAlias === '') {
                continue;
            }

            $candidate = $this->compare(
                $normalizedHeader,
                $normalizedAlias
            );

            if ($candidate['confidence'] > $best['confidence']) {
                $best = [
                    ...$candidate,
                    'alias' => $alias,
                ];
            }
        }

        return $best;
    }

    /**
     * @return array{
     *     matched: bool,
     *     confidence: float,
     *     alias: null,
     *     match_type: string|null
     * }
     */
    private function compare(
        string $header,
        string $alias
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Coincidencia exacta
        |--------------------------------------------------------------------------
        */

        if ($header === $alias) {
            return [
                'matched' => true,
                'confidence' => 100.0,
                'alias' => null,
                'match_type' => 'exact',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | El encabezado contiene el alias completo
        |--------------------------------------------------------------------------
        */

        if (
            mb_strlen($alias) >= 6
            && str_contains($header, $alias)
        ) {
            $coverage = mb_strlen($alias) / max(
                mb_strlen($header),
                1
            );

            return [
                'matched' => true,
                'confidence' => round(
                    88 + ($coverage * 10),
                    2
                ),
                'alias' => null,
                'match_type' => 'contains_alias',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | El alias contiene el encabezado
        |--------------------------------------------------------------------------
        */

        if (
            mb_strlen($header) >= 6
            && str_contains($alias, $header)
        ) {
            $coverage = mb_strlen($header) / max(
                mb_strlen($alias),
                1
            );

            return [
                'matched' => true,
                'confidence' => round(
                    80 + ($coverage * 12),
                    2
                ),
                'alias' => null,
                'match_type' => 'contained_in_alias',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Similitud de palabras
        |--------------------------------------------------------------------------
        */

        $headerTokens = $this->tokens($header);
        $aliasTokens = $this->tokens($alias);

        if ($headerTokens === [] || $aliasTokens === []) {
            return $this->emptyResult();
        }

        $intersection = array_intersect(
            $headerTokens,
            $aliasTokens
        );

        $union = array_unique([
            ...$headerTokens,
            ...$aliasTokens,
        ]);

        $jaccard = count($intersection) / max(
            count($union),
            1
        );

        /*
         * Evita aceptar coincidencias demasiado ambiguas,
         * como relacionar "prestador" con cualquier campo
         * que incluya esa palabra.
         */
        if (
            count($intersection) < 2
            || $jaccard < 0.45
        ) {
            return $this->emptyResult();
        }

        $confidence = round(
            60 + ($jaccard * 30),
            2
        );

        return [
            'matched' => $confidence >= 72,
            'confidence' => $confidence,
            'alias' => null,
            'match_type' => 'token_similarity',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value): array
    {
        $ignored = [
            'de',
            'del',
            'la',
            'las',
            'el',
            'los',
            'para',
            'por',
            'en',
            'y',
            'o',
            'a',
            'que',
        ];

        $tokens = preg_split(
            '/\s+/',
            trim($value)
        ) ?: [];

        return array_values(
            array_unique(
                array_filter(
                    $tokens,
                    static fn (string $token): bool =>
                        mb_strlen($token) >= 2
                        && ! in_array(
                            $token,
                            $ignored,
                            true
                        )
                )
            )
        );
    }

    /**
     * @return array{
     *     matched: false,
     *     confidence: float,
     *     alias: null,
     *     match_type: null
     * }
     */
    private function emptyResult(): array
    {
        return [
            'matched' => false,
            'confidence' => 0.0,
            'alias' => null,
            'match_type' => null,
        ];
    }
}