<?php

namespace App\Livewire\Informes;

use App\Services\CronicosDusakawi\CronicosDusakawiCorrectionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class CronicosDusakawiCorrectionUploader extends Component
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
    public bool $eliminarDuplicados = false;

    public function corregir(CronicosDusakawiCorrectionService $service): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Analizando novedades de DUSAKAWI...';
        $this->limpiarResultado();

        try {
            $this->validate([
                'archivoInforme' => ['required'],
                'archivoErrores' => ['required'],
            ], [
                'archivoInforme.required' => 'Selecciona el Excel original cargado a DUSAKAWI.',
                'archivoErrores.required' => 'Selecciona el Excel de errores devuelto por DUSAKAWI.',
            ]);

            if (! $this->archivoInforme instanceof TemporaryUploadedFile || ! $this->archivoErrores instanceof TemporaryUploadedFile) {
                throw new RuntimeException('Selecciona nuevamente ambos archivos Excel.');
            }

            $this->validarExcelTemporal($this->archivoInforme, 50 * 1024 * 1024);
            $this->validarExcelTemporal($this->archivoErrores, 20 * 1024 * 1024);

            $folder = 'private/uploads/cronicos-dusakawi-correccion/'.Str::uuid();
            Storage::disk('local')->makeDirectory($folder);

            $originalName = basename($this->archivoInforme->getClientOriginalName());
            $reportRelative = $this->archivoInforme->storeAs($folder, $originalName, 'local');
            $errorsRelative = $this->archivoErrores->storeAs($folder, 'errores_dusakawi.xlsx', 'local');

            $outputFolder = storage_path('app/private/reports/cronicos-dusakawi/'.Str::uuid());
            if (! is_dir($outputFolder) && ! mkdir($outputFolder, 0775, true) && ! is_dir($outputFolder)) {
                throw new RuntimeException('No fue posible crear la carpeta de salida.');
            }

            // El archivo corregido conserva exactamente el nombre del Excel original.
            $this->outputName = $originalName;
            $this->outputPath = $outputFolder.DIRECTORY_SEPARATOR.$originalName;

            $result = $service->correct(
                Storage::disk('local')->path((string) $reportRelative),
                Storage::disk('local')->path((string) $errorsRelative),
                $this->outputPath,
                $this->eliminarDuplicados,
            );

            $this->auditoria = $result['audit'];
            $this->pendientes = $result['manual_errors'];
            $this->resumen = [
                'errores' => (int) $result['parsed_errors'],
                'automaticos' => (int) $result['automatic_corrections'],
                'celdas' => (int) $result['updated_cells'],
                'duplicados' => (int) ($result['duplicate_rows'] ?? 0),
                'manuales' => count($this->pendientes),
            ];

            $this->generado = true;
            $this->mensaje = $this->pendientes === []
                ? 'El Excel fue corregido automáticamente.'
                : 'Se generó el Excel corregido. Algunos casos quedaron para revisión manual.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible corregir el informe: '.$exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarExcel()
    {
        if (! $this->generado || ! is_string($this->outputPath) || ! is_file($this->outputPath)) {
            $this->error = 'El Excel corregido ya no está disponible. Procesa nuevamente los archivos.';
            return null;
        }

        return response()->download(
            $this->outputPath,
            $this->outputName ?: basename($this->outputPath),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function reiniciar(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    private function validarExcelTemporal(TemporaryUploadedFile $file, int $maxBytes): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Los dos archivos deben ser Excel (.xlsx o .xls).');
        }

        $path = $file->getRealPath();
        if (! $path || ! is_file($path)) {
            throw new RuntimeException('El archivo temporal expiró. Selecciónalo nuevamente.');
        }

        $size = filesize($path);
        if ($size !== false && $size > $maxBytes) {
            throw new RuntimeException('Uno de los archivos supera el tamaño permitido.');
        }
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
        return view('livewire.informes.cronicos-dusakawi-correction-uploader');
    }
}
