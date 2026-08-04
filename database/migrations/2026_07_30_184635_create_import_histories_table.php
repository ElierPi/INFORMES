<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Tipo de reporte
            |--------------------------------------------------------------------------
            */

            $table->string('report_type', 80)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Perfil detectado o seleccionado
            |--------------------------------------------------------------------------
            */

            $table->foreignId('import_profile_id')
                ->nullable()
                ->constrained('import_profiles')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Archivo original
            |--------------------------------------------------------------------------
            */

            $table->string('original_filename', 255);

            $table->string('original_path', 500)
                ->nullable();

            $table->string('file_hash', 64)
                ->nullable()
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Detección realizada
            |--------------------------------------------------------------------------
            */

            $table->string('sheet_name', 150)
                ->nullable();

            $table->unsignedInteger('header_row')
                ->nullable();

            $table->unsignedInteger('data_start_row')
                ->nullable();

            $table->unsignedInteger('records_count')
                ->default(0);

            $table->unsignedInteger('valid_records_count')
                ->default(0);

            $table->unsignedInteger('invalid_records_count')
                ->default(0);

            $table->unsignedInteger('warnings_count')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Estado del proceso
            |--------------------------------------------------------------------------
            |
            | uploaded
            | analyzed
            | mapping_required
            | validated
            | generated
            | failed
            |
            */

            $table->string('status', 40)
                ->default('uploaded')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Información estructurada
            |--------------------------------------------------------------------------
            */

            $table->json('mapping')
                ->nullable();

            $table->json('missing_fields')
                ->nullable();

            $table->json('warnings')
                ->nullable();

            $table->json('statistics')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Archivos generados
            |--------------------------------------------------------------------------
            */

            $table->string('txt_path', 500)
                ->nullable();

            $table->string('zip_path', 500)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Error técnico
            |--------------------------------------------------------------------------
            */

            $table->text('error_message')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_histories');
    }
};