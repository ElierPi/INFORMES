<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1604\FamiliarColombia\Resolucion1604FamiliarCorrectionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1604FamiliarCorrectionUploader extends Component
{
    use WithFileUploads;

    public $archivoInforme = null;
    public $archivoErrores = null;
    public bool $procesando = false;
    public bool $corregido = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public ?string $txtPath = null;
    public ?string $txtName = null;
    public array $resumen = [];
    public array $auditoria = [];
    public array $pendientesManuales = [];

    protected function rules(): array
    {
        return [
            'archivoInforme' => ['required'],
            'archivoErrores' => ['required'],
        ];
    }

    public function corregir(Resolucion1604FamiliarCorrectionService $service): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Analizando el TXT y los errores de Familiar...';
        $this->limpiarResultado();

        try {
            $this->validate();
            $originalName = $this->validarTxt($this->archivoInforme, 30, 'informe');
            $this->validarTxt($this->archivoErrores, 10, 'errores');

            $folder = 'private/uploads/resolucion1604-familiar-correccion/'.Str::uuid();
            Storage::disk('local')->makeDirectory($folder);

            $inputRelative = $this->archivoInforme->storeAs($folder, 'informe.txt', 'local');
            $errorsRelative = $this->archivoErrores->storeAs($folder, 'errores.txt', 'local');
            $inputPath = Storage::disk('local')->path((string) $inputRelative);
            $errorsPath = Storage::disk('local')->path((string) $errorsRelative);
            $outputDirectory = Storage::disk('local')->path($folder.'/salida');

            $result = $service->correct($inputPath, $errorsPath, $originalName, $outputDirectory);

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->auditoria = is_array($result['audit'] ?? null) ? $result['audit'] : [];
            $this->pendientesManuales = is_array($result['manual'] ?? null) ? $result['manual'] : [];
            $this->resumen = [
                'originales' => (int) ($result['original_records'] ?? 0),
                'excluidos' => (int) ($result['excluded_records'] ?? 0),
                'finales' => (int) ($result['final_records'] ?? 0),
                'automaticas' => (int) ($result['automatic_corrections'] ?? 0),
                'manuales' => (int) ($result['manual_count'] ?? 0),
            ];

            $this->corregido = true;
            $this->mensaje = $this->pendientesManuales === []
                ? 'El informe fue corregido. Las líneas con CUM inexistente fueron excluidas y el nombre original se conservó.'
                : 'Se aplicaron las reglas conocidas. Hay errores nuevos que requieren revisión antes del cargue.';
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
        if (! $this->corregido || ! is_string($this->txtPath) || ! is_file($this->txtPath)) {
            $this->error = 'El TXT corregido ya no está disponible. Procesa nuevamente los archivos.';
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
        $this->reset([
            'archivoInforme', 'archivoErrores', 'procesando', 'corregido', 'mensaje', 'error',
            'txtPath', 'txtName', 'resumen', 'auditoria', 'pendientesManuales',
        ]);
        $this->resetValidation();
    }

    private function validarTxt($file, int $maxMb, string $label): string
    {
        if (! $file instanceof TemporaryUploadedFile) {
            throw new RuntimeException('Selecciona nuevamente el TXT de '.$label.'.');
        }
        $path = $file->getRealPath();
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('El archivo temporal de '.$label.' expiró. Selecciónalo nuevamente.');
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'txt') {
            throw new RuntimeException('El archivo de '.$label.' debe tener extensión .txt.');
        }
        if (filesize($path) > $maxMb * 1024 * 1024) {
            throw new RuntimeException('El archivo de '.$label.' supera el tamaño máximo permitido de '.$maxMb.' MB.');
        }
        return $file->getClientOriginalName();
    }

    private function limpiarResultado(): void
    {
        $this->corregido = false;
        $this->txtPath = null;
        $this->txtName = null;
        $this->resumen = [];
        $this->auditoria = [];
        $this->pendientesManuales = [];
    }

    public function render()
    {
        return view('livewire.informes.resolucion1604-familiar-correction-uploader');
    }
}
