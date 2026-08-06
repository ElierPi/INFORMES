<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1552\Resolucion1552FamiliarExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1552FamiliarUploader extends Component
{
    use WithFileUploads;

    public $archivo = null;
    public string $fechaReporte = '';
    public bool $procesando = false;
    public bool $generado = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public array $resumen = [];
    public array $erroresValidacion = [];
    public array $advertencias = [];
    public ?string $txtPath = null;
    public ?string $txtName = null;

    public function mount(): void
    {
        $this->fechaReporte = now()->startOfMonth()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:30720'],
            'fechaReporte' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function generar(Resolucion1552FamiliarExporter $exporter): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Procesando el archivo...';
        $this->limpiarResultado();

        try {
            $this->validate();

            if (! $this->archivo instanceof TemporaryUploadedFile) {
                throw new RuntimeException('Selecciona nuevamente el archivo Excel.');
            }

            $extension = strtolower($this->archivo->getClientOriginalExtension());
            $folder = 'private/uploads/resolucion1552-familiar/' . Str::uuid();
            Storage::disk('local')->makeDirectory($folder);
            $relativePath = $this->archivo->storeAs($folder, 'entrada.' . $extension, 'local');
            $path = Storage::disk('local')->path((string) $relativePath);
            $result = $exporter->export($path, $this->fechaReporte);
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
                'prestador' => (string) ($result['provider_code'] ?? ''),
            ];

            if (! ($result['success'] ?? false)) {
                $this->error = 'El Excel contiene errores que deben corregirse antes de generar el TXT.';
                $this->mensaje = null;
                return;
            }

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->generado = true;
            $this->mensaje = 'El TXT de Familiar de Colombia fue generado correctamente.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible procesar el archivo: ' . $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarTxt()
    {
        if (! $this->generado || ! is_string($this->txtPath) || ! is_file($this->txtPath)) {
            $this->error = 'El TXT ya no está disponible. Genera nuevamente el informe.';
            return null;
        }

        return response()->download(
            $this->txtPath,
            $this->txtName ?: basename($this->txtPath),
            ['Content-Type' => 'text/plain; charset=Windows-1252']
        );
    }

    public function reiniciar(): void
    {
        $this->reset(['archivo', 'procesando', 'generado', 'mensaje', 'error', 'resumen', 'erroresValidacion', 'advertencias', 'txtPath', 'txtName']);
        $this->fechaReporte = now()->startOfMonth()->format('Y-m-d');
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
    }

    public function render()
    {
        return view('livewire.informes.resolucion1552-familiar-uploader');
    }
}
