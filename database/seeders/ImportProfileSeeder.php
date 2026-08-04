<?php

namespace Database\Seeders;

use App\Models\ImportProfile;
use Illuminate\Database\Seeder;

class ImportProfileSeeder extends Seeder
{
    public function run(): void
    {
        ImportProfile::updateOrCreate(
            [
                'slug' => 'cidsma',
            ],
            [
                'name' => 'CIDSMA',
                'provider_nit' => '901533697',
                'provider_code' => '444300120001',
                'provider_name' =>
                    'CENTRO INTEGRAL DE SALUD DE MAICAO',
                'default_regime' => 'S',
                'default_phone' => null,
                'default_municipality_code' => '44430',
                'settings' => [
                    'regime_strategy' => 'default',
                    'sheet_names' => [
                        'Hoja1',
                    ],
                    'usual_header_row' => 5,
                    'date_format' => 'd/m/Y',
                ],
                'active' => true,
            ]
        );

        ImportProfile::updateOrCreate(
            [
                'slug' => 'anashii',
            ],
            [
                'name' => 'ANASHII',
                'provider_nit' => null,
                'provider_code' => null,
                'provider_name' => 'ANASHII',
                'default_regime' => null,
                'default_phone' => null,
                'default_municipality_code' => null,
                'settings' => [
                    'regime_strategy' => 'column_or_user_selection',
                    'date_format' => 'd/m/Y',
                ],
                'active' => true,
            ]
        );
    }
}