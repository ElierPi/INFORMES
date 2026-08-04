<?php

namespace App\Livewire\Informes;

use App\Services\Reports\Excel\ConfigurableExcelReader;
use App\Services\Resolucion0256\Resolucion0256ValidationService;
use Livewire\Component;
use Livewire\WithFileUploads;

class Resolucion0256Uploader extends Component
{
    use WithFileUploads;

    public $archivo;

    public array $errores = [];

    public array $resumen = [];

    public bool $analizado = false;

    public function analizar(
        ConfigurableExcelReader $reader,
        Resolucion0256ValidationService $validator
    ): void {
        $this->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:30720',
            ],
        ], [
            'archivo.required' => 'Debe seleccionar un archivo.',
            'archivo.mimes' => 'El archivo debe ser Excel.',
            'archivo.max' => 'El archivo no puede superar los 30 MB.',
        ]);

        $path = $this->archivo->getRealPath();

        $data = $reader->read(
            path: $path,
            configKey: 'resolucion0256'
        );

        $result = $validator->validate($data);

        $this->errores = $result['errors'] ?? [];
        $this->resumen = $result['summary'] ?? [];
        $this->analizado = true;
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
    }

    public function render()
    {
        return view('livewire.informes.resolucion0256-uploader');
    }
}