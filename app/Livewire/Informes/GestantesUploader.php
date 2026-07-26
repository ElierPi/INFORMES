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

    public ?string $txtPath = null;

    public ?string $zipPath = null;

    /**
     * Ruta relativa del Excel original almacenado.
     */
    public ?string $uploadedPath = null;

    /**
     * Ruta absoluta del Excel corregido.
     */
    public ?string $correctedFile = null;

    public array $corrections = [];

    public int $totalCorrections = 0;

    /**
     * Analiza el archivo, valida su contenido y genera TXT/ZIP
     * únicamente cuando no existen errores.
     */
    public function analyze(
        GestantesExcelReader $reader,
        GestantesValidationService $validator,
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
        ]);

        try {
            /*
             * Guardamos una copia permanente del archivo cargado.
             * Esto permite corregirlo y descargarlo en solicitudes
             * posteriores de Livewire.
             */
            $this->uploadedPath = $this->archivo->store(
                'gestantes/uploads',
                'local'
            );

            $absolutePath = Storage::disk('local')->path(
                $this->uploadedPath
            );

            if (! is_file($absolutePath)) {
                throw new RuntimeException(
                    'No fue posible almacenar el archivo Excel cargado.'
                );
            }

            $data = $reader->read($absolutePath);
            $result = $validator->validate($data);

            $this->errorsReport = $result['errors'] ?? [];
            $this->summary = $result['summary'] ?? [];
            $this->analyzed = true;

            /*
             * Limpiamos archivos TXT y ZIP anteriores.
             */
            $this->txtPath = null;
            $this->zipPath = null;

            /*
             * Si existen errores, se detiene la generación.
             */
            if (! ($result['valid'] ?? false)) {
                return;
            }

$timestamp = now()->format('Ymd_His');

$relativeDirectory =
    "gestantes/generated/{$timestamp}";

/*
 * Ruta temporal.
 *
 * El GestantesTxtGenerator reemplazará este nombre
 * por el nombre oficial:
 *
 * GESTANTE_MSPS_CODIGOHABILITACION_DDMMAAAA.txt
 */
$temporaryTxtRelativePath =
    "{$relativeDirectory}/gestantes.txt";

$temporaryTxtAbsolutePath = Storage::disk('local')->path(
    $temporaryTxtRelativePath
);

/*
 * Guardamos la ruta real que devuelve el generador.
 */
$generatedTxtAbsolutePath = $txtGenerator->generate(
    data: $data,
    outputPath: $temporaryTxtAbsolutePath
);

if (! is_file($generatedTxtAbsolutePath)) {
    throw new RuntimeException(
        'El generador terminó, pero no se encontró el TXT generado.'
    );
}

/*
 * El ZIP tendrá exactamente el mismo nombre base que el TXT.
 */
$generatedFileName = basename(
    $generatedTxtAbsolutePath
);

$generatedBaseName = pathinfo(
    $generatedFileName,
    PATHINFO_FILENAME
);

$generatedDirectory = dirname(
    $generatedTxtAbsolutePath
);

$generatedZipAbsolutePath =
    $generatedDirectory
    . DIRECTORY_SEPARATOR
    . $generatedBaseName
    . '.zip';

$generatedZipAbsolutePath = $zipGenerator->generate(
    txtPath: $generatedTxtAbsolutePath,
    zipPath: $generatedZipAbsolutePath
);

/*
 * Convertimos las rutas absolutas a rutas relativas
 * para poder descargarlas con Storage.
 */
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

            session()->flash(
                'success',
                'El archivo fue validado correctamente y se generaron el TXT y el ZIP.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->analyzed = false;
            $this->summary = [];
            $this->errorsReport = [];
            $this->txtPath = null;
            $this->zipPath = null;

            $this->addError(
                'archivo',
                'No fue posible analizar el archivo: '.
                $exception->getMessage()
            );
        }
    }

    /**
     * Corrige automáticamente los errores permitidos.
     */
    public function autoCorrect(
        GestantesAutoCorrector $corrector
    ): void {
        $this->resetErrorBag('correction');

        try {
            $sourcePath = $this->resolveSourcePath();

            $result = $corrector->correct($sourcePath);

            /*
             * El servicio puede devolver claves en español o inglés.
             */
            $this->correctedFile = $result['ruta']
                ?? $result['path']
                ?? null;

            $this->corrections = $result['correcciones']
                ?? $result['corrections']
                ?? [];

            $this->totalCorrections = (int) (
                $result['total_corrections']
                ?? $result['totalCorrections']
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

            session()->flash(
                'success',
                "Se realizaron {$this->totalCorrections} ".
                'correcciones automáticas. Ya puedes descargar el Excel corregido.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->correctedFile = null;
            $this->corrections = [];
            $this->totalCorrections = 0;

            $this->addError(
                'correction',
                'No fue posible corregir el archivo: '.
                $exception->getMessage()
            );
        }
    }

    /**
     * Descarga el Excel corregido.
     */
    public function downloadCorrectedExcel(): BinaryFileResponse
    {
        if (
            ! is_string($this->correctedFile)
            || $this->correctedFile === ''
            || ! is_file($this->correctedFile)
        ) {
            throw new RuntimeException(
                'No se encontró el archivo Excel corregido. '.
                'Ejecuta primero la corrección automática.'
            );
        }

        return response()->download(
            $this->correctedFile,
            basename($this->correctedFile),
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    /**
     * Descarga el TXT generado.
     */
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

    /**
     * Descarga el ZIP generado.
     */
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

    /**
     * Limpia resultados anteriores al seleccionar otro Excel.
     */
    public function updatedArchivo(): void
    {
        $this->resetValidation();

        $this->analyzed = false;
        $this->summary = [];
        $this->errorsReport = [];

        $this->txtPath = null;
        $this->zipPath = null;
        $this->uploadedPath = null;

        $this->correctedFile = null;
        $this->corrections = [];
        $this->totalCorrections = 0;
    }

    /**
     * Obtiene la ruta absoluta del Excel que será corregido.
     */
    private function resolveSourcePath(): string
    {
        /*
         * Preferimos el archivo almacenado durante el análisis.
         */
        if (
            $this->uploadedPath
            && Storage::disk('local')->exists($this->uploadedPath)
        ) {
            return Storage::disk('local')->path(
                $this->uploadedPath
            );
        }

        /*
         * También permitimos corregir sin analizar previamente.
         */
        if (! $this->archivo) {
            throw new RuntimeException(
                'Primero debes seleccionar un archivo Excel.'
            );
        }

        $this->validate([
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:30720',
            ],
        ]);

        $this->uploadedPath = $this->archivo->store(
            'gestantes/uploads',
            'local'
        );

        $absolutePath = Storage::disk('local')->path(
            $this->uploadedPath
        );

        if (! is_file($absolutePath)) {
            throw new RuntimeException(
                'No fue posible acceder al archivo Excel cargado.'
            );
        }

        return $absolutePath;
    }

    public function render()
    {
        return view(
            'livewire.informes.gestantes-uploader'
        );
    }
}