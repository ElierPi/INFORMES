<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1552\Resolucion1552DusakawiCorrectionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1552DusakawiCorrectionUploader extends Component
{
    use WithFileUploads;

    public $archivoInforme = null;
    public $archivoErrores = null;
    public bool $procesando = false;
    public bool $generado = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public array $resumen = [];
    public array $auditoria = [];
    public array $pendientes = [];
    public ?string $outputPath = null;
    public ?string $outputName = null;

    protected function rules(): array
    {
        return [
            'archivoInforme' => ['required', 'file', 'extensions:txt', 'max:30720'],
            'archivoErrores' => ['required', 'file', 'extensions:txt', 'max:10240'],
        ];
    }

    public function corregir(Resolucion1552DusakawiCorrectionService $service): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Analizando errores...';
        $this->limpiarResultado();

        try {
            $this->validate();

            if (! $this->archivoInforme instanceof TemporaryUploadedFile || ! $this->archivoErrores instanceof TemporaryUploadedFile) {
                throw new RuntimeException('Selecciona nuevamente el informe y el archivo de errores.');
            }

            $folder = 'private/uploads/resolucion1552-dusakawi-correccion/' . Str::uuid();
            Storage::disk('local')->makeDirectory($folder);
            $originalReportName = basename($this->archivoInforme->getClientOriginalName());
            $reportRelative = $this->archivoInforme->storeAs($folder, $originalReportName, 'local');
            $errorsRelative = $this->archivoErrores->storeAs($folder, 'errores.txt', 'local');
            $result = $service->correct(
                Storage::disk('local')->path((string) $reportRelative),
                Storage::disk('local')->path((string) $errorsRelative),
                $originalReportName,
            );

            $this->resumen = [
                'originales' => (int) $result['original_records'],
                'corregidos' => (int) $result['corrected_records'],
                'eliminados' => (int) $result['removed_records'],
                'actualizados' => (int) $result['updated_records'],
                'errores' => (int) $result['parsed_errors'],
                'automaticos' => (int) $result['automatic_corrections'],
                'manuales' => count($result['manual_errors']),
                'control_anterior' => (string) $result['previous_control_total'],
                'control_nuevo' => (string) $result['new_control_total'],
            ];
            $this->auditoria = $result['audit'];
            $this->pendientes = $result['manual_errors'];
            $this->outputPath = $result['output_path'];
            $this->outputName = $result['output_name'];
            $this->generado = true;
            $this->mensaje = $this->pendientes === []
                ? 'Los errores fueron corregidos automáticamente.'
                : 'El TXT fue generado, pero quedaron errores para revisión manual.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible corregir el informe: ' . $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarTxt()
    {
        if (! $this->generado || ! is_string($this->outputPath) || ! is_file($this->outputPath)) {
            $this->error = 'El TXT corregido ya no está disponible. Procesa nuevamente los archivos.';

            return null;
        }

        return response()->download($this->outputPath, $this->outputName ?: basename($this->outputPath), [
            'Content-Type' => 'text/plain; charset=Windows-1252',
        ]);
    }

    public function reiniciar(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    private function limpiarResultado(): void
    {
        $this->generado = false;
        $this->resumen = [];
        $this->auditoria = [];
        $this->pendientes = [];
        $this->outputPath = null;
        $this->outputName = null;
    }

    public function render()
    {
        return view('livewire.informes.resolucion1552-dusakawi-correction-uploader');
    }
}
