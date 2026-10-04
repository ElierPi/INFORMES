<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1604\FamiliarColombia\Resolucion1604FamiliarExporter;
use App\Support\Livewire\HandlesTemporaryUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

final class Resolucion1604FamiliarUploader extends Component
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
    public ?string $hoja = null;

    public function mount(): void
    {
        $this->periodo = now()->subMonthNoOverflow()->format('Y-m');
    }

    protected function rules(): array
    {
        // Extensión y tamaño se validan manualmente después de confirmar que
        // el archivo temporal de Livewire todavía existe.
        return [
            'archivo' => ['required'],
            'periodo' => ['required', 'date_format:Y-m'],
        ];
    }

    public function generar(Resolucion1604FamiliarExporter $exporter): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Procesando el archivo...';
        $this->limpiarResultado();

        try {
            $this->validateOnly('periodo');

            $folder = 'uploads/resolucion1604-familiar/'.Str::uuid();
            $stablePath = $this->stabilizeUpload(
                upload: $this->archivo,
                extensions: ['xlsx', 'xls'],
                maxBytes: 30 * 1024 * 1024,
                folder: $folder,
                baseName: 'entrada'
            );

            $result = $exporter->export($stablePath, $this->periodo);
            $validation = $result['validation'] ?? [];
            $this->erroresValidacion = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            $this->advertencias = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];
            $statistics = is_array($validation['statistics'] ?? null) ? $validation['statistics'] : [];
            $this->hoja = (string) ($result['sheet_name'] ?? '');

            $this->resumen = [
                'registros' => (int) ($statistics['records_count'] ?? 0),
                'validos' => (int) ($statistics['valid_records_count'] ?? 0),
                'invalidos' => (int) ($statistics['invalid_records_count'] ?? 0),
                'errores' => (int) ($statistics['errors_count'] ?? 0),
                'advertencias' => (int) ($statistics['warnings_count'] ?? 0),
                'correcciones' => (int) ($statistics['auto_corrections_count'] ?? 0),
            ];

            if (! ($result['success'] ?? false)) {
                $this->error = 'El Excel contiene errores estructurales que deben corregirse antes de generar el TXT.';
                $this->mensaje = null;
                return;
            }

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->generado = true;
            $this->mensaje = 'El TXT de la Resolución 1604 fue generado correctamente con 39 campos.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible procesar el archivo: '.$exception->getMessage();
            $this->mensaje = null;
        } finally {
            if (isset($folder)) {
                Storage::disk('local')->deleteDirectory($folder);
            }

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
        $this->reset(['archivo', 'procesando', 'generado', 'mensaje', 'error', 'resumen', 'erroresValidacion', 'advertencias', 'txtPath', 'txtName', 'hoja']);
        $this->periodo = now()->subMonthNoOverflow()->format('Y-m');
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
        $this->hoja = null;
    }

    public function render()
    {
        return view('livewire.informes.resolucion1604-familiar-uploader');
    }
}
