<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1604\Dusakawi\Resolucion1604DusakawiExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1604DusakawiUploader extends Component
{
    use WithFileUploads;

    public $archivo = null;
    public string $periodo = '2026-07';
    public bool $procesando = false;
    public bool $generado = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public array $resumen = [];
    public array $erroresValidacion = [];
    public array $advertencias = [];
    public ?string $txtPath = null;
    public ?string $txtName = null;
    public ?string $lineaControl = null;

    public function generar(Resolucion1604DusakawiExporter $exporter): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Procesando el Excel de DUSAKAWI...';
        $this->limpiarResultado();

        try {
            $this->validate([
                'archivo' => ['required'],
                'periodo' => ['required', 'date_format:Y-m'],
            ]);

            if (! $this->archivo instanceof TemporaryUploadedFile) {
                throw new RuntimeException('Selecciona nuevamente el archivo Excel.');
            }

            $temporaryPath = $this->archivo->getRealPath();
            if (! is_string($temporaryPath) || ! is_file($temporaryPath)) {
                throw new RuntimeException('El archivo temporal expiró. Selecciona nuevamente el Excel.');
            }

            $extension = strtolower($this->archivo->getClientOriginalExtension());
            if (! in_array($extension, ['xlsx', 'xls'], true)) {
                throw new RuntimeException('El archivo debe ser Excel (.xlsx o .xls).');
            }
            if (filesize($temporaryPath) > 30 * 1024 * 1024) {
                throw new RuntimeException('El archivo supera el tamaño máximo permitido de 30 MB.');
            }

            $folder = 'private/uploads/resolucion1604-dusakawi/'.Str::uuid();
            Storage::disk('local')->makeDirectory($folder);
            $relativePath = $this->archivo->storeAs($folder, 'entrada.'.$extension, 'local');
            $stablePath = Storage::disk('local')->path((string) $relativePath);

            $result = $exporter->export($stablePath, $this->periodo);
            $validation = $result['validation'] ?? [];
            $statistics = is_array($validation['statistics'] ?? null) ? $validation['statistics'] : [];
            $this->erroresValidacion = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            $this->advertencias = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];

            $this->resumen = [
                'registros' => (int) ($statistics['records_count'] ?? 0),
                'validos' => (int) ($statistics['valid_records_count'] ?? 0),
                'invalidos' => (int) ($statistics['invalid_records_count'] ?? 0),
                'errores' => (int) ($statistics['errors_count'] ?? 0),
                'advertencias' => (int) ($statistics['warnings_count'] ?? 0),
            ];

            if (! ($result['success'] ?? false)) {
                $this->error = 'El Excel contiene errores estructurales que impiden generar el TXT.';
                $this->mensaje = null;
                return;
            }

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->lineaControl = (string) $result['control_line'];
            $this->generado = true;
            $this->mensaje = 'TXT 1604 DUSAKAWI generado: línea de control + 25 campos por registro.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible preparar el informe: '.$exception->getMessage();
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
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public function reiniciar(): void
    {
        $this->reset(['archivo', 'procesando', 'generado', 'mensaje', 'error', 'resumen', 'erroresValidacion', 'advertencias', 'txtPath', 'txtName', 'lineaControl']);
        $this->periodo = '2026-07';
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
        $this->lineaControl = null;
    }

    public function render()
    {
        return view('livewire.informes.resolucion1604-dusakawi-uploader');
    }
}
