<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1552\Resolucion1552Exporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonSerializable;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1552Uploader extends Component
{
    use WithFileUploads;

    public $archivo = null;

    public bool $procesando = false;
    public bool $generado = false;

    public ?string $mensaje = null;
    public ?string $error = null;

    /** @var array<string, mixed> */
    public array $resumen = [];

    /** @var array<int, array<string, mixed>> */
    public array $erroresValidacion = [];

    /** @var array<int, array<string, mixed>> */
    public array $advertencias = [];

    public ?string $zipPath = null;
    public ?string $zipName = null;
    public ?string $txtPath = null;
    public ?string $txtName = null;

    protected function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:30720',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'archivo.required' =>
                'Selecciona el archivo Excel de la Resolución 1552.',
            'archivo.file' =>
                'El archivo seleccionado no es válido.',
            'archivo.mimes' =>
                'El archivo debe tener formato XLSX o XLS.',
            'archivo.max' =>
                'El archivo no puede superar los 30 MB.',
        ];
    }

    public function updatedArchivo(): void
    {
        $this->limpiarResultado();
        $this->resetValidation();
        $this->error = null;
        $this->mensaje = null;
    }

    public function generar(
        Resolucion1552Exporter $exporter
    ): void {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Procesando el archivo...';
        $this->limpiarResultado();

        try {
            $this->validate();
            $this->validarTemporal();

            $originalName =
                $this->archivo->getClientOriginalName();

            $extension = strtolower(
                $this->archivo->getClientOriginalExtension()
            );

            $folder =
                'private/uploads/resolucion1552/'
                . Str::uuid();

            Storage::disk('local')->makeDirectory($folder);

            $storedRelativePath = $this->archivo->storeAs(
                $folder,
                'entrada.' . $extension,
                'local'
            );

            if (
                ! is_string($storedRelativePath)
                || $storedRelativePath === ''
            ) {
                throw new RuntimeException(
                    'No fue posible guardar temporalmente el Excel.'
                );
            }

            $storedPath = Storage::disk('local')->path(
                $storedRelativePath
            );

            if (! is_file($storedPath)) {
                throw new RuntimeException(
                    'El Excel temporal no está disponible para procesarlo.'
                );
            }

            $result = $exporter->exportFromExcel(
                path: $storedPath,
                originalFilename: $originalName,
            );

            $validation = is_array(
                $result['validation'] ?? null
            )
                ? $result['validation']
                : [];

            /*
             * Livewire no debe conservar objetos ValidationIssue
             * en propiedades públicas. Los convertimos a arreglos
             * antes de renderizar y serializar el componente.
             */
            $this->erroresValidacion = $this->normalizeIssues(
                $validation['errors'] ?? []
            );

            $this->advertencias = $this->normalizeIssues(
                $validation['warnings'] ?? []
            );

            $statistics = is_array(
                $validation['statistics'] ?? null
            )
                ? $validation['statistics']
                : [];

            $this->resumen = [
                'registros' => (int) (
                    $statistics['records_count']
                    ?? $result['records_count']
                    ?? 0
                ),
                'validos' => (int) (
                    $statistics['valid_records_count']
                    ?? 0
                ),
                'invalidos' => (int) (
                    $statistics['invalid_records_count']
                    ?? 0
                ),
                'errores' => (int) (
                    $statistics['errors_count']
                    ?? count($this->erroresValidacion)
                ),
                'advertencias' => (int) (
                    $statistics['warnings_count']
                    ?? count($this->advertencias)
                ),
                'prestador' => (string) (
                    $result['provider_code'] ?? ''
                ),
                'codificacion' => (string) (
                    $result['encoding'] ?? 'Windows-1252'
                ),
            ];

            if (! ($result['success'] ?? false)) {
                $this->error =
                    'El archivo contiene errores que deben corregirse antes de generar el informe.';
                $this->mensaje = null;

                return;
            }

            $this->zipPath = (string) (
                $result['zip_path'] ?? ''
            );
            $this->zipName = (string) (
                $result['zip_name'] ?? ''
            );
            $this->txtPath = (string) (
                $result['txt_path'] ?? ''
            );
            $this->txtName = (string) (
                $result['txt_name'] ?? ''
            );

            $this->generado = true;
            $this->mensaje =
                'El informe fue validado y generado correctamente.';
        } catch (Throwable $exception) {
            report($exception);

            $this->error =
                'No fue posible procesar el archivo: '
                . $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarZip()
    {
        if (! $this->archivoDisponible($this->zipPath)) {
            $this->error =
                'El ZIP ya no está disponible. Genera nuevamente el informe.';

            return null;
        }

        return response()->download(
            $this->zipPath,
            $this->zipName ?: basename($this->zipPath),
            ['Content-Type' => 'application/zip']
        );
    }

    public function descargarTxt()
    {
        if (! $this->archivoDisponible($this->txtPath)) {
            $this->error =
                'El TXT ya no está disponible. Genera nuevamente el informe.';

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
            'archivo',
            'procesando',
            'generado',
            'mensaje',
            'error',
            'resumen',
            'erroresValidacion',
            'advertencias',
            'zipPath',
            'zipName',
            'txtPath',
            'txtName',
        ]);

        $this->resetValidation();
    }

    private function validarTemporal(): void
    {
        if (! $this->archivo instanceof TemporaryUploadedFile) {
            throw new RuntimeException(
                'El archivo seleccionado no está disponible. Vuelve a cargarlo.'
            );
        }

        $realPath = $this->archivo->getRealPath();

        if (
            ! is_string($realPath)
            || $realPath === ''
            || ! is_file($realPath)
        ) {
            throw new RuntimeException(
                'Livewire perdió el archivo temporal. Selecciónalo nuevamente.'
            );
        }
    }

    /**
     * @param iterable<mixed> $issues
     * @return array<int, array<string, mixed>>
     */
    private function normalizeIssues(
        iterable $issues
    ): array {
        $normalized = [];

        foreach ($issues as $issue) {
            if (is_array($issue)) {
                $normalized[] = $issue;
                continue;
            }

            if (is_object($issue) && method_exists($issue, 'toArray')) {
                $value = $issue->toArray();

                if (is_array($value)) {
                    $normalized[] = $value;
                }

                continue;
            }

            if ($issue instanceof JsonSerializable) {
                $value = $issue->jsonSerialize();

                if (is_array($value)) {
                    $normalized[] = $value;
                }
            }
        }

        return $normalized;
    }

    private function archivoDisponible(
        ?string $path
    ): bool {
        return $this->generado
            && is_string($path)
            && $path !== ''
            && is_file($path);
    }

    private function limpiarResultado(): void
    {
        $this->generado = false;
        $this->resumen = [];
        $this->erroresValidacion = [];
        $this->advertencias = [];
        $this->zipPath = null;
        $this->zipName = null;
        $this->txtPath = null;
        $this->txtName = null;
    }

    public function render()
    {
        return view(
            'livewire.informes.resolucion1552-uploader'
        );
    }
}
