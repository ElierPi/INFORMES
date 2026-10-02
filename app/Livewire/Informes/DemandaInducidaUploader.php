<?php

namespace App\Livewire\Informes;

use App\Services\DemandaInducida\DemandaInducidaPrestadorRepository;
use App\Services\DemandaInducida\DemandaInducidaErrorCorrectionService;
use App\Services\DemandaInducida\DemandaInducidaService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DemandaInducidaUploader extends Component
{
    use WithFileUploads;

    public $archivo;

    public string $prestadorSeleccionado =
        '444300063502';

    public string $codigoHabilitacion =
        '444300063502';

    public array $prestadores = [];

    public bool $mostrarNuevoPrestador = false;
    public string $nuevoPrestadorNombre = '';
    public string $nuevoPrestadorCodigo = '';

    public $zipRechazado;
    public $archivoErrores;
    public ?array $correccionResultado = null;
    public ?string $correccionErrorMessage = null;

    public ?string $errorMessage = null;
    public ?string $prestadorMessage = null;
    public ?array $resultado = null;

    public function mount(
        DemandaInducidaPrestadorRepository $repository
    ): void {
        $this->prestadores =
            $repository->all();

        if (
            ! collect($this->prestadores)
                ->contains(
                    fn ($item) =>
                        ($item['code'] ?? '')
                        === '444300063502'
                )
            && $this->prestadores !== []
        ) {
            $this->prestadorSeleccionado =
                (string) $this->prestadores[0]['code'];

            $this->codigoHabilitacion =
                $this->prestadorSeleccionado;
        }
    }

    public function updatedPrestadorSeleccionado(
        string $value
    ): void {
        if ($value === '__nuevo__') {
            $this->mostrarNuevoPrestador = true;
            return;
        }

        $this->mostrarNuevoPrestador = false;
        $this->codigoHabilitacion = $value;
    }

    public function guardarPrestador(
        DemandaInducidaPrestadorRepository $repository
    ): void {
        $this->validate([
            'nuevoPrestadorNombre' =>
                ['required','string','max:120'],
            'nuevoPrestadorCodigo' =>
                ['required','regex:/^\d{12}$/'],
        ]);

        try {
            $item = $repository->add(
                $this->nuevoPrestadorNombre,
                $this->nuevoPrestadorCodigo
            );

            $this->prestadores =
                $repository->all();

            $this->prestadorSeleccionado =
                $item['code'];

            $this->codigoHabilitacion =
                $item['code'];

            $this->mostrarNuevoPrestador = false;
            $this->nuevoPrestadorNombre = '';
            $this->nuevoPrestadorCodigo = '';

            $this->prestadorMessage =
                'IPS guardada correctamente.';
        } catch (Throwable $exception) {
            $this->addError(
                'nuevoPrestadorCodigo',
                $exception->getMessage()
            );
        }
    }

    public function procesar(
        DemandaInducidaService $service
    ): void {
        $this->reset([
            'errorMessage',
            'resultado',
        ]);

        $this->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:30720',
            ],
            'codigoHabilitacion' => [
                'required',
                'regex:/^\d{12}$/',
            ],
        ], [
            'archivo.required' =>
                'Selecciona el Excel de Demanda inducida.',
            'archivo.mimes' =>
                'El archivo debe estar en formato XLSX.',
        ]);

        try {
            $stored = $this->archivo->storeAs(
                'demanda-inducida/uploads',
                uniqid('demanda_', true).'.xlsx',
                'local'
            );

            $this->resultado =
                $service->process(
                    Storage::disk('local')
                        ->path($stored),
                    $this->codigoHabilitacion
                );
        } catch (Throwable $exception) {
            $this->errorMessage =
                'No fue posible preparar Demanda inducida: '
                .$exception->getMessage();
        }
    }

    public function descargarExcel(): ?BinaryFileResponse
    {
        if (
            ! $this->resultado
            || ! is_file(
                $this->resultado['xlsx_path']
                ?? ''
            )
        ) {
            $this->errorMessage =
                'Primero debes procesar un archivo.';

            return null;
        }

        return response()->download(
            $this->resultado['xlsx_path'],
            $this->resultado['xlsx_name']
        );
    }

    public function descargarZip(): ?BinaryFileResponse
    {
        if (
            ! $this->resultado
            || ! is_file(
                $this->resultado['zip_path']
                ?? ''
            )
        ) {
            $this->errorMessage =
                'Primero debes procesar un archivo.';

            return null;
        }

        return response()->download(
            $this->resultado['zip_path'],
            $this->resultado['zip_name']
        );
    }

    public function corregirDevolucion(
        DemandaInducidaErrorCorrectionService $service
    ): void {
        $this->reset([
            'correccionResultado',
            'correccionErrorMessage',
        ]);

        $this->validate([
            'zipRechazado' => [
                'required', 'file', 'mimes:zip', 'max:30720',
            ],
            'archivoErrores' => [
                'required', 'file', 'mimes:xls,xlsx', 'max:10240',
            ],
        ]);

        try {
            $id = uniqid('correccion_', true);

            $zipStored = $this->zipRechazado->storeAs(
                'demanda-inducida/correction-input',
                $id.'.zip',
                'local'
            );

            $ext = strtolower(
                $this->archivoErrores->getClientOriginalExtension()
            );

            $logStored = $this->archivoErrores->storeAs(
                'demanda-inducida/correction-input',
                $id.'.'.$ext,
                'local'
            );

            $this->correccionResultado = $service->correct(
                Storage::disk('local')->path($zipStored),
                Storage::disk('local')->path($logStored)
            );
        } catch (\Throwable $exception) {
            $this->correccionErrorMessage =
                'No fue posible corregir la devolución SIGIRES: '
                .$exception->getMessage();
        }
    }

    public function descargarZipCorregido(): ?BinaryFileResponse
    {
        if (
            ! $this->correccionResultado
            || ! is_file($this->correccionResultado['zip_path'] ?? '')
        ) {
            $this->correccionErrorMessage =
                'Primero debes analizar y corregir el ZIP.';
            return null;
        }

        return response()->download(
            $this->correccionResultado['zip_path'],
            $this->correccionResultado['zip_name']
        );
    }

    public function descargarExcelCorregidoDevolucion(): ?BinaryFileResponse
    {
        if (
            ! $this->correccionResultado
            || ! is_file($this->correccionResultado['xlsx_path'] ?? '')
        ) {
            $this->correccionErrorMessage =
                'Primero debes analizar y corregir el ZIP.';
            return null;
        }

        return response()->download(
            $this->correccionResultado['xlsx_path'],
            $this->correccionResultado['xlsx_name']
        );
    }

    public function nuevoProceso(): void
    {
        $this->reset([
            'archivo',
            'errorMessage',
            'resultado',
        ]);
    }

    public function render()
    {
        return view(
            'livewire.informes.demanda-inducida-uploader'
        );
    }
}
