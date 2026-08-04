<?php

namespace App\Livewire\Informes;

use App\Services\Informe202\Informe202PreparationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class Resolucion202PreparacionUploader extends Component
{
    use WithFileUploads;

    public $archivo = null;

    public string $destino = 'proteger';

    public string $fechaCorte = '';

    public string $codigoEps = 'EPSI01';

    public string $fechaInicial = '';

    public string $fechaFinal = '';

    public string $ipsSeleccionada = 'cidsma';

    public ?string $zipPath = null;

    public ?string $zipName = null;

    public bool $procesando = false;

    public bool $analizado = false;

    public bool $generado = false;

    public array $errores = [];

    public array $advertencias = [];

    public array $resumen = [];

    public ?array $encabezadoDetectado = null;

    public ?string $txtPath = null;

    public ?string $txtName = null;

    public ?string $mensaje = null;

    public ?string $errorProceso = null;

    public function updatedDestino(): void
    {
        $this->limpiarResultados();
        $this->resetErrorBag();
    }

    public function updatedArchivo(): void
    {
        $this->limpiarResultados();
        $this->resetErrorBag();
    }

    public function analizar(
        Informe202PreparationService $service
    ): void {
        $this->resetErrorBag();
        $this->errorProceso = null;
        $this->mensaje = null;
        $this->procesando = true;

        try {
            $this->validate(
                $this->validationRules(),
                $this->validationMessages()
            );

            $this->validarTemporal(
                $this->archivo
            );

            $extension = mb_strtolower(
                $this->archivo
                    ->getClientOriginalExtension()
            );

            if (! in_array(
                $extension,
                ['xlsx', 'xls', 'txt'],
                true
            )) {
                throw new RuntimeException(
                    'Solo se permiten archivos XLSX, XLS o TXT.'
                );
            }

            if (
                in_array(
                    $extension,
                    ['xlsx', 'xls'],
                    true
                )
                && trim($this->fechaCorte) === ''
            ) {
                throw new RuntimeException(
                    'Selecciona la fecha de corte para procesar el Excel.'
                );
            }

            $this->limpiarResultados();

            $folder =
                'informes-202/preparacion/'
                . Str::uuid();

            Storage::disk('local')
                ->makeDirectory($folder);

            $relativeInput =
                $this->archivo->storeAs(
                    $folder,
                    'entrada.' . $extension,
                    'local'
                );

            if (! $relativeInput) {
                throw new RuntimeException(
                    'No fue posible guardar el archivo cargado.'
                );
            }

            $absoluteInput =
                Storage::disk('local')->path(
                    $relativeInput
                );

            $result = $service->prepare(
                inputPath: $absoluteInput,
                extension: $extension,
                destination: $this->destino,
                cutoffDate:
                    $this->fechaCorte !== ''
                        ? $this->fechaCorte
                        : null
            );

            $this->errores =
                $result['errors'];

            $this->advertencias =
                $result['warnings'];

            $this->resumen =
                $result['summary'];

            $this->encabezadoDetectado =
                $result['detected_header'];

            $this->analizado = true;

            if ($this->errores !== []) {
                $this->mensaje =
                    'El archivo tiene errores estructurales. Corrige las líneas indicadas antes de generar el TXT.';

                return;
            }

            /*
             * Si el TXT de entrada ya tenía registro tipo 1,
             * aprovecha sus datos para DUSAKAWI cuando los campos
             * visuales están vacíos.
             */
            if (
                $this->destino ===
                    Informe202PreparationService::DESTINATION_DUSAKAWI
                && $this->encabezadoDetectado !== null
            ) {
                $this->codigoEps =
                    $this->codigoEps !== ''
                        ? $this->codigoEps
                        : (string) (
                            $this->encabezadoDetectado['eps_code']
                            ?? ''
                        );

                $this->fechaInicial =
                    $this->fechaInicial !== ''
                        ? $this->fechaInicial
                        : (string) (
                            $this->encabezadoDetectado['start_date']
                            ?? ''
                        );

                $this->fechaFinal =
                    $this->fechaFinal !== ''
                        ? $this->fechaFinal
                        : (string) (
                            $this->encabezadoDetectado['end_date']
                            ?? ''
                        );
            }

            $outputRules = $this->outputValidationRules();

            if ($outputRules !== []) {
                $this->validate(
                    $outputRules,
                    $this->validationMessages()
                );
            }

            if ($this->destino === 'familiar_colombia') {
                $names = $this->buildFamiliarColombiaNames();

                $this->txtName = $names['txt'];
                $this->zipName = $names['zip'];

                $txtRelativePath =
                    $folder . '/' . $this->txtName;

                $zipRelativePath =
                    $folder . '/' . $this->zipName;

                $service->generateFamiliarColombiaZip(
                    records: $result['records'],
                    txtPath: Storage::disk('local')->path(
                        $txtRelativePath
                    ),
                    zipPath: Storage::disk('local')->path(
                        $zipRelativePath
                    )
                );

                $this->txtPath = $txtRelativePath;
                $this->zipPath = $zipRelativePath;
                $this->generado = true;
                $this->mensaje =
                    'El ZIP de Familiar de Colombia fue generado con TXT ANSI separado por |.';

                return;
            }

            $this->txtName =
                $this->buildOutputName();

            $txtRelativePath =
                $folder . '/' . $this->txtName;

            $txtAbsolutePath =
                Storage::disk('local')->path(
                    $txtRelativePath
                );

            $service->generateTxt(
                records: $result['records'],
                outputPath: $txtAbsolutePath,
                destination: $this->destino,
                headerData: [
                    'eps_code' =>
                        $this->codigoEps,

                    'start_date' =>
                        $this->fechaInicial,

                    'end_date' =>
                        $this->fechaFinal,
                ]
            );

            $this->txtPath =
                $txtRelativePath;

            $this->generado = true;

            $this->mensaje =
                $this->destino === 'dusakawi'
                    ? 'El TXT DUSAKAWI fue generado con registro tipo 1 y registros tipo 2.'
                    : 'El TXT PROTEGER fue generado únicamente con registros tipo 2.';
        } catch (Throwable $exception) {
            report($exception);

            $this->errorProceso =
                'No fue posible preparar el informe: '
                . $exception->getMessage();
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarTxt(): BinaryFileResponse
    {
        if (
            $this->txtPath === null
            || ! Storage::disk('local')
                ->exists($this->txtPath)
        ) {
            throw new RuntimeException(
                'No se encontró el TXT preparado.'
            );
        }

        return response()->download(
            Storage::disk('local')->path(
                $this->txtPath
            ),
            $this->txtName
                ?? 'INFORME_202_LISTO.txt',
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',
            ]
        );
    }

    public function descargarZip(): BinaryFileResponse
    {
        if (
            $this->zipPath === null
            || ! Storage::disk('local')
                ->exists($this->zipPath)
        ) {
            throw new RuntimeException(
                'No se encontró el ZIP preparado.'
            );
        }

        return response()->download(
            Storage::disk('local')->path(
                $this->zipPath
            ),
            $this->zipName
                ?? 'RESOLUCION_202.zip',
            [
                'Content-Type' =>
                    'application/zip',
            ]
        );
    }

    private function validationRules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:xlsx,xls,txt',
                'max:51200',
            ],

            'destino' => [
                'required',
                'in:proteger,dusakawi,familiar_colombia',
            ],

            'fechaCorte' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ];
    }

    private function outputValidationRules(): array
    {
        if ($this->destino === 'dusakawi') {
            return [
                'codigoEps' => [
                    'required',
                    'string',
                    'max:30',
                ],

                'fechaInicial' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'fechaFinal' => [
                    'required',
                    'date_format:Y-m-d',
                    'after_or_equal:fechaInicial',
                ],
            ];
        }

        if ($this->destino === 'familiar_colombia') {
            return [
                'ipsSeleccionada' => [
                    'required',
                    'in:cidsma,wayuu_anashii',
                ],

                'fechaCorte' => [
                    'required',
                    'date_format:Y-m-d',
                ],
            ];
        }

        return [];
    }

    private function validationMessages(): array
    {
        return [
            'archivo.required' =>
                'Selecciona un Excel o TXT.',

            'archivo.file' =>
                'El archivo seleccionado no es válido.',

            'archivo.mimes' =>
                'Solo se permiten XLSX, XLS o TXT.',

            'archivo.max' =>
                'El archivo no puede superar 50 MB.',

            'destino.required' =>
                'Selecciona la entidad destino.',

            'destino.in' =>
                'La entidad destino no es válida.',

            'fechaCorte.date_format' =>
                'La fecha de corte debe usar AAAA-MM-DD.',

            'codigoEps.required' =>
                'El código EPS es obligatorio para DUSAKAWI.',

            'fechaInicial.required' =>
                'La fecha inicial es obligatoria para DUSAKAWI.',

            'fechaInicial.date_format' =>
                'La fecha inicial debe usar AAAA-MM-DD.',

            'fechaFinal.required' =>
                'La fecha final es obligatoria para DUSAKAWI.',

            'fechaFinal.date_format' =>
                'La fecha final debe usar AAAA-MM-DD.',

            'fechaFinal.after_or_equal' =>
                'La fecha final no puede ser anterior a la fecha inicial.',

            'ipsSeleccionada.required' =>
                'Selecciona la IPS que reporta.',

            'ipsSeleccionada.in' =>
                'La IPS seleccionada no es válida.',

            'fechaCorte.required' =>
                'La fecha final del periodo es obligatoria.',
        ];
    }

    private function buildOutputName(): string
    {
        if ($this->destino === 'dusakawi') {
            $period = $this->fechaFinal !== ''
                ? str_replace(
                    '-',
                    '',
                    mb_substr(
                        $this->fechaFinal,
                        0,
                        7
                    )
                )
                : now()->format('Ym');

            return sprintf(
                'RESOLUCION_202_%s_%s.txt',
                preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '',
                    $this->codigoEps
                ) ?: 'DUSAKAWI',
                $period
            );
        }

        /*
         * PROTEGER exige el nombre NIT_MMYYYY.txt.
         * Ejemplo para julio de 2026:
         * 900144397_072026.txt
         */
        $period = $this->fechaCorte !== ''
            ? \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $this->fechaCorte
            )
            : false;

        if ($period === false) {
            throw new RuntimeException(
                'Selecciona una fecha de corte válida para generar el nombre del archivo de PROTEGER.'
            );
        }

        return '900144397_'
            . $period->format('mY')
            . '.txt';
    }

    private function buildFamiliarColombiaNames(): array
    {
        $codes = [
            'cidsma' => '444300120001',
            'wayuu_anashii' => '444300063502',
        ];

        $providerCode =
            $codes[$this->ipsSeleccionada]
            ?? null;

        if ($providerCode === null) {
            throw new RuntimeException(
                'No se encontró el código de habilitación de la IPS.'
            );
        }

        $period = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $this->fechaCorte
        );

        if ($period === false) {
            throw new RuntimeException(
                'Selecciona una fecha final de periodo válida.'
            );
        }

        $baseName =
            $providerCode
            . '_'
            . $period->format('dmY');

        return [
            'txt' => $baseName . '.txt',
            'zip' => $baseName . '.zip',
        ];
    }

    private function validarTemporal(
        mixed $archivo
    ): void {
        if (
            ! $archivo instanceof
                TemporaryUploadedFile
        ) {
            throw new RuntimeException(
                'El archivo temporal ya no está disponible. Selecciónalo nuevamente.'
            );
        }

        if (
            ! is_file(
                $archivo->getRealPath()
            )
        ) {
            throw new RuntimeException(
                'No fue posible acceder al archivo temporal.'
            );
        }
    }

    private function limpiarResultados(): void
    {
        $this->analizado = false;
        $this->generado = false;
        $this->errores = [];
        $this->advertencias = [];
        $this->resumen = [];
        $this->encabezadoDetectado = null;
        $this->txtPath = null;
        $this->txtName = null;
        $this->zipPath = null;
        $this->zipName = null;
        $this->mensaje = null;
        $this->errorProceso = null;
    }

    public function render()
    {
        return view(
            'livewire.informes.resolucion202-preparacion-uploader'
        );
    }
}
