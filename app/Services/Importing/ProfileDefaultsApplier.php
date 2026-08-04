<?php

namespace App\Services\Importing;

use App\Models\ImportProfile;

class ProfileDefaultsApplier
{
    /**
     * @param array<int, array<string, mixed>> $records
     *
     * @return array<int, array<string, mixed>>
     */
    public function apply(
        array $records,
        ?ImportProfile $profile
    ): array {
        if ($profile === null) {
            return $records;
        }

        foreach ($records as $index => $record) {
            $data = $record['data'] ?? [];

            if ($this->isEmpty($data['regime'] ?? null)) {
                $data['regime'] = $profile->default_regime;
            }

            if ($this->isEmpty($data['phone'] ?? null)) {
                $data['phone'] = $profile->default_phone;
            }

            if (
                $this->isEmpty(
                    $data['municipality_code'] ?? null
                )
            ) {
                $data['municipality_code'] =
                    $profile->default_municipality_code;
            }

            $records[$index]['data'] = $data;
        }

        return $records;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null
            || trim((string) $value) === '';
    }
}