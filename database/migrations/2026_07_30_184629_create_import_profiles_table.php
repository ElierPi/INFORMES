<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_profiles', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Identificación del perfil
            |--------------------------------------------------------------------------
            */

            $table->string('name', 150);

            $table->string('slug', 170)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Datos de la IPS
            |--------------------------------------------------------------------------
            */

            $table->string('provider_nit', 30)
                ->nullable()
                ->index();

            $table->string('provider_code', 30)
                ->nullable()
                ->index();

            $table->string('provider_name', 255)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Configuración predeterminada
            |--------------------------------------------------------------------------
            */

            $table->string('default_regime', 30)
                ->nullable();

            $table->string('default_phone', 30)
                ->nullable();

            $table->string('default_municipality_code', 10)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Configuración flexible
            |--------------------------------------------------------------------------
            |
            | Aquí podremos guardar:
            | - nombres de hojas frecuentes
            | - fila usual de encabezados
            | - formatos de fecha
            | - reglas particulares
            | - equivalencias adicionales
            |
            */

            $table->json('settings')
                ->nullable();

            $table->boolean('active')
                ->default(true)
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_profiles');
    }
};