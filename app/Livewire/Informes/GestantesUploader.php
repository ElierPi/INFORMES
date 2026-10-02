<?php

namespace App\Livewire\Informes;

use App\Services\Gestantes\GestantesAutoCorrector;
use App\Services\Gestantes\GestantesExcelReader;
use App\Services\Gestantes\GestantesTxtGenerator;
use App\Services\Gestantes\GestantesValidationService;
use App\Services\Gestantes\GestantesZipGenerator;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class GestantesUploader extends Component
{
    use WithFileUploads;

    public $archivo;

    public array $errorsReport = [];
    public array $summary = [];
    public bool $analyzed = false;

    public ?string $uploadedPath = null;
    public ?string $correctedFile = null;
    public ?string $correctedDownloadName = null;

    public array $corrections = [];
    public int $totalCorrections = 0;

    /**
     * Rutas relativas en storage local.
     * Se generan únicamente cuando el Excel corregido queda válido.
     */
    public ?string $txtPath = null;
    public ?string $zipPath = null;

    /**
     * Flujo de Gestante semanal:
     *
     * Excel original
     * -> corrección segura
     * -> relectura
     * -> validación
     * -> Excel corregido siempre disponible
     * -> si queda válido: TXT ANSI + ZIP para SIGIRES.
     */
    public function analyze(
        GestantesExcelReader $reader,
        GestantesValidationService $validator,
        GestantesAutoCorrector $corrector,
        GestantesTxtGenerator $txtGenerator,
        GestantesZipGenerator $zipGenerator
    ): void {
        $this->resetErrorBag();

        $this->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:30720',
            ],
        ], [
            'archivo.required' => 'Selecciona el Excel semanal de gestantes.',
            'archivo.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
        ]);

        try {
            $this->resetProcessResults();

            $originalName = $this->archivo->getClientOriginalName();

            /*
             * El corrector genera XLSX. Conservamos el nombre base
             * del archivo que el usuario cargó.
             */
            $this->correctedDownloadName =
                pathinfo($originalName, PATHINFO_FILENAME).'.xlsx';

            $this->uploadedPath = $this->archivo->store(
                'gestantes/uploads',
                'local'
            );

            $sourcePath = Storage::disk('local')->path(
                $this->uploadedPath
            );

            if (! is_file($sourcePath)) {
                throw new RuntimeException(
                    'No fue posible almacenar el archivo Excel cargado.'
                );
            }

            $timestamp = now()->format('Ymd_His_u');

            $correctedDirectory = storage_path(
                "app/private/gestantes/corregidos/{$timestamp}"
            );

            if (! is_dir($correctedDirectory)) {
                mkdir($correctedDirectory, 0775, true);
            }

            $correctedPath =
                $correctedDirectory
                .DIRECTORY_SEPARATOR
                .$this->correctedDownloadName;

            /*
             * 1. CORREGIR
             */
            $correctionResult = $corrector->correct(
                sourcePath: $sourcePath,
                destinationPath: $correctedPath
            );

            $this->correctedFile =
                $correctionResult['path']
                ?? $correctionResult['ruta']
                ?? null;

            $this->corrections =
                $correctionResult['corrections']
                ?? $correctionResult['correcciones']
                ?? [];

            $this->totalCorrections = (int) (
                $correctionResult['total_corrections']
                ?? $correctionResult['totalCorrections']
                ?? count($this->corrections)
            );

            if (
                ! is_string($this->correctedFile)
                || $this->correctedFile === ''
                || ! is_file($this->correctedFile)
            ) {
                throw new RuntimeException(
                    'El corrector terminó, pero no se encontró el Excel corregido.'
                );
            }

            /*
             * 2. LEER Y VALIDAR EL EXCEL YA CORREGIDO
             */
            $data = $reader->read($this->correctedFile);
            $validation = $validator->validate($data);

            $this->errorsReport = $validation['errors'] ?? [];
            $this->summary = $validation['summary'] ?? [];
            $this->analyzed = true;

            /*
             * IMPORTANTE:
             * Los pendientes NO bloquean la generación.
             *
             * El objetivo de "Gestante semanal" es:
             * 1. aplicar todas las correcciones seguras conocidas;
             * 2. mostrar las novedades internas;
             * 3. generar SIEMPRE el TXT/ZIP para probarlo en SIGIRES;
             * 4. usar después el LOG real de la EPS en el corrector SIGIRES.
             */
            /*
             * 3. GENERAR TXT OFICIAL PARA SIGIRES
             *
             * El propio GestantesTxtGenerator:
             * - obtiene el código de habilitación desde registro tipo 2
             * - usa nombre GESTANTE_MSPS_CODIGOHABILITACION_DDMMAAAA.txt
             * - separa con |
             * - genera ANSI Windows-1252
             * - usa CRLF
             */
            $generatedRelativeDirectory =
                "gestantes/generated/{$timestamp}";

            $temporaryTxtAbsolutePath = Storage::disk('local')->path(
                "{$generatedRelativeDirectory}/gestantes.txt"
            );

            $generatedTxtAbsolutePath = $txtGenerator->generate(
                data: $data,
                outputPath: $temporaryTxtAbsolutePath
            );

            if (! is_file($generatedTxtAbsolutePath)) {
                throw new RuntimeException(
                    'El generador terminó, pero no se encontró el TXT.'
                );
            }

            /*
             * 4. COMPRIMIR TXT EN ZIP CON EL MISMO NOMBRE BASE.
             */
            $generatedBaseName = pathinfo(
                basename($generatedTxtAbsolutePath),
                PATHINFO_FILENAME
            );

            $generatedZipAbsolutePath =
                dirname($generatedTxtAbsolutePath)
                .DIRECTORY_SEPARATOR
                .$generatedBaseName
                .'.zip';

            $generatedZipAbsolutePath = $zipGenerator->generate(
                txtPath: $generatedTxtAbsolutePath,
                zipPath: $generatedZipAbsolutePath
            );

            $localRoot = rtrim(
                Storage::disk('local')->path(''),
                DIRECTORY_SEPARATOR
            );

            $this->txtPath = ltrim(
                str_replace(
                    $localRoot,
                    '',
                    $generatedTxtAbsolutePath
                ),
                DIRECTORY_SEPARATOR
            );

            $this->zipPath = ltrim(
                str_replace(
                    $localRoot,
                    '',
                    $generatedZipAbsolutePath
                ),
                DIRECTORY_SEPARATOR
            );

            if ($this->errorsReport === []) {
                session()->flash(
                    'success',
                    "Excel corregido. Se aplicaron {$this->totalCorrections} correcciones automáticas y se generó el ZIP para SIGIRES sin pendientes internos."
                );
            } else {
                session()->flash(
                    'warning',
                    "Excel corregido y ZIP generado para SIGIRES. Se aplicaron {$this->totalCorrections} correcciones automáticas y quedan ".
                    count($this->errorsReport).
                    ' pendientes internos. Puedes cargar el ZIP en SIGIRES y luego corregiremos la devolución real de la EPS en el corrector.'
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->analyzed = false;
            $this->summary = [];
            $this->errorsReport = [];
            $this->correctedFile = null;
            $this->correctedDownloadName = null;
            $this->corrections = [];
            $this->totalCorrections = 0;
            $this->txtPath = null;
            $this->zipPath = null;

            $this->addError(
                'archivo',
                'No fue posible validar y preparar el reporte semanal: '.
                $exception->getMessage()
            );
        }
    }

    /**
     * Compatibilidad con el botón/flujo anterior.
     */
    public function autoCorrect(
        GestantesExcelReader $reader,
        GestantesValidationService $validator,
        GestantesAutoCorrector $corrector,
        GestantesTxtGenerator $txtGenerator,
        GestantesZipGenerator $zipGenerator
    ): void {
        $this->analyze(
            $reader,
            $validator,
            $corrector,
            $txtGenerator,
            $zipGenerator
        );
    }

    public function downloadCorrectedExcel(): BinaryFileResponse
    {
        if (
            ! is_string($this->correctedFile)
            || $this->correctedFile === ''
            || ! is_file($this->correctedFile)
        ) {
            throw new RuntimeException(
                'No se encontró el Excel corregido. Ejecuta primero "Validar y corregir".'
            );
        }

        return response()->download(
            $this->correctedFile,
            $this->correctedDownloadName ?: basename($this->correctedFile),
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function downloadTxt()
    {
        abort_unless(
            $this->txtPath
            && Storage::disk('local')->exists($this->txtPath),
            404,
            'No se encontró el archivo TXT.'
        );

        return Storage::disk('local')->download(
            $this->txtPath
        );
    }

    public function downloadZip()
    {
        abort_unless(
            $this->zipPath
            && Storage::disk('local')->exists($this->zipPath),
            404,
            'No se encontró el archivo ZIP.'
        );

        return Storage::disk('local')->download(
            $this->zipPath
        );
    }

    public function updatedArchivo(): void
    {
        $this->resetValidation();
        $this->resetProcessResults();
        $this->uploadedPath = null;
    }

    private function resetProcessResults(): void
    {
        $this->analyzed = false;
        $this->summary = [];
        $this->errorsReport = [];
        $this->correctedFile = null;
        $this->correctedDownloadName = null;
        $this->corrections = [];
        $this->totalCorrections = 0;
        $this->txtPath = null;
        $this->zipPath = null;
    }

    public function render()
    {
        return view('livewire.informes.gestantes-uploader');
    }
}
