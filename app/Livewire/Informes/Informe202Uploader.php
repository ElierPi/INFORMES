<?php

namespace App\Livewire\Informes;

use App\Services\Informe202\Informe202CorrectionService;
use App\Services\Informe202\Parsers\ErrorParserManager;
use App\Services\Informe202\Resolution202ExcelReader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Informe202Uploader extends Component
{
    use WithFileUploads;

    public $archivoInforme = null;

    public $archivoErrores = null;

    public string $eps = 'proteger';

    public bool $procesando = false;

    public ?string $mensajeProceso = null;

    public ?string $errorProceso = null;

    /*
     * La edad se calcula contra esta fecha,
     * nunca contra la fecha actual.
     */
    public string $fechaCorte = '';

    public array $errores = [];

    public array $correcciones = [];

    public array $pendientes = [];

    public array $registrosLeidos = [];

    public array $erroresEdad = [];

    public array $resumen = [];

    public bool $analizado = false;

    public ?string $archivoCorregido = null;

    public function mount(): void
    {
        $this->fechaCorte = '';
    }

    protected function rules(): array
    {
        /*
         * No se validan aquí "file" ni "max".
         *
         * Esas reglas obligan a Livewire/Flysystem a consultar
         * metadatos del archivo temporal antes de copiarlo y pueden
         * producir UnableToRetrieveMetadata cuando el temporal ya no
         * está disponible.
         *
         * Los archivos se comprueban y almacenan explícitamente
         * dentro de analizar().
         */
        return [
            'eps' => [
                'required',
                'in:proteger,dusakawi',
            ],

            'fechaCorte' => [
                'required',
                'date_format:Y-m-d',
            ],

            'archivoInforme' => [
                'required',
            ],

            'archivoErrores' => [
                'required',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'fechaCorte.required' =>
                'Selecciona la fecha de corte del informe.',

            'fechaCorte.date_format' =>
                'La fecha de corte debe tener el formato AAAA-MM-DD.',

            'archivoInforme.required' =>
                'Selecciona el Excel original de la Resolución 202.',

            'archivoErrores.required' =>
                'Selecciona el reporte de errores de la EPS.',
        ];
    }

    public function analizar(): void
    {
        $this->procesando = true;
        $this->mensajeProceso = 'Analizando archivos...';
        $this->errorProceso = null;

        try {
            $this->validate();

            $this->resetResultados();

            $this->validarArchivoTemporal(
                archivo: $this->archivoInforme,
                campo: 'archivoInforme',
                nombre: 'Excel original'
            );

            $this->validarArchivoTemporal(
                archivo: $this->archivoErrores,
                campo: 'archivoErrores',
                nombre: 'reporte de errores'
            );

            $extensionInforme = strtolower(
                $this->archivoInforme->getClientOriginalExtension()
            );

            if (! in_array($extensionInforme, ['xlsx', 'xls'], true)) {
                throw new \RuntimeException(
                    'El archivo de la Resolución 202 debe ser XLSX o XLS.'
                );
            }

            $extensionErrores = strtolower(
                $this->archivoErrores->getClientOriginalExtension()
            );

            if (! in_array(
                $extensionErrores,
                ['xlsx', 'xls', 'html', 'htm', 'txt'],
                true
            )) {
                throw new \RuntimeException(
                    'El reporte de errores debe ser XLSX, XLS, HTML o TXT.'
                );
            }

            /*
             * Cada procesamiento queda en una carpeta independiente.
             */
            $folder = 'informes-202/' . Str::uuid();

            Storage::disk('local')->makeDirectory($folder);

            /*
             * Se copian inmediatamente los temporales al disco local.
             * Después de este punto, el proceso ya no depende de
             * livewire-tmp.
             */
            $originalRelativePath = $this->guardarTemporal(
                archivo: $this->archivoInforme,
                folder: $folder,
                filename: 'original.' . $extensionInforme
            );

            $errorRelativePath = $this->guardarTemporal(
                archivo: $this->archivoErrores,
                folder: $folder,
                filename: 'errores.' . $extensionErrores
            );

            $correctedRelativePath =
                "{$folder}/INFORME_202_CORREGIDO.xlsx";

            $originalPath = Storage::disk('local')
                ->path($originalRelativePath);

            $errorPath = Storage::disk('local')
                ->path($errorRelativePath);

            $correctedPath = Storage::disk('local')
                ->path($correctedRelativePath);

            if (! is_file($originalPath)) {
                throw new \RuntimeException(
                    'No fue posible guardar el Excel original. '
                    . 'Vuelve a seleccionarlo e inténtalo nuevamente.'
                );
            }

            if (! is_file($errorPath)) {
                throw new \RuntimeException(
                    'No fue posible guardar el reporte de errores. '
                    . 'Vuelve a seleccionarlo e inténtalo nuevamente.'
                );
            }

            $reader = app(
                Resolution202ExcelReader::class
            );

            $excelResult = $reader->read(
                $originalPath,
                $this->fechaCorte
            );

            $this->registrosLeidos =
                $excelResult['records'];

            $this->erroresEdad = collect(
                $this->registrosLeidos
            )
                ->filter(
                    fn (array $record) =>
                        ! empty($record['age_error'])
                )
                ->map(
                    fn (array $record) => [
                        'registro' =>
                            $record['record_number'],

                        'fila_excel' =>
                            $record['excel_row'],

                        'fecha_nacimiento' =>
                            $record['birth_date'],

                        'detalle' =>
                            $record['age_error'],
                    ]
                )
                ->values()
                ->all();

            $parserManager = app(
                ErrorParserManager::class
            );

            $this->errores = $parserManager->parse(
                $this->eps,
                $errorPath
            );

            if ($this->errores === []) {
                throw new \RuntimeException(
                    'No se encontraron errores reconocibles '
                    . 'en el reporte de la EPS.'
                );
            }

            $correctionService = app(
                Informe202CorrectionService::class
            );

            $result = $correctionService->correct(
                $originalPath,
                $correctedPath,
                $this->errores,
                $excelResult
            );

            $this->correcciones =
                $result['correcciones'];

            $this->pendientes =
                $result['pendientes'];

            $validos = $result['validos'] ?? [];

            $this->archivoCorregido =
                $correctedRelativePath;

            $this->resumen = [
                'registros_leidos' =>
                    $excelResult['total_records'],

                'errores_ya_validos' =>
                    count($validos),

                'errores_reportados' =>
                    count($this->errores),

                'registros_afectados' =>
                    collect($this->errores)
                        ->pluck('fila')
                        ->unique()
                        ->count(),

                'celdas_corregidas' =>
                    count($this->correcciones),

                'pendientes' =>
                    count($this->pendientes),

                'errores_edad' =>
                    count($this->erroresEdad),
            ];

            $this->mensajeProceso =
                'El informe fue procesado correctamente.';

            $this->analizado = true;
        } catch (\Throwable $exception) {
            report($exception);

            $this->errorProceso =
                'No fue posible procesar los archivos: '
                . $exception->getMessage();

            $this->addError(
                'archivoInforme',
                $this->errorProceso
            );
        } finally {
            /*
             * Evita que la interfaz quede bloqueada si la validación,
             * la copia temporal o cualquier servicio lanza una excepción.
             */
            $this->procesando = false;
        }
    }

    public function descargarCorregido()
    {
        if (
            ! $this->archivoCorregido
            || ! Storage::disk('local')->exists(
                $this->archivoCorregido
            )
        ) {
            $this->addError(
                'archivoInforme',
                'El archivo corregido ya no está disponible.'
            );

            return null;
        }

        return Storage::disk('local')->download(
            $this->archivoCorregido,
            'INFORME_202_CORREGIDO.xlsx'
        );
    }

    public function limpiar(): void
    {
        $this->reset([
            'archivoInforme',
            'archivoErrores',
            'errores',
            'correcciones',
            'pendientes',
            'registrosLeidos',
            'erroresEdad',
            'resumen',
            'analizado',
            'archivoCorregido',
            'procesando',
            'mensajeProceso',
            'errorProceso',
        ]);

        $this->resetValidation();
    }

    private function resetResultados(): void
    {
        $this->reset([
            'errores',
            'correcciones',
            'pendientes',
            'registrosLeidos',
            'erroresEdad',
            'resumen',
            'analizado',
            'archivoCorregido',
        ]);

        $this->resetValidation();
    }

    private function validarArchivoTemporal(
        mixed $archivo,
        string $campo,
        string $nombre
    ): void {
        if (! $archivo instanceof TemporaryUploadedFile) {
            throw new \RuntimeException(
                "El {$nombre} no es un archivo temporal válido. "
                . 'Vuelve a seleccionarlo.'
            );
        }

        /*
         * getRealPath() no consulta el tamaño mediante Flysystem.
         * Solo confirma que el temporal todavía existe físicamente.
         */
        $realPath = $archivo->getRealPath();

        if (
            ! is_string($realPath)
            || $realPath === ''
            || ! is_file($realPath)
        ) {
            $this->reset($campo);

            throw new \RuntimeException(
                "El {$nombre} temporal dejó de estar disponible. "
                . 'Vuelve a seleccionarlo y pulsa Analizar nuevamente.'
            );
        }
    }

    private function guardarTemporal(
        TemporaryUploadedFile $archivo,
        string $folder,
        string $filename
    ): string {
        $realPath = $archivo->getRealPath();

        if (
            ! is_string($realPath)
            || ! is_file($realPath)
        ) {
            throw new \RuntimeException(
                "El archivo temporal {$filename} ya no está disponible."
            );
        }

        $relativePath = "{$folder}/{$filename}";

        $stream = fopen($realPath, 'rb');

        if ($stream === false) {
            throw new \RuntimeException(
                "No fue posible abrir el archivo temporal {$filename}."
            );
        }

        try {
            $stored = Storage::disk('local')->put(
                $relativePath,
                $stream
            );
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new \RuntimeException(
                "No fue posible guardar {$filename} en el disco local."
            );
        }

        return $relativePath;
    }

    public function render()
    {
        return view(
            'livewire.informes.informe202-uploader'
        );
    }
}