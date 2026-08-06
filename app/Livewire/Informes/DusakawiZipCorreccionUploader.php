<?php

namespace App\Livewire\Informes;

use App\Services\Informe202\Dusakawi\DusakawiZipCorrectionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class DusakawiZipCorreccionUploader extends Component
{
    use WithFileUploads;

    public $archivoZip = null;

    public $archivoErrores = null;

    public string $fechaCorte = '';

    public bool $procesando = false;

    public bool $procesado = false;

    public ?string $mensaje = null;

    public ?string $errorProceso = null;

    public ?string $zipCorregidoPath = null;

    public ?string $zipCorregidoName = null;

    public array $correcciones = [];

    public array $pendientes = [];

    public array $validos = [];

    public array $resumen = [];

    public function mount(): void
    {
        $this->fechaCorte = now()
            ->endOfMonth()
            ->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'archivoZip' => [
                'required',
                'file',
                'mimes:zip',
                'max:51200',
            ],

'archivoErrores' => [
    'required',
    'file',
    'max:20480',
    function (
        string $attribute,
        mixed $value,
        \Closure $fail
    ): void {
        if (
            ! $value instanceof
                \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
        ) {
            $fail(
                'No fue posible recibir el archivo de errores.'
            );

            return;
        }

        $extension = strtolower(
            $value->getClientOriginalExtension()
        );

        if (
            ! in_array(
                $extension,
                [
                    'xlsx',
                    'xls',
                    'html',
                    'htm',
                    'txt',
                ],
                true
            )
        ) {
            $fail(
                'El archivo de errores debe ser '
                . 'XLSX, XLS, HTML o TXT.'
            );
        }
    },
],

            'fechaCorte' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'archivoZip.required' =>
                'Selecciona el ZIP original de Dusakawi.',

            'archivoZip.mimes' =>
                'El informe original debe estar en formato ZIP.',

            'archivoErrores.required' =>
                'Selecciona el archivo de errores de Dusakawi.',

            'archivoErrores.mimes' =>
                'El archivo de errores debe ser XLSX, XLS, HTML o TXT.',

            'fechaCorte.required' =>
                'Selecciona la fecha de corte del informe.',
        ];
    }

    public function procesar(
        DusakawiZipCorrectionService $service
    ): void {
        $this->resetErrorBag();
        $this->limpiarResultados();
        $this->procesando = true;
        $this->mensaje = 'Procesando el ZIP de Dusakawi...';

        try {
            $this->validate();

            $this->validarTemporal(
                $this->archivoZip,
                'ZIP original'
            );

            $this->validarTemporal(
                $this->archivoErrores,
                'archivo de errores'
            );

            $folder = 'informes-202/dusakawi/'
                . Str::uuid();

            Storage::disk('local')
                ->makeDirectory($folder);
$nombreZipOriginal = basename(
    $this->archivoZip->getClientOriginalName()
);

$nombreZipOriginal = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    $nombreZipOriginal
) ?: 'DUSAKAWI.zip';

if (
    strtolower(
        pathinfo(
            $nombreZipOriginal,
            PATHINFO_EXTENSION
        )
    ) !== 'zip'
) {
    $nombreZipOriginal .= '.zip';
}

$zipRelative = $this->guardarTemporal(
    archivo: $this->archivoZip,
    folder: $folder,
    filename: $nombreZipOriginal
);

            $errorExtension = strtolower(
                $this->archivoErrores
                    ->getClientOriginalExtension()
            );

            $errorRelative = $this->guardarTemporal(
                archivo: $this->archivoErrores,
                folder: $folder,
                filename:
                    'errores.' . $errorExtension
            );

            $outputRelative = $folder . '/salida';

            Storage::disk('local')
                ->makeDirectory($outputRelative);

            $result = $service->correct(
                inputZip: Storage::disk('local')
                    ->path($zipRelative),
                errorFile: Storage::disk('local')
                    ->path($errorRelative),
                cutoffDate: $this->fechaCorte,
                outputDirectory: Storage::disk('local')
                    ->path($outputRelative)
            );

            $outputAbsolute = $result['output_zip'];

            if (! is_file($outputAbsolute)) {
                throw new RuntimeException(
                    'No se encontró el ZIP corregido generado.'
                );
            }

            $localRoot = rtrim(
                Storage::disk('local')->path(''),
                DIRECTORY_SEPARATOR
            );

            $this->zipCorregidoPath = ltrim(
                str_replace(
                    $localRoot,
                    '',
                    $outputAbsolute
                ),
                DIRECTORY_SEPARATOR
            );

            $this->zipCorregidoName =
                basename($outputAbsolute);

            $this->correcciones =
                $result['corrections'];

            $this->pendientes =
                $result['pending'];

            $this->validos =
                $result['valid'];

            $this->resumen = [
                'archivo_interno' =>
                    $result['internal_file'],

                'registros_leidos' =>
                    $result['total_records'],

                'errores_reportados' =>
                    $result['total_errors'],

                'celdas_corregidas' =>
                    count($this->correcciones),

                'pendientes' =>
                    count($this->pendientes),

                'ya_validos' =>
                    count($this->validos),
            ];

            $this->procesado = true;
            $this->mensaje =
                'El ZIP de Dusakawi fue corregido correctamente.';
        } catch (Throwable $exception) {
            report($exception);

            $this->errorProceso =
                'No fue posible procesar los archivos: '
                . $exception->getMessage();

            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

public function descargar(): StreamedResponse
{
    if (
        $this->zipCorregidoPath === null
        || ! Storage::disk('local')
            ->exists($this->zipCorregidoPath)
    ) {
        throw new RuntimeException(
            'El ZIP corregido ya no está disponible. '
            . 'Procesa nuevamente los archivos.'
        );
    }

    return Storage::disk('local')->download(
        $this->zipCorregidoPath,
        $this->zipCorregidoName
            ?? 'DUSAKAWI_CORREGIDO.zip',
        [
            'Content-Type' => 'application/zip',
        ]
    );
}

    private function validarTemporal(
        mixed $file,
        string $label
    ): void {
        if (! $file instanceof TemporaryUploadedFile) {
            throw new RuntimeException(
                "No fue posible recibir el {$label}. "
                . 'Vuelve a seleccionarlo.'
            );
        }

        $path = $file->getRealPath();

        if (
            ! is_string($path)
            || ! is_file($path)
        ) {
            throw new RuntimeException(
                "El {$label} temporal no está disponible."
            );
        }
    }

    private function guardarTemporal(
        TemporaryUploadedFile $archivo,
        string $folder,
        string $filename
    ): string {
        $stored = $archivo->storeAs(
            $folder,
            $filename,
            'local'
        );

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException(
                'No fue posible guardar uno de los archivos cargados.'
            );
        }

        return $stored;
    }

    private function limpiarResultados(): void
    {
        $this->procesado = false;
        $this->errorProceso = null;
        $this->zipCorregidoPath = null;
        $this->zipCorregidoName = null;
        $this->correcciones = [];
        $this->pendientes = [];
        $this->validos = [];
        $this->resumen = [];
    }

    public function render()
    {
        return view(
            'livewire.informes.dusakawi-zip-correccion-uploader'
        );
    }
}
