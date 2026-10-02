<?php

namespace App\Livewire\Informes;

use App\Services\GestanteMensualSigiresService;
use App\Services\GestantePrestadorRepository;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GestanteMensualUploader extends Component
{
    use WithFileUploads;

    public $archivo;
    public string $periodo = '';
    public string $codigoHabilitacion = '444300063502';
    public string $prestadorSeleccionado = '444300063502';
    public array $prestadores = [];
    public bool $mostrarNuevoPrestador = false;
    public string $nuevoPrestadorNombre = '';
    public string $nuevoPrestadorCodigo = '';
    public ?string $prestadorMessage = null;
    public ?string $errorMessage = null;
    public ?array $resultado = null;

    public function mount(GestantePrestadorRepository $repository): void
    {
        $this->periodo = now()->subMonthNoOverflow()->format('Y-m');
        $this->cargarPrestadores($repository);
        if (collect($this->prestadores)->contains(fn ($p) => ($p['code'] ?? '') === '444300063502')) {
            $this->prestadorSeleccionado = '444300063502';
            $this->codigoHabilitacion = '444300063502';
        } elseif (!empty($this->prestadores)) {
            $this->prestadorSeleccionado = (string) $this->prestadores[0]['code'];
            $this->codigoHabilitacion = $this->prestadorSeleccionado;
        }
    }

    public function updatedPrestadorSeleccionado(string $value): void
    {
        if ($value === '__nuevo__') {
            $this->mostrarNuevoPrestador = true;
            return;
        }
        $this->mostrarNuevoPrestador = false;
        $this->codigoHabilitacion = $value;
        $this->prestadorMessage = null;
    }

    public function abrirNuevoPrestador(): void
    {
        $this->mostrarNuevoPrestador = true;
        $this->prestadorSeleccionado = '__nuevo__';
        $this->prestadorMessage = null;
    }

    public function cancelarNuevoPrestador(): void
    {
        $this->mostrarNuevoPrestador = false;
        $this->nuevoPrestadorNombre = '';
        $this->nuevoPrestadorCodigo = '';
        $this->prestadorSeleccionado = $this->codigoHabilitacion ?: '444300063502';
    }

    public function guardarPrestador(GestantePrestadorRepository $repository): void
    {
        $this->resetValidation(['nuevoPrestadorNombre', 'nuevoPrestadorCodigo']);
        $this->prestadorMessage = null;
        $this->validate([
            'nuevoPrestadorNombre' => ['required', 'string', 'max:120'],
            'nuevoPrestadorCodigo' => ['required', 'regex:/^\d{12}$/'],
        ], [
            'nuevoPrestadorNombre.required' => 'Escriba el nombre de la IPS/prestador.',
            'nuevoPrestadorCodigo.regex' => 'El código debe tener exactamente 12 dígitos.',
        ]);

        try {
            $item = $repository->add($this->nuevoPrestadorNombre, $this->nuevoPrestadorCodigo);
            $this->cargarPrestadores($repository);
            $this->prestadorSeleccionado = $item['code'];
            $this->codigoHabilitacion = $item['code'];
            $this->mostrarNuevoPrestador = false;
            $this->nuevoPrestadorNombre = '';
            $this->nuevoPrestadorCodigo = '';
            $this->prestadorMessage = 'Prestador guardado correctamente.';
        } catch (\Throwable $e) {
            $this->addError('nuevoPrestadorCodigo', $e->getMessage());
        }
    }

    public function procesar(GestanteMensualSigiresService $service, GestantePrestadorRepository $repository): void
    {
        $this->reset(['errorMessage', 'resultado']);
        if ($this->prestadorSeleccionado === '__nuevo__') {
            $this->errorMessage = 'Guarde o seleccione una IPS antes de preparar el archivo.';
            return;
        }
        $selected = $repository->findByCode($this->prestadorSeleccionado);
        if ($selected) $this->codigoHabilitacion = $selected['code'];

        $this->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:30720'],
            'periodo' => ['required', 'date_format:Y-m'],
            'codigoHabilitacion' => ['required', 'regex:/^\d{12}$/'],
        ], [
            'archivo.required' => 'Seleccione el Excel de gestantes.',
            'archivo.mimes' => 'El archivo debe ser XLSX.',
            'codigoHabilitacion.regex' => 'El código de habilitación debe tener exactamente 12 dígitos.',
        ]);

        try {
            $stored = $this->archivo->storeAs('gestante-mensual/entradas', uniqid('gestante_', true).'.xlsx', 'local');
            $inputPath = Storage::disk('local')->path($stored);
            $this->resultado = $service->preparar($inputPath, $this->periodo, $this->codigoHabilitacion);
            if ($selected) $this->resultado['prestador'] = $selected['name'];
        } catch (\Throwable $e) {
            $this->errorMessage = 'No fue posible preparar el informe mensual de gestantes: '.$e->getMessage();
        }
    }

    public function descargar(): ?BinaryFileResponse
    {
        if (!$this->resultado || empty($this->resultado['zip_path']) || !is_file($this->resultado['zip_path'])) {
            $this->errorMessage = 'Primero debe procesar un archivo válido.';
            return null;
        }
        return response()->download($this->resultado['zip_path'], $this->resultado['download_name']);
    }

    public function nuevoProceso(): void
    {
        $this->reset(['archivo', 'errorMessage', 'resultado']);
    }

    public function render()
    {
        return view('livewire.informes.gestante-mensual-uploader');
    }

    private function cargarPrestadores(GestantePrestadorRepository $repository): void
    {
        $this->prestadores = $repository->all();
    }
}
