<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_aliases', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Contexto del alias
            |--------------------------------------------------------------------------
            |
            | report_type permite utilizar el mismo motor para:
            | - resolucion_1552
            | - resolucion_202
            | - resolucion_0256
            | - rips
            | - cac
            |
            */

            $table->string('report_type', 80)
                ->default('universal')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Campo interno
            |--------------------------------------------------------------------------
            |
            | Ejemplos:
            | provider_code
            | document_type
            | appointment_date
            |
            */

            $table->string('internal_field', 100)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Encabezado original y normalizado
            |--------------------------------------------------------------------------
            */

            $table->string('original_header', 255);

            $table->string('normalized_header', 255)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Origen
            |--------------------------------------------------------------------------
            |
            | system  = definido por programación
            | learned = aprendido mediante selección del usuario
            | imported = cargado desde una configuración
            |
            */

            $table->string('source', 30)
                ->default('learned');

            /*
            |--------------------------------------------------------------------------
            | Estado de aprobación
            |--------------------------------------------------------------------------
            */

            $table->boolean('approved')
                ->default(true)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Métricas de uso
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('times_used')
                ->default(0);

            $table->decimal('average_confidence', 5, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | IPS asociada
            |--------------------------------------------------------------------------
            |
            | Si es null, el alias será global.
            | Si tiene valor, será prioritario para esa IPS.
            |
            */

            $table->foreignId('import_profile_id')
                ->nullable()
                ->constrained('import_profiles')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                [
                    'report_type',
                    'internal_field',
                    'normalized_header',
                    'import_profile_id',
                ],
                'import_alias_unique_mapping'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_aliases');
    }
};