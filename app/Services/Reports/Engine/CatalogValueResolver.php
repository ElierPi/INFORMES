<?php

namespace App\Services\Reports\Engine;

final class CatalogValueResolver
{
    public function resolve(string $catalogName, mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $catalog = config($catalogName, []);

        if (! is_array($catalog) || $catalog === []) {
            return null;
        }

        foreach ($catalog as $key => $item) {
            if (! is_array($item)) {
                if (
                    $this->equals($value, $key) ||
                    $this->equals($value, $item)
                ) {
                    return (string) $key;
                }

                continue;
            }

            $code = $item['code']
                ?? $item['codigo']
                ?? $item['value']
                ?? $key;

            $label = $item['name']
                ?? $item['nombre']
                ?? $item['label']
                ?? $item['description']
                ?? null;

            if ($this->equals($value, $code)) {
                return (string) $code;
            }

            if ($label !== null && $this->equals($value, $label)) {
                return (string) $code;
            }

            foreach (($item['aliases'] ?? []) as $alias) {
                if ($this->equals($value, $alias)) {
                    return (string) $code;
                }
            }
        }

        return null;
    }

    private function equals(mixed $left, mixed $right): bool
    {
        return $this->normalize($left) === $this->normalize($right);
    }

    private function normalize(mixed $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');

        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}