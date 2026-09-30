<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1604\Dusakawi\Resolucion1604DusakawiCorrectionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1604DusakawiCorrectionUploader extends Component
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
    public ?string $lineaControl = null;

    public function corregir(Resolucion1604DusakawiCorrectionService $service): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Analizando errores de Aryuwi...';
        $this->limpiarResultado();

        try {
            $this->validate([
                'archivoInforme' => ['required'],
                'archivoErrores' => ['required'],
            ]);

            if (! $this->archivoInforme instanceof TemporaryUploadedFile || ! $this->archivoErrores instanceof TemporaryUploadedFile) {
                throw new RuntimeException('Selecciona nuevamente el TXT original y el TXT de errores.');
            }

            $this->validarTxt($this->archivoInforme, 30 * 1024 * 1024, 'informe');
            $this->validarTxt($this->archivoErrores, 10 * 1024 * 1024, 'errores');

            $folder = 'private/uploads/resolucion1604-dusakawi-correccion/'.Str::uuid();
            Storage::disk('local')->makeDirectory($folder);

            $originalName = basename($this->archivoInforme->getClientOriginalName());
            $reportRelative = $this->archivoInforme->storeAs($folder, $originalName, 'local');
            $errorsRelative = $this->archivoErrores->storeAs($folder, 'errores_dusakawi.txt', 'local');

            $outputFolder = storage_path('app/private/reports/resolucion1604-dusakawi-correccion/'.Str::uuid());
            if (! is_dir($outputFolder) && ! mkdir($outputFolder, 0775, true) && ! is_dir($outputFolder)) {
                throw new RuntimeException('No fue posible crear la carpeta de salida.');
            }

            // Conserva exactamente el nombre del TXT original.
            $this->outputName = $originalName;
            $this->outputPath = $outputFolder.DIRECTORY_SEPARATOR.$originalName;

            $result = $service->correct(
                Storage::disk('local')->path((string) $reportRelative),
                Storage::disk('local')->path((string) $errorsRelative),
                $this->outputPath,
            );

            $this->auditoria = $result['audit'];
            $this->pendientes = $result['manual_errors'];
            $this->lineaControl = $result['control_line'];
            $this->resumen = [
                'errores' => (int) $result['parsed_error_lines'],
                'originales' => (int) $result['original_records'],
                'eliminados' => (int) $result['deleted_records'],
                'actualizados' => (int) $result['updated_records'],
                'finales' => (int) $result['final_records'],
                'manuales' => count($this->pendientes),
            ];

            $this->generado = true;
            $this->mensaje = $this->pendientes === []
                ? 'El TXT fue corregido automáticamente y está listo para un nuevo cargue.'
                : 'Se generó el TXT corregido. Quedaron algunos casos para revisión manual.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible corregir el informe: '.$exception->getMessage();
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
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function reiniciar(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    private function validarTxt(TemporaryUploadedFile $file, int $maxBytes, string $label): void
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'txt') {
            throw new RuntimeException('El archivo de '.$label.' debe ser TXT (.txt).');
        }
        $path = $file->getRealPath();
        if (! $path || ! is_file($path)) {
            throw new RuntimeException('El archivo temporal expiró. Selecciónalo nuevamente.');
        }
        $size = filesize($path);
        if ($size !== false && $size > $maxBytes) {
            throw new RuntimeException('El archivo de '.$label.' supera el tamaño permitido.');
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
        $this->lineaControl = null;
    }

    public function render()
    {
        return view('livewire.informes.resolucion1604-dusakawi-correction-uploader');
    }
}
