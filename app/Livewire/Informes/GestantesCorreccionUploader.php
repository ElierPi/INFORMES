<?php

namespace App\Livewire\Informes;

use App\Services\Gestantes\GestantesLogsParser;
use App\Services\Gestantes\GestantesTxtErrorCorrectionService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

#[Layout('components.layouts.app')]
class GestantesCorreccionUploader extends Component
{
    use WithFileUploads;

    /**
     * Ahora recibe directamente el ZIP generado por Gestante semanal.
     */
    public $archivoInforme;

    public $archivoErrores;

    public ?string $informePath = null;
    public ?string $erroresPath = null;

    public array $parsedErrors = [];
    public array $metadata = [];

    public int $totalErrors = 0;
    public int $automaticErrors = 0;
    public int $manualErrors = 0;

    public bool $analyzed = false;
    public bool $corrected = false;

    public array $changes = [];
    public array $manualCorrections = [];

    public int $correctedCount = 0;
    public int $pendingCount = 0;

    public ?string $generatedTxtPath = null;
    public ?string $generatedZipPath = null;
    public bool $artifactsGenerated = false;

    public function analyze(
        GestantesLogsParser $logsParser
    ): void {
        $this->resetErrorBag();

        $this->validate([
            'archivoInforme' => [
                'required',
                'file',
                'mimes:zip',
                'max:30720',
            ],
            'archivoErrores' => [
                'required',
                'file',
                'mimes:xls,xlsx',
                'max:10240',
            ],
        ], [
            'archivoInforme.required' =>
                'Debes seleccionar el ZIP que generó Gestante semanal.',
            'archivoErrores.required' =>
                'Debes seleccionar el archivo de errores SIGIRES.',
            'archivoInforme.mimes' =>
                'El informe debe ser el ZIP generado por Gestante semanal.',
            'archivoErrores.mimes' =>
                'El archivo de errores debe ser XLS o XLSX.',
        ]);

        try {
            $this->clearResults();

            $identifier =
                now()->format('Ymd_His')
                .'_'
                .bin2hex(random_bytes(3));

            $directory =
                "gestantes/correccion-sigires/{$identifier}";

            $this->informePath =
                $this->archivoInforme->storeAs(
                    $directory,
                    $this->sanitizeFileName(
                        $this->archivoInforme
                            ->getClientOriginalName()
                    ),
                    'local'
                );

            $this->erroresPath =
                $this->archivoErrores->storeAs(
                    $directory,
                    $this->sanitizeFileName(
                        $this->archivoErrores
                            ->getClientOriginalName()
                    ),
                    'local'
                );

            if (
                ! $this->informePath
                || ! Storage::disk('local')
                    ->exists($this->informePath)
            ) {
                throw new RuntimeException(
                    'No fue posible almacenar el ZIP original.'
                );
            }

            if (
                ! $this->erroresPath
                || ! Storage::disk('local')
                    ->exists($this->erroresPath)
            ) {
                throw new RuntimeException(
                    'No fue posible almacenar el archivo de errores.'
                );
            }

            $result = $logsParser->parse(
                Storage::disk('local')->path(
                    $this->erroresPath
                )
            );

            $this->parsedErrors =
                $result['errors'] ?? [];

            $this->metadata =
                $result['metadata'] ?? [];

            $this->totalErrors = (int) (
                $result['total']
                ?? count($this->parsedErrors)
            );

            $this->automaticErrors = (int) (
                $result['automatic'] ?? 0
            );

            $this->manualErrors = (int) (
                $result['manual'] ?? 0
            );

            $this->analyzed = true;

            session()->flash(
                'success',
                "Se analizaron {$this->totalErrors} errores del LOG de SIGIRES."
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->clearResults();

            $this->addError(
                'archivoErrores',
                'No fue posible analizar los archivos: '
                .$exception->getMessage()
            );
        }
    }

    public function correct(
        GestantesTxtErrorCorrectionService $service
    ): void {
        $this->resetErrorBag();

        if (! $this->analyzed) {
            $this->addError(
                'archivoErrores',
                'Primero debes analizar los errores.'
            );

            return;
        }

        if (
            ! is_string($this->informePath)
            || ! Storage::disk('local')
                ->exists($this->informePath)
        ) {
            $this->addError(
                'archivoInforme',
                'No se encontró el ZIP original.'
            );

            return;
        }

        if ($this->parsedErrors === []) {
            $this->addError(
                'archivoErrores',
                'No existen errores para corregir.'
            );

            return;
        }

        try {
            $this->resetCorrectionResults();

            $zipAbsolutePath =
                Storage::disk('local')->path(
                    $this->informePath
                );

            $relativeDirectory =
                dirname($this->informePath)
                .'/corregido';

            $outputDirectory =
                Storage::disk('local')->path(
                    $relativeDirectory
                );

            $result = $service->correctZip(
                zipPath: $zipAbsolutePath,
                outputDirectory: $outputDirectory,
                errors: $this->parsedErrors
            );

            $this->changes =
                $result['changes'] ?? [];

            $this->manualCorrections =
                $result['manual'] ?? [];

            $this->correctedCount = (int) (
                $result['corrected'] ?? 0
            );

            $this->pendingCount = (int) (
                $result['pending'] ?? 0
            );

            $this->generatedTxtPath =
                $this->absoluteToLocalRelativePath(
                    $result['txt_path']
                );

            $this->generatedZipPath =
                $this->absoluteToLocalRelativePath(
                    $result['zip_path']
                );

            $this->corrected = true;
            $this->artifactsGenerated = true;

            session()->flash(
                'correction_success',
                "Se aplicaron {$this->correctedCount} correcciones automáticas. "
                ."Quedaron {$this->pendingCount} pendientes. "
                .'El ZIP corregido ya está listo para descargar.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->resetCorrectionResults();

            $this->addError(
                'archivoErrores',
                'No fue posible corregir el ZIP: '
                .$exception->getMessage()
            );
        }
    }

    public function downloadGeneratedTxt(): StreamedResponse
    {
        abort_unless(
            is_string($this->generatedTxtPath)
            && $this->generatedTxtPath !== ''
            && Storage::disk('local')->exists(
                $this->generatedTxtPath
            ),
            404,
            'No se encontró el TXT corregido.'
        );

        return Storage::disk('local')->download(
            $this->generatedTxtPath,
            basename($this->generatedTxtPath),
            [
                'Content-Type' => 'text/plain',
            ]
        );
    }

    public function downloadGeneratedZip(): StreamedResponse
    {
        abort_unless(
            is_string($this->generatedZipPath)
            && $this->generatedZipPath !== ''
            && Storage::disk('local')->exists(
                $this->generatedZipPath
            ),
            404,
            'No se encontró el ZIP corregido.'
        );

        return Storage::disk('local')->download(
            $this->generatedZipPath,
            basename($this->generatedZipPath),
            [
                'Content-Type' => 'application/zip',
            ]
        );
    }

    public function updatedArchivoInforme(): void
    {
        $this->clearResults();
        $this->resetErrorBag();
    }

    public function updatedArchivoErrores(): void
    {
        $this->clearResults();
        $this->resetErrorBag();
    }

    private function clearResults(): void
    {
        $this->parsedErrors = [];
        $this->metadata = [];
        $this->totalErrors = 0;
        $this->automaticErrors = 0;
        $this->manualErrors = 0;
        $this->analyzed = false;

        $this->resetCorrectionResults();
    }

    private function resetCorrectionResults(): void
    {
        $this->corrected = false;
        $this->changes = [];
        $this->manualCorrections = [];
        $this->correctedCount = 0;
        $this->pendingCount = 0;
        $this->generatedTxtPath = null;
        $this->generatedZipPath = null;
        $this->artifactsGenerated = false;
    }

    private function absoluteToLocalRelativePath(
        string $absolutePath
    ): string {
        $localRoot = rtrim(
            Storage::disk('local')->path(''),
            DIRECTORY_SEPARATOR
        );

        $normalizedRoot = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $localRoot
        );

        $normalizedPath = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $absolutePath
        );

        if (
            ! str_starts_with(
                $normalizedPath,
                $normalizedRoot
            )
        ) {
            throw new RuntimeException(
                'El archivo corregido quedó fuera del almacenamiento local.'
            );
        }

        return ltrim(
            substr(
                $normalizedPath,
                strlen($normalizedRoot)
            ),
            DIRECTORY_SEPARATOR
        );
    }

    private function sanitizeFileName(
        string $fileName
    ): string {
        $extension = pathinfo(
            $fileName,
            PATHINFO_EXTENSION
        );

        $name = pathinfo(
            $fileName,
            PATHINFO_FILENAME
        );

        $name = preg_replace(
            '/[^A-Za-z0-9_-]+/',
            '_',
            $name
        ) ?? 'archivo';

        return trim($name, '_')
            .'.'
            .mb_strtolower($extension);
    }

    public function render()
    {
        return view(
            'livewire.informes.gestantes-correccion-uploader'
        );
    }
}
