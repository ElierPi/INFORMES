<?php

namespace App\Livewire\Informes;

use App\Services\Informe202\FamiliarColombia\FamiliarColombiaCorrectionService;
use App\Services\Informe202\FamiliarColombia\FamiliarColombiaErrorParser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FamiliarColombiaCorreccionUploader extends Component
{
    use WithFileUploads;

    public $archivoZip = null;

    public $archivoErrores = null;

    public bool $procesando = false;

    public bool $procesado = false;

    public ?string $mensaje = null;

    public ?string $errorProceso = null;

    public ?string $zipCorregidoPath = null;

    public ?string $zipCorregidoName = null;

    public array $metadata = [];

    public array $estadisticas = [];

    public array $correcciones = [];

    public array $pendientes = [];

    public function procesar(
        FamiliarColombiaErrorParser $parser,
        FamiliarColombiaCorrectionService $corrector
    ): void {
        $this->resetErrorBag();
        $this->limpiarResultados();
        $this->procesando = true;

        try {
            $this->validate([
                'archivoZip' => [
                    'required',
                    'file',
                    'max:102400',
                ],

                'archivoErrores' => [
                    'required',
                    'file',
                    'max:51200',
                ],
            ], [
                'archivoZip.required' =>
                    'Selecciona el ZIP original cargado.',

                'archivoErrores.required' =>
                    'Selecciona el Excel de errores.',

                'archivoZip.max' =>
                    'El ZIP no puede superar 100 MB.',

                'archivoErrores.max' =>
                    'El Excel de errores no puede superar 50 MB.',
            ]);

            $zipExtension = mb_strtolower(
                pathinfo(
                    $this->archivoZip
                        ->getClientOriginalName(),
                    PATHINFO_EXTENSION
                )
            );

            $errorExtension = mb_strtolower(
                pathinfo(
                    $this->archivoErrores
                        ->getClientOriginalName(),
                    PATHINFO_EXTENSION
                )
            );

            if ($zipExtension !== 'zip') {
                throw new RuntimeException(
                    'El archivo original debe ser ZIP.'
                );
            }

            if (! in_array(
                $errorExtension,
                ['xls', 'xlsx'],
                true
            )) {
                throw new RuntimeException(
                    'El reporte de errores debe ser XLS o XLSX.'
                );
            }

            $folder =
                'informes-202/familiar-colombia-correccion/'
                . Str::uuid();

            Storage::disk('local')
                ->makeDirectory($folder);

            $originalZipName =
                $this->archivoZip
                    ->getClientOriginalName();

            $zipRelative = $this->archivoZip->storeAs(
                $folder,
                'original.zip',
                'local'
            );

            $errorsRelative =
                $this->archivoErrores->storeAs(
                    $folder,
                    'errores.' . $errorExtension,
                    'local'
                );

            if (! $zipRelative || ! $errorsRelative) {
                throw new RuntimeException(
                    'No fue posible guardar los archivos cargados.'
                );
            }

            $parsed = $parser->parse(
                Storage::disk('local')
                    ->path($errorsRelative)
            );

            $result = $corrector->correct(
                inputZip: Storage::disk('local')
                    ->path($zipRelative),

                errors: $parsed['errors'],

                outputDirectory:
                    Storage::disk('local')
                        ->path($folder . '/salida'),

                outputBaseName:
                    pathinfo(
                        $originalZipName,
                        PATHINFO_FILENAME
                    )
            );

            $this->metadata =
                $parsed['metadata'];

            $this->estadisticas =
                $result['statistics'];

            $this->correcciones =
                array_slice(
                    $result['corrections'],
                    0,
                    500
                );

            $this->pendientes =
                $result['unresolved'];

            /*
             * Conserva exactamente el nombre exigido por SIGIRES,
             * sin agregar sufijos como _CORREGIDO.
             */
            $this->zipCorregidoName =
                pathinfo(
                    $originalZipName,
                    PATHINFO_FILENAME
                )
                . '.zip';

            $this->zipCorregidoPath =
                $result['output_zip'];

            $this->procesado = true;

            $this->mensaje =
                $this->pendientes === []
                    ? 'El ZIP fue corregido completamente y está listo para descargar.'
                    : 'El ZIP fue generado. Algunas reglas quedaron pendientes de revisión manual.';
        } catch (Throwable $exception) {
            report($exception);

            $this->errorProceso =
                'No fue posible corregir el archivo: '
                . $exception->getMessage();
        } finally {
            $this->procesando = false;
        }
    }

    public function descargar(): BinaryFileResponse
    {
        if (
            $this->zipCorregidoPath === null
            || ! is_file($this->zipCorregidoPath)
        ) {
            throw new RuntimeException(
                'No se encontró el ZIP corregido.'
            );
        }

        return response()->download(
            $this->zipCorregidoPath,
            $this->zipCorregidoName
                ?? 'RESOLUCION_202_CORREGIDO.zip',
            [
                'Content-Type' => 'application/zip',
            ]
        );
    }

    private function limpiarResultados(): void
    {
        $this->procesado = false;
        $this->mensaje = null;
        $this->errorProceso = null;
        $this->zipCorregidoPath = null;
        $this->zipCorregidoName = null;
        $this->metadata = [];
        $this->estadisticas = [];
        $this->correcciones = [];
        $this->pendientes = [];
    }

    public function render()
    {
        return view(
            'livewire.informes.familiar-colombia-correccion-uploader'
        );
    }
}
