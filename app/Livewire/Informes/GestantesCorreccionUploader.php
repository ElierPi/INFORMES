<?php

namespace App\Livewire\Informes;

use App\Services\Gestantes\GestantesErrorCorrectionService;
use App\Services\Gestantes\GestantesExcelReader;
use App\Services\Gestantes\GestantesLogsParser;
use App\Services\Gestantes\GestantesTxtGenerator;
use App\Services\Gestantes\GestantesValidationService;
use App\Services\Gestantes\GestantesZipGenerator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
class GestantesCorreccionUploader extends Component
{
    use WithFileUploads;

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

    public ?string $correctedExcelPath = null;

    public array $changes = [];

    public array $manualCorrections = [];

    public int $correctedCount = 0;

    public int $pendingCount = 0;

    public ?string $generatedTxtPath = null;

    public ?string $generatedZipPath = null;

    public bool $artifactsGenerated = false;

    public array $finalValidationErrors = [];

    public array $finalValidationSummary = [];

    public function analyze(
        GestantesLogsParser $logsParser
    ): void {
        $this->resetErrorBag();

        $this->validate([
            'archivoInforme' => [
                'required',
                'file',
                'mimes:xlsx,xls',
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
                'Debes seleccionar el Excel original del informe.',
            'archivoErrores.required' =>
                'Debes seleccionar el archivo de errores SIGIRES.',
            'archivoInforme.mimes' =>
                'El informe debe ser un archivo XLSX o XLS.',
            'archivoErrores.mimes' =>
                'El archivo de errores debe ser XLS o XLSX.',
        ]);

        try {
            $this->clearResults();

            $identifier = now()->format('Ymd_His')
                . '_'
                . bin2hex(random_bytes(3));

            $directory =
                "gestantes/correccion-sigires/{$identifier}";

            $this->informePath = $this->archivoInforme->storeAs(
                $directory,
                $this->sanitizeFileName(
                    $this->archivoInforme->getClientOriginalName()
                ),
                'local'
            );

            $this->erroresPath = $this->archivoErrores->storeAs(
                $directory,
                $this->sanitizeFileName(
                    $this->archivoErrores->getClientOriginalName()
                ),
                'local'
            );

            if (
                ! $this->informePath
                || ! Storage::disk('local')->exists(
                    $this->informePath
                )
            ) {
                throw new RuntimeException(
                    'No fue posible almacenar el Excel original.'
                );
            }

            if (
                ! $this->erroresPath
                || ! Storage::disk('local')->exists(
                    $this->erroresPath
                )
            ) {
                throw new RuntimeException(
                    'No fue posible almacenar el archivo de errores.'
                );
            }

            $absoluteErrorsPath = Storage::disk('local')->path(
                $this->erroresPath
            );

            $result = $logsParser->parse(
                $absoluteErrorsPath
            );

            $this->parsedErrors = $result['errors'] ?? [];
            $this->metadata = $result['metadata'] ?? [];

            $this->totalErrors = (int) (
                $result['total'] ?? count($this->parsedErrors)
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
                "Se analizaron {$this->totalErrors} errores del archivo SIGIRES."
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->clearResults();

            $this->addError(
                'archivoErrores',
                'No fue posible analizar los archivos: '
                . $exception->getMessage()
            );
        }
    }

    public function correct(
        GestantesErrorCorrectionService $correctionService
    ): void {
        $this->resetErrorBag();
        $this->resetGeneratedArtifacts();

        if (! $this->analyzed) {
            $this->addError(
                'archivoErrores',
                'Primero debes analizar los errores.'
            );

            return;
        }

        if (
            $this->informePath === null
            || ! Storage::disk('local')->exists(
                $this->informePath
            )
        ) {
            $this->addError(
                'archivoInforme',
                'No se encontró el Excel original.'
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

            $inputAbsolutePath = Storage::disk('local')->path(
                $this->informePath
            );

            $directory = dirname(
                $this->informePath
            );

            $fileName = pathinfo(
                $this->informePath,
                PATHINFO_FILENAME
            );

            $this->correctedExcelPath =
                $directory
                . '/'
                . $fileName
                . '_CORREGIDO.xlsx';

            $outputAbsolutePath = Storage::disk('local')->path(
                $this->correctedExcelPath
            );

            $result = $correctionService->correct(
                inputPath: $inputAbsolutePath,
                outputPath: $outputAbsolutePath,
                errors: $this->parsedErrors
            );

            $this->changes = $result['changes'] ?? [];
            $this->manualCorrections =
                $result['manual'] ?? [];

            $this->correctedCount = (int) (
                $result['corrected'] ?? count($this->changes)
            );

            $this->pendingCount = (int) (
                $result['pending']
                ?? count($this->manualCorrections)
            );

            $this->corrected = true;

            session()->flash(
                'success',
                "Proceso terminado: {$this->correctedCount} correcciones aplicadas y {$this->pendingCount} pendientes de revisión."
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->resetCorrectionResults();

            $this->addError(
                'archivoErrores',
                'No fue posible corregir el informe: '
                . $exception->getMessage()
            );
        }
    }

    public function generateTxtAndZip(
        GestantesExcelReader $reader,
        GestantesValidationService $validator,
        GestantesTxtGenerator $txtGenerator,
        GestantesZipGenerator $zipGenerator
    ): void {
        $this->resetErrorBag('generation');
        $this->resetGeneratedArtifacts();

        try {
            $correctedPath = $this->resolveCorrectedExcelPath();

            $data = $reader->read($correctedPath);
            $validation = $validator->validate($data);

            $this->finalValidationErrors =
                $validation['errors'] ?? [];

            $this->finalValidationSummary =
                $validation['summary'] ?? [];

            if (! ($validation['valid'] ?? false)) {
                throw new RuntimeException(
                    'El Excel corregido todavía presenta '
                    . count($this->finalValidationErrors)
                    . ' error(es) de validación. Revisa el resultado '
                    . 'antes de generar el TXT.'
                );
            }

            $timestamp = now()->format('Ymd_His');

            $relativeDirectory =
                "gestantes/sigires-corrected/{$timestamp}";

            $temporaryTxtRelativePath =
                "{$relativeDirectory}/gestantes.txt";

            $temporaryTxtAbsolutePath =
                Storage::disk('local')->path(
                    $temporaryTxtRelativePath
                );

            $generatedTxtAbsolutePath =
                $txtGenerator->generate(
                    data: $data,
                    outputPath: $temporaryTxtAbsolutePath
                );

            if (
                ! is_file($generatedTxtAbsolutePath)
                || filesize($generatedTxtAbsolutePath) === 0
            ) {
                throw new RuntimeException(
                    'El generador terminó, pero no se encontró '
                    . 'el TXT generado.'
                );
            }

            $generatedBaseName = pathinfo(
                basename($generatedTxtAbsolutePath),
                PATHINFO_FILENAME
            );

            $generatedZipAbsolutePath =
                dirname($generatedTxtAbsolutePath)
                . DIRECTORY_SEPARATOR
                . $generatedBaseName
                . '.zip';

            $generatedZipAbsolutePath =
                $zipGenerator->generate(
                    txtPath: $generatedTxtAbsolutePath,
                    zipPath: $generatedZipAbsolutePath
                );

            if (
                ! is_file($generatedZipAbsolutePath)
                || filesize($generatedZipAbsolutePath) === 0
            ) {
                throw new RuntimeException(
                    'El generador terminó, pero no se encontró '
                    . 'el ZIP generado.'
                );
            }

            $this->generatedTxtPath =
                $this->absoluteToLocalRelativePath(
                    $generatedTxtAbsolutePath
                );

            $this->generatedZipPath =
                $this->absoluteToLocalRelativePath(
                    $generatedZipAbsolutePath
                );

            $this->artifactsGenerated = true;

            session()->flash(
                'generation_success',
                'El Excel corregido fue validado correctamente. '
                . 'El TXT y el ZIP están listos para descargar.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->resetGeneratedArtifacts(
                preserveValidation: true
            );

            $this->addError(
                'generation',
                'No fue posible generar el TXT y el ZIP: '
                . $exception->getMessage()
            );
        }
    }

    public function downloadCorrectedExcel(): BinaryFileResponse
    {
        if (
            $this->correctedExcelPath === null
            || ! Storage::disk('local')->exists(
                $this->correctedExcelPath
            )
        ) {
            throw new RuntimeException(
                'No se encontró el Excel corregido.'
            );
        }

        return response()->download(
            Storage::disk('local')->path(
                $this->correctedExcelPath
            ),
            basename($this->correctedExcelPath),
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
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
            'No se encontró el TXT generado.'
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
            'No se encontró el ZIP generado.'
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
        $this->correctedExcelPath = null;
        $this->changes = [];
        $this->manualCorrections = [];
        $this->correctedCount = 0;
        $this->pendingCount = 0;

        $this->resetGeneratedArtifacts();
    }

    private function resetGeneratedArtifacts(
        bool $preserveValidation = false
    ): void {
        $this->generatedTxtPath = null;
        $this->generatedZipPath = null;
        $this->artifactsGenerated = false;

        if (! $preserveValidation) {
            $this->finalValidationErrors = [];
            $this->finalValidationSummary = [];
        }
    }

    private function resolveCorrectedExcelPath(): string
    {
        if (
            ! $this->corrected
            || ! is_string($this->correctedExcelPath)
            || trim($this->correctedExcelPath) === ''
        ) {
            throw new RuntimeException(
                'Primero debes ejecutar la corrección automática.'
            );
        }

        if (is_file($this->correctedExcelPath)) {
            return $this->correctedExcelPath;
        }

        if (
            Storage::disk('local')->exists(
                $this->correctedExcelPath
            )
        ) {
            return Storage::disk('local')->path(
                $this->correctedExcelPath
            );
        }

        throw new RuntimeException(
            'No se encontró el Excel corregido. '
            . 'Ejecuta nuevamente la corrección automática.'
        );
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
                'El archivo generado quedó fuera del '
                . 'almacenamiento local permitido.'
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
            . '.'
            . mb_strtolower($extension);
    }

    public function render()
    {
        return view(
            'livewire.informes.gestantes-correccion-uploader'
        );
    }
}