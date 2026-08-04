<?php

namespace App\Services\Importing;

use App\Models\ImportProfile;

class ImportProfileResolver
{
    /**
     * Intenta identificar automáticamente la IPS usando
     * los primeros registros importados.
     */
    public function resolve(array $records): ?ImportProfile
    {
        if ($records === []) {
            return null;
        }

        $sample = array_slice($records, 0, 20);

        $providerNits = [];
        $providerCodes = [];
        $providerNames = [];

        foreach ($sample as $record) {
            $data = $record['data'] ?? [];

            $nit = $this->normalizeIdentifier(
                $data['provider_nit'] ?? null
            );

            $code = $this->normalizeIdentifier(
                $data['provider_code'] ?? null
            );

            $name = $this->normalizeText(
                $data['provider_name'] ?? null
            );

            if ($nit !== '') {
                $providerNits[] = $nit;
            }

            if ($code !== '') {
                $providerCodes[] = $code;
            }

            if ($name !== '') {
                $providerNames[] = $name;
            }
        }

        $providerNits = array_unique($providerNits);
        $providerCodes = array_unique($providerCodes);
        $providerNames = array_unique($providerNames);

        /*
        |--------------------------------------------------------------------------
        | Coincidencia por NIT
        |--------------------------------------------------------------------------
        */

        if ($providerNits !== []) {
            $profile = ImportProfile::query()
                ->where('active', true)
                ->whereIn('provider_nit', $providerNits)
                ->first();

            if ($profile !== null) {
                return $profile;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Coincidencia por código de prestador
        |--------------------------------------------------------------------------
        */

        if ($providerCodes !== []) {
            $profile = ImportProfile::query()
                ->where('active', true)
                ->whereIn('provider_code', $providerCodes)
                ->first();

            if ($profile !== null) {
                return $profile;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Coincidencia aproximada por nombre
        |--------------------------------------------------------------------------
        */

        if ($providerNames !== []) {
            $profiles = ImportProfile::query()
                ->where('active', true)
                ->whereNotNull('provider_name')
                ->get();

            foreach ($profiles as $profile) {
                $profileName = $this->normalizeText(
                    $profile->provider_name
                );

                foreach ($providerNames as $providerName) {
                    similar_text(
                        $providerName,
                        $profileName,
                        $percentage
                    );

                    if ($percentage >= 80) {
                        return $profile;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeIdentifier(mixed $value): string
    {
        return preg_replace(
            '/[^0-9]/',
            '',
            (string) $value
        ) ?? '';
    }

    private function normalizeText(mixed $value): string
    {
        $value = mb_strtoupper(
            trim((string) $value)
        );

        return preg_replace(
            '/\s+/',
            ' ',
            $value
        ) ?? $value;
    }
}