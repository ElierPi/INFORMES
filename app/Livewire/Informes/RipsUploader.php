<?php

namespace App\Livewire\Informes;

use App\Services\Rips\RipsFamiliarMonthlyGenerator;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RipsUploader extends Component
{
    use WithFileUploads;

    public $archivoFuente;

    /**
     * Copia estable del archivo fuente.
     * Después de guardarlo ya no dependemos de livewire-tmp.
     */
    public ?string $archivoFuentePath = null;

    public ?string $archivoFuenteNombre = null;

    public string $periodo = '2025-06';

    public ?array $resultado = null;
    public ?array $resultadoLote = null;
    public ?string $errorMessage = null;

    public function updatedArchivoFuente(): void
    {
        $this->resetValidation();
        $this->errorMessage = null;
        $this->resultado = null;
        $this->resultadoLote = null;

        if (! $this->archivoFuente) {
            $this->archivoFuentePath = null;
            $this->archivoFuenteNombre = null;

            return;
        }

        try {
            /*
             * IMPORTANTE:
             * No usamos reglas file/max/mimes aquí porque esas reglas llaman
             * file_size() sobre livewire-tmp y era justo lo que estaba fallando.
             *
             * Validamos la extensión por el nombre original y guardamos
             * inmediatamente una copia permanente.
             */
            $originalName =
                $this->archivoFuente->getClientOriginalName();

            $extension = mb_strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

            if (! in_array(
                $extension,
                ['xlsx', 'xls'],
                true
            )) {
                $this->addError(
                    'archivoFuente',
                    'El archivo fuente debe ser Excel (.xlsx o .xls).'
                );

                $this->archivoFuentePath = null;
                $this->archivoFuenteNombre = null;

                return;
            }

            $stableName =
                'rips_fuente_'
                .now()->format('Ymd_His_u')
                .'.'
                .$extension;

            /*
             * storeAs crea una copia estable en el disco local.
             */
            $stored =
                $this->archivoFuente->storeAs(
                    'rips/familiar/uploads',
                    $stableName,
                    'local'
                );

            if (
                ! is_string($stored)
                || $stored === ''
                || ! Storage::disk('local')->exists(
                    $stored
                )
            ) {
                throw new \RuntimeException(
                    'No fue posible guardar una copia estable del Excel.'
                );
            }

            $this->archivoFuentePath = $stored;
            $this->archivoFuenteNombre = $originalName;
        } catch (Throwable $exception) {
            report($exception);

            $this->archivoFuentePath = null;
            $this->archivoFuenteNombre = null;

            $this->addError(
                'archivoFuente',
                'No fue posible guardar el Excel cargado: '
                .$exception->getMessage()
            );
        }
    }

    public function generarMes(
        RipsFamiliarMonthlyGenerator $generator
    ): void {
        $this->reset([
            'resultado',
            'resultadoLote',
            'errorMessage',
        ]);

        $this->validate([
            /*
             * Ya NO validamos archivoFuente como file/max/mimes.
             * Solo verificamos que exista la copia estable.
             */
            'periodo' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        try {
            $sourcePath =
                $this->getStableSourcePath();

            $this->resultado =
                $generator->generateMonth(
                    $sourcePath,
                    $this->periodo
                );
        } catch (Throwable $exception) {
            report($exception);

            $this->errorMessage =
                'No fue posible generar el RIPS: '
                .$exception->getMessage();
        }
    }

    public function generarJunioDiciembre(
        RipsFamiliarMonthlyGenerator $generator
    ): void {
        $this->reset([
            'resultado',
            'resultadoLote',
            'errorMessage',
        ]);

        try {
            $sourcePath =
                $this->getStableSourcePath();

            $year = (int) substr(
                $this->periodo,
                0,
                4
            );

            $this->resultadoLote =
                $generator->generateJuneToDecember(
                    $sourcePath,
                    $year
                );
        } catch (Throwable $exception) {
            report($exception);

            $this->errorMessage =
                'No fue posible generar los meses: '
                .$exception->getMessage();
        }
    }

    /**
     * Descarga el ZIP mensual con:
     * - Morbilidad
     * - PYM
     * - Procedimientos
     */
    public function descargarMes(): ?BinaryFileResponse
    {
        if (
            ! $this->resultado
            || ! is_file(
                $this->resultado['zip_path']
                ?? ''
            )
        ) {
            $this->errorMessage =
                'Primero genera los archivos del mes.';

            return null;
        }

        return response()->download(
            $this->resultado['zip_path'],
            $this->resultado['zip_name']
        );
    }

    public function descargarArchivoMes(
        string $tipo
    ): ?BinaryFileResponse {
        $tipo = mb_strtoupper(
            trim($tipo)
        );

        $file =
            $this->resultado['files'][$tipo]
            ?? null;

        if (
            ! is_array($file)
            || ! is_file(
                $file['path'] ?? ''
            )
        ) {
            $this->errorMessage =
                'No se encontró el archivo '
                .$tipo.'.';

            return null;
        }

        return response()->download(
            $file['path'],
            $file['name']
        );
    }

    public function descargarLote(): ?BinaryFileResponse
    {
        if (
            ! $this->resultadoLote
            || ! is_file(
                $this->resultadoLote['zip_path']
                ?? ''
            )
        ) {
            $this->errorMessage =
                'Primero genera junio a diciembre.';

            return null;
        }

        return response()->download(
            $this->resultadoLote['zip_path'],
            $this->resultadoLote['zip_name']
        );
    }

    public function nuevoProceso(): void
    {
        $this->reset([
            'archivoFuente',
            'archivoFuentePath',
            'archivoFuenteNombre',
            'resultado',
            'resultadoLote',
            'errorMessage',
        ]);

        $this->resetValidation();
    }

    private function getStableSourcePath(): string
    {
        if (
            ! is_string($this->archivoFuentePath)
            || $this->archivoFuentePath === ''
            || ! Storage::disk('local')->exists(
                $this->archivoFuentePath
            )
        ) {
            throw new \RuntimeException(
                'El Excel fuente no está disponible. Selecciónalo nuevamente y espera a que termine de cargar.'
            );
        }

        $absolutePath =
            Storage::disk('local')->path(
                $this->archivoFuentePath
            );

        if (! is_file($absolutePath)) {
            throw new \RuntimeException(
                'No se encontró la copia estable del Excel fuente.'
            );
        }

        return $absolutePath;
    }

    public function render()
    {
        return view(
            'livewire.informes.rips-uploader'
        );
    }
}
