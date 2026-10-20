<?php

namespace App\Livewire\Informes;

use App\Services\Reports\Excel\ConfigurableExcelReader;
use App\Services\Resolucion0256\Resolucion0256ValidationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class Resolucion0256Uploader extends Component
{
    use WithFileUploads;

    public $archivo = null;

    #[Locked]
    public array $errores = [];

    #[Locked]
    public array $resumen = [];

    #[Locked]
    public bool $analizado = false;

    #[Locked]
    public int $inputVersion = 0;

    public function updatedArchivo(): void
    {
        $this->reset(['errores', 'resumen', 'analizado']);
        $this->resetValidation();
    }

    public function analizar(
        ConfigurableExcelReader $reader,
        Resolucion0256ValidationService $validator
    ): void {
        abort_unless(auth()->check(), 403);

        $this->updatedArchivo();

        if ($this->archivo instanceof TemporaryUploadedFile) {
            clearstatcache(true, $this->archivo->getRealPath());
        }

        if ($this->archivo !== null && (
            ! $this->archivo instanceof TemporaryUploadedFile
            || ! is_file($this->archivo->getRealPath())
        )) {
            $this->addError('archivo', 'El archivo temporal ya no está disponible. Selecciona el archivo nuevamente.');

            return;
        }

        $this->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'extensions:xlsx,xls',
                'max:30720',
            ],
        ], [
            'archivo.required' => 'Debe seleccionar un archivo.',
            'archivo.file' => 'El archivo seleccionado no es válido.',
            'archivo.mimes' => 'El archivo debe ser Excel.',
            'archivo.extensions' => 'El archivo debe tener extensión XLSX o XLS.',
            'archivo.max' => 'El archivo no puede superar los 30 MB.',
        ]);

        try {
            $data = $reader->read(
                path: $this->archivo->getRealPath(),
                configKey: 'resolucion0256'
            );

            $result = $validator->validate($data);

            $this->errores = $result['errors'] ?? [];
            $this->resumen = $result['summary'] ?? [];
            $this->analizado = true;
        } catch (InvalidArgumentException $exception) {
            $this->addError('archivo', $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('No fue posible analizar el Excel de Resolución 0256.', [
                'exception_class' => $exception::class,
            ]);

            $this->addError('archivo', 'No fue posible analizar el archivo Excel. Verifica que no esté dañado e inténtalo nuevamente.');
        }
    }

    public function limpiar(): void
    {
        $this->reset([
            'archivo',
            'errores',
            'resumen',
            'analizado',
        ]);

        $this->resetValidation();
        $this->inputVersion++;
    }

    public function render(): View
    {
        return view('livewire.informes.resolucion0256-uploader');
    }
}
