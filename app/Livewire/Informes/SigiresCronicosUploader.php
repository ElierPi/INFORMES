<?php

namespace App\Livewire\Informes;

use App\Services\Sigires\Cronicos\SigiresCronicosPreparationService;
use App\Services\Sigires\Cronicos\SigiresCronicosReviewedMatrixService;
use App\Support\Livewire\HandlesTemporaryUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class SigiresCronicosUploader extends Component
{
    use WithFileUploads;
    use HandlesTemporaryUploads;

    public $baseUsuarios = null;

    public $historiasClinicas = null;

    public $matrizCompletada = null;

    public string $fechaCorte = '2026-06-30';

    public string $codigoEapb = '';

    public string $procedimiento = 'PRECURSORAS';

    public bool $procesando = false;

    public bool $analizado = false;

    public ?string $mensaje = null;

    public ?string $error = null;

    /** @var array<string, mixed> */
    public array $resumen = [];

    /** @var array<int, string> */
    public array $advertencias = [];

    /** @var array<int, array<string, mixed>> */
    public array $pacientes = [];

    public ?string $matrizPath = null;

    public ?string $txtPath = null;

    public ?string $zipPath = null;

    public ?string $generatedFileName = null;

    protected function rules(): array
    {
        return [
            'baseUsuarios' => ['required'],
            'historiasClinicas' => ['required'],
            'fechaCorte' => [
                'required',
                'date_format:Y-m-d',
            ],
            'codigoEapb' => [
                'required',
                'regex:/^[A-Za-z0-9]{1,6}$/',
            ],
            'procedimiento' => [
                'required',
                'in:PRECURSORAS,DIALISIS,TMND,NEFRO,TRASPLANTE',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'baseUsuarios.required' =>
                'Selecciona la base regional de usuarios.',
            'baseUsuarios.mimes' =>
                'La base regional debe estar en formato XLSX o XLS.',
            'baseUsuarios.max' =>
                'La base regional no puede superar los 40 MB.',
            'historiasClinicas.required' =>
                'Selecciona el ZIP de historias clínicas.',
            'historiasClinicas.mimes' =>
                'Las historias clínicas deben estar comprimidas en un archivo ZIP.',
            'historiasClinicas.max' =>
                'El ZIP de historias clínicas no puede superar los 150 MB.',
            'fechaCorte.required' =>
                'Selecciona la fecha de corte.',
            'fechaCorte.date_format' =>
                'La fecha de corte debe tener el formato AAAA-MM-DD.',
            'codigoEapb.required' =>
                'Ingresa el código de la EAPB.',
            'codigoEapb.regex' =>
                'El código EAPB debe contener entre 1 y 6 letras o números.',
            'procedimiento.in' =>
                'El procedimiento seleccionado no es válido.',
        ];
    }

    public function updatedBaseUsuarios(): void
    {
        $this->limpiarResultados();
        $this->resetValidation('baseUsuarios');
    }

    public function updatedHistoriasClinicas(): void
    {
        $this->limpiarResultados();
        $this->resetValidation('historiasClinicas');
    }

    public function updatedMatrizCompletada(): void
    {
        $this->error = null;
        $this->resetValidation('matrizCompletada');
    }

    public function preparar(
        SigiresCronicosPreparationService $preparationService
    ): void {
        $this->procesando = true;
        $this->mensaje = 'Analizando la base y las historias clínicas...';
        $this->error = null;
        $this->limpiarResultados(preserveMessage: true);

        try {
            $this->validate();

            $folder = 'private/uploads/sigires-cronicos/'.Str::uuid();
            $basePath = $this->stabilizeUpload(
                $this->baseUsuarios,
                ['xlsx', 'xls'],
                40 * 1024 * 1024,
                $folder,
                'base_usuarios'
            );
            $historiesPath = $this->stabilizeUpload(
                $this->historiasClinicas,
                ['zip'],
                150 * 1024 * 1024,
                $folder,
                'historias_clinicas'
            );

            $result = $preparationService->prepare(
                basePath: $basePath,
                historiesZipPath: $historiesPath,
                cutoffDate: $this->fechaCorte,
                eapbCode: mb_strtoupper(trim($this->codigoEapb)),
                procedure: $this->procedimiento
            );

            $this->resumen = is_array($result['summary'] ?? null)
                ? $result['summary']
                : [];
            $this->advertencias = array_values(array_map(
                static fn (mixed $warning): string => (string) $warning,
                is_array($result['warnings'] ?? null)
                    ? $result['warnings']
                    : []
            ));
            $this->pacientes = $this->simplificarPacientes(
                is_array($result['patients'] ?? null)
                    ? $result['patients']
                    : []
            );

            $this->matrizPath = $this->normalizarRuta(
                $result['review_path'] ?? null
            );
            $this->txtPath = $this->normalizarRuta(
                $result['txt_path'] ?? null
            );
            $this->zipPath = $this->normalizarRuta(
                $result['zip_path'] ?? null
            );
            $this->generatedFileName = isset(
                $result['generated_file_name']
            )
                ? (string) $result['generated_file_name']
                : null;

            $this->analizado = true;
            $this->mensaje = $this->zipPath !== null
                ? 'Todos los registros están completos. Se generaron el TXT y el ZIP.'
                : 'La matriz de revisión fue generada. Completa o confirma los campos pendientes antes del cargue definitivo.';
        } catch (Throwable $exception) {
            report($exception);

            $this->error =
                'No fue posible preparar el informe SIGIRES: '.
                $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function generarDesdeMatriz(
        SigiresCronicosReviewedMatrixService $reviewedMatrixService
    ): void {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Validando la matriz completada...';

        try {
            if ($this->matrizCompletada === null) {
                throw new RuntimeException(
                    'Selecciona la matriz de revisión completada.'
                );
            }

            $folder = 'private/uploads/sigires-cronicos/'.Str::uuid();
            $matrixPath = $this->stabilizeUpload(
                $this->matrizCompletada,
                ['xlsx'],
                40 * 1024 * 1024,
                $folder,
                'matriz_completada'
            );

            $result = $reviewedMatrixService->generate(
                $matrixPath,
                $this->fechaCorte,
                $this->procedimiento
            );

            $this->txtPath = $this->normalizarRuta($result['txt_path'] ?? null);
            $this->zipPath = $this->normalizarRuta($result['zip_path'] ?? null);
            $this->generatedFileName = isset($result['file_name'])
                ? (string) $result['file_name']
                : null;
            $this->resumen['ready_total'] = (int) ($result['records_total'] ?? 0);
            $this->resumen['pending_total'] = 0;
            $this->resumen['pending_fields_total'] = 0;
            $this->mensaje = 'La matriz quedó validada. Se generaron el TXT y el ZIP definitivos.';
            $this->analizado = true;
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible generar el informe desde la matriz: '.
                $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarMatriz()
    {
        return $this->descargarArchivo(
            $this->matrizPath,
            'MATRIZ_REVISION_SIGIRES_PRECURSORAS.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function descargarTxt()
    {
        $name = $this->generatedFileName !== null
            ? $this->generatedFileName.'.txt'
            : null;

        return $this->descargarArchivo(
            $this->txtPath,
            $name,
            'text/plain; charset=Windows-1252'
        );
    }

    public function descargarZip()
    {
        $name = $this->generatedFileName !== null
            ? $this->generatedFileName.'.zip'
            : null;

        return $this->descargarArchivo(
            $this->zipPath,
            $name,
            'application/zip'
        );
    }

    public function reiniciar(): void
    {
        $this->reset([
            'baseUsuarios',
            'historiasClinicas',
            'matrizCompletada',
            'procesando',
            'analizado',
            'mensaje',
            'error',
            'resumen',
            'advertencias',
            'pacientes',
            'matrizPath',
            'txtPath',
            'zipPath',
            'generatedFileName',
        ]);

        $this->fechaCorte = '2026-06-30';
        $this->procedimiento = 'PRECURSORAS';
        $this->resetValidation();
    }

    public function render()
    {
        return view(
            'livewire.informes.sigires-cronicos-uploader'
        );
    }

    private function validarArchivosTemporales(): void
    {
        if (! $this->baseUsuarios instanceof TemporaryUploadedFile) {
            throw new RuntimeException(
                'La base regional ya no está disponible. Vuelve a seleccionarla.'
            );
        }

        if (! $this->historiasClinicas instanceof TemporaryUploadedFile) {
            throw new RuntimeException(
                'El ZIP de historias clínicas ya no está disponible. Vuelve a seleccionarlo.'
            );
        }
    }

    private function guardarTemporal(
        TemporaryUploadedFile $file,
        string $folder,
        string $baseName
    ): string {
        $extension = strtolower($file->getClientOriginalExtension());
        $relativePath = $file->storeAs(
            $folder,
            $baseName.'.'.$extension,
            'local'
        );

        if (! is_string($relativePath) || $relativePath === '') {
            throw new RuntimeException(
                'No fue posible guardar temporalmente uno de los archivos.'
            );
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! is_file($absolutePath)) {
            throw new RuntimeException(
                'El archivo temporal no está disponible para procesarlo.'
            );
        }

        return $absolutePath;
    }

    /**
     * @param  array<int, array<string, mixed>>  $patients
     * @return array<int, array<string, mixed>>
     */
    private function simplificarPacientes(array $patients): array
    {
        $rows = [];

        foreach ($patients as $patient) {
            $base = is_array($patient['base'] ?? null)
                ? $patient['base']
                : [];
            $history = is_array($patient['history'] ?? null)
                ? $patient['history']
                : [];
            $build = is_array($patient['build'] ?? null)
                ? $patient['build']
                : [];

            $rows[] = [
                'document_type' => (string) (
                    $base['document_type']
                    ?? $history['document_type_history']
                    ?? $history['document_type_file']
                    ?? ''
                ),
                'document_number' => (string) (
                    $history['document_number']
                    ?? $base['document_number']
                    ?? ''
                ),
                'name' => trim(implode(' ', array_filter([
                    $base['first_name'] ?? null,
                    $base['second_name'] ?? null,
                    $base['first_surname'] ?? null,
                    $base['second_surname'] ?? null,
                ]))),
                'provider' => (string) ($base['provider_name'] ?? ''),
                'hta' => (bool) ($history['hta'] ?? false)
                    || $this->isMarked($base['hta'] ?? null),
                'dm' => (bool) ($history['dm'] ?? false)
                    || $this->isMarked($base['dm'] ?? null),
                'erc' => (bool) ($history['erc'] ?? false)
                    || $this->isMarked($base['erc'] ?? null),
                'pending' => count($build['pending'] ?? []),
                'pending_items' => array_values(is_array($build['pending'] ?? null)
                    ? $build['pending']
                    : []),
                'validation_issues' => count(
                    $patient['validation_issues'] ?? []
                ),
                'validation_items' => array_values(
                    is_array($patient['validation_issues'] ?? null)
                        ? $patient['validation_issues']
                        : []
                ),
                'clinical_values' => [
                    'attention_date' => $history['attention_date'] ?? null,
                    'weight' => $history['weight'] ?? null,
                    'height' => $history['height'] ?? null,
                    'pas' => $history['pas'] ?? null,
                    'pad' => $history['pad'] ?? null,
                    'creatinine' => $history['labs']['creatinine']['value'] ?? null,
                    'hba1c' => $history['labs']['hba1c']['value'] ?? null,
                    'tfg' => $history['labs']['tfg']['value'] ?? null,
                ],
                'warnings' => array_values(array_map(
                    static fn (mixed $warning): string => (string) $warning,
                    is_array($build['warnings'] ?? null)
                        ? $build['warnings']
                        : []
                )),
                'ready' => (bool) ($build['ready'] ?? false),
                'file_name' => (string) ($history['file_name'] ?? ''),
            ];
        }

        return $rows;
    }

    private function isMarked(mixed $value): bool
    {
        $normalized = mb_strtoupper(trim((string) $value));

        return in_array(
            $normalized,
            ['1', 'SI', 'SÍ', 'S', 'X', 'TRUE'],
            true
        );
    }

    private function normalizarRuta(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return $path;
    }

    private function descargarArchivo(
        ?string $path,
        ?string $downloadName,
        string $contentType
    ) {
        if ($path === null || ! is_file($path)) {
            $this->error =
                'El archivo ya no está disponible. Procesa nuevamente los insumos.';

            return null;
        }

        return response()->download(
            $path,
            $downloadName ?: basename($path),
            ['Content-Type' => $contentType]
        );
    }

    private function limpiarResultados(bool $preserveMessage = false): void
    {
        $this->analizado = false;
        $this->resumen = [];
        $this->advertencias = [];
        $this->pacientes = [];
        $this->matrizPath = null;
        $this->txtPath = null;
        $this->zipPath = null;
        $this->generatedFileName = null;
        $this->error = null;

        if (! $preserveMessage) {
            $this->mensaje = null;
        }
    }
}
