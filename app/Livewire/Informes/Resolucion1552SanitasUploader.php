<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1552\Resolucion1552SanitasExporter;
use App\Support\Livewire\HandlesTemporaryUploads;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

final class Resolucion1552SanitasUploader extends Component
{
    use HandlesTemporaryUploads;
    use WithFileUploads;

    public $archivo = null;
    public string $periodo = '';
    public bool $procesando = false;
    public bool $generado = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public array $resumen = [];
    public array $erroresValidacion = [];
    public array $advertencias = [];
    public ?string $txtPath = null;
    public ?string $txtName = null;
    public ?string $zipPath = null;
    public ?string $zipName = null;

    public function mount(): void
    {
        $this->periodo = now()->subMonth()->format('Y-m');
    }

    protected function rules(): array
    {
        return [
            'archivo' => ['required'],
            'periodo' => ['required', 'date_format:Y-m'],
        ];
    }

    public function generar(Resolucion1552SanitasExporter $exporter): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Procesando el archivo...';
        $this->limpiarResultado();

        try {
            $this->validateOnly('periodo');

            $folder = 'private/uploads/resolucion1552-sanitas/'.Str::uuid();
            $path = $this->stabilizeUpload(
                upload: $this->archivo,
                extensions: ['xlsx', 'xls'],
                maxBytes: 30 * 1024 * 1024,
                folder: $folder,
                baseName: 'entrada'
            );
            $result = $exporter->export($path, $this->periodo);
            $validation = $result['validation'] ?? [];
            $this->erroresValidacion = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            $this->advertencias = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];
            $statistics = is_array($validation['statistics'] ?? null) ? $validation['statistics'] : [];
            $this->resumen = [
                'registros' => (int) ($statistics['records_count'] ?? 0),
                'validos' => (int) ($statistics['valid_records_count'] ?? 0),
                'invalidos' => (int) ($statistics['invalid_records_count'] ?? 0),
                'errores' => (int) ($statistics['errors_count'] ?? 0),
                'advertencias' => (int) ($statistics['warnings_count'] ?? 0),
                'duplicados' => (int) ($statistics['duplicate_count'] ?? 0),
                'telefonos' => (int) ($statistics['phone_corrections_count'] ?? 0),
                'prestador' => (string) ($result['provider_code'] ?? ''),
            ];

            if (! ($result['success'] ?? false)) {
                $this->error = 'El Excel contiene errores que deben corregirse antes de generar el ZIP.';
                $this->mensaje = null;
                return;
            }

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->zipPath = (string) $result['zip_path'];
            $this->zipName = (string) $result['zip_name'];
            $this->generado = true;
            $this->mensaje = 'El TXT y el ZIP de la Resolución 1552 de Sanitas fueron generados correctamente.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible procesar el archivo: ' . $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarZip()
    {
        if (! is_string($this->zipPath) || $this->zipPath === '' || ! is_file($this->zipPath)) {
            $this->error = 'El ZIP ya no está disponible. Genera nuevamente el informe.';
            return null;
        }

        return response()->download($this->zipPath, $this->zipName ?: basename($this->zipPath), ['Content-Type' => 'application/zip']);
    }

    public function descargarTxt()
    {
        if (! is_string($this->txtPath) || $this->txtPath === '' || ! is_file($this->txtPath)) {
            $this->error = 'El TXT ya no está disponible. Genera nuevamente el informe.';
            return null;
        }

        return response()->download($this->txtPath, $this->txtName ?: basename($this->txtPath), ['Content-Type' => 'text/plain; charset=Windows-1252']);
    }

    public function reiniciar(): void
    {
        $this->reset(['archivo', 'procesando', 'generado', 'mensaje', 'error', 'resumen', 'erroresValidacion', 'advertencias', 'txtPath', 'txtName', 'zipPath', 'zipName']);
        $this->periodo = now()->subMonth()->format('Y-m');
        $this->resetValidation();
    }

    private function limpiarResultado(): void
    {
        $this->generado = false;
        $this->resumen = [];
        $this->erroresValidacion = [];
        $this->advertencias = [];
        $this->txtPath = null;
        $this->txtName = null;
        $this->zipPath = null;
        $this->zipName = null;
    }

    public function render()
    {
        return view('livewire.informes.resolucion1552-sanitas-uploader');
    }
}
