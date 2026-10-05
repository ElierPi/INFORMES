<?php

namespace App\Livewire\Informes;

use App\Models\IpsNitGuardado;
use App\Services\Resolucion1552\Resolucion1552ProtegerExporter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

final class Resolucion1552ProtegerUploader extends Component
{
    use WithFileUploads;

    public $archivo = null;
    public string $periodo = '';
    public string $nitReceptor = '900144397';
    public ?int $ipsNitSeleccionado = null;
    public string $ipsNombre = '';
    public array $ipsNitsGuardados = [];
    public bool $procesando = false;
    public bool $generado = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public array $resumen = [];
    public array $erroresValidacion = [];
    public array $advertencias = [];
    public ?string $txtPath = null;
    public ?string $txtName = null;
    public ?string $zipPath = null;
    public ?string $zipName = null;

    public function mount(): void
    {
        $this->periodo = now()->subMonth()->format('Y-m');
        $this->cargarIpsNitsGuardados();

        if ($this->ipsNitsGuardados === []) {
            $registro = IpsNitGuardado::query()->firstOrCreate(
                [
                    'user_id' => auth()->id(),
                    'nit' => '900144397',
                ],
                [
                    'nombre' => null,
                    'last_used_at' => now(),
                ]
            );

            $this->cargarIpsNitsGuardados();
            $this->ipsNitSeleccionado = $registro->id;
            $this->nitReceptor = $registro->nit;
            return;
        }

        $primero = $this->ipsNitsGuardados[0];
        $this->ipsNitSeleccionado = (int) $primero['id'];
        $this->nitReceptor = (string) $primero['nit'];
        $this->ipsNombre = (string) ($primero['nombre'] ?? '');
    }

    public function updatedIpsNitSeleccionado(mixed $value): void
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            $this->ipsNitSeleccionado = null;
            $this->ipsNombre = '';
            $this->nitReceptor = '';
            $this->resetValidation(['nitReceptor', 'ipsNombre']);
            return;
        }

        $registro = IpsNitGuardado::query()
            ->where('user_id', auth()->id())
            ->find($id);

        if ($registro === null) {
            $this->ipsNitSeleccionado = null;
            return;
        }

        $this->nitReceptor = $registro->nit;
        $this->ipsNombre = (string) ($registro->nombre ?? '');
        $this->resetValidation(['nitReceptor', 'ipsNombre']);
    }

    public function guardarIpsNit(): void
    {
        $this->resetValidation(['nitReceptor', 'ipsNombre']);

        $this->validate([
            'nitReceptor' => ['required', 'regex:/^\d{9,12}$/'],
            'ipsNombre' => ['nullable', 'string', 'max:120'],
        ], [
            'nitReceptor.required' => 'Escribe el NIT que deseas guardar.',
            'nitReceptor.regex' => 'El NIT debe contener entre 9 y 12 dígitos, sin puntos ni guiones.',
            'ipsNombre.max' => 'El nombre de la IPS no puede superar 120 caracteres.',
        ]);

        $nit = preg_replace('/\D+/', '', $this->nitReceptor) ?? '';
        $nombre = trim($this->ipsNombre);

        $registro = null;

        if ($this->ipsNitSeleccionado !== null) {
            $registro = IpsNitGuardado::query()
                ->where('user_id', auth()->id())
                ->find($this->ipsNitSeleccionado);

            if ($registro !== null && $registro->nit !== $nit) {
                $registro = null;
                $this->ipsNitSeleccionado = null;
            }
        }

        $duplicado = IpsNitGuardado::query()
            ->where('user_id', auth()->id())
            ->where('nit', $nit)
            ->when($registro !== null, fn ($query) => $query->whereKeyNot($registro->id))
            ->exists();

        if ($duplicado) {
            $this->addError('nitReceptor', 'Ese NIT ya está guardado en otra IPS. Selecciónalo desde la lista.');
            return;
        }

        if ($registro === null) {
            $registro = new IpsNitGuardado();
            $registro->user_id = auth()->id();
        }

        $registro->nombre = $nombre !== '' ? $nombre : null;
        $registro->nit = $nit;
        $registro->last_used_at = now();
        $registro->save();

        $this->nitReceptor = $registro->nit;
        $this->ipsNitSeleccionado = $registro->id;
        $this->cargarIpsNitsGuardados();
        $this->mensaje = 'IPS/NIT guardado. Podrás seleccionarlo en próximos informes.';
        $this->error = null;
    }

    public function eliminarIpsNit(): void
    {
        if ($this->ipsNitSeleccionado === null) {
            return;
        }

        IpsNitGuardado::query()
            ->where('user_id', auth()->id())
            ->whereKey($this->ipsNitSeleccionado)
            ->delete();

        $this->ipsNitSeleccionado = null;
        $this->ipsNombre = '';
        $this->nitReceptor = '';
        $this->cargarIpsNitsGuardados();

        if ($this->ipsNitsGuardados !== []) {
            $primero = $this->ipsNitsGuardados[0];
            $this->ipsNitSeleccionado = (int) $primero['id'];
            $this->nitReceptor = (string) $primero['nit'];
            $this->ipsNombre = (string) ($primero['nombre'] ?? '');
        }

        $this->mensaje = 'IPS/NIT eliminado de tus guardados.';
        $this->error = null;
    }

    protected function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:30720'],
            'periodo' => ['required', 'date_format:Y-m'],
            'nitReceptor' => ['required', 'regex:/^\d{9,12}$/'],
        ];
    }

    public function generar(Resolucion1552ProtegerExporter $exporter): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Procesando el archivo...';
        $this->limpiarResultado();

        try {
            $this->validate();

            if (! $this->archivo instanceof TemporaryUploadedFile) {
                throw new RuntimeException('Selecciona nuevamente el archivo Excel.');
            }

            $extension = strtolower($this->archivo->getClientOriginalExtension());
            $folder = 'private/uploads/resolucion1552-proteger/' . Str::uuid();
            Storage::disk('local')->makeDirectory($folder);
            $relativePath = $this->archivo->storeAs($folder, 'entrada.' . $extension, 'local');
            $path = Storage::disk('local')->path((string) $relativePath);
            $result = $exporter->export($path, $this->periodo, $this->nitReceptor);
            $validation = $result['validation'] ?? [];
            $this->erroresValidacion = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            $this->advertencias = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];
            $statistics = is_array($validation['statistics'] ?? null) ? $validation['statistics'] : [];
            $this->resumen = [
                'registros' => (int) ($statistics['records_count'] ?? 0),
                'validos' => (int) ($statistics['valid_records_count'] ?? 0),
                'invalidos' => (int) ($statistics['invalid_records_count'] ?? 0),
                'errores' => (int) ($statistics['errors_count'] ?? 0),
                'advertencias' => (int) ($statistics['warnings_count'] ?? 0),
                'duplicados' => (int) ($statistics['duplicate_count'] ?? 0),
                'omitidos' => (int) ($statistics['skipped_rows_count'] ?? 0),
                'prestador' => (string) ($result['provider_code'] ?? ''),
            ];

            if (! ($result['success'] ?? false)) {
                $this->error = 'El Excel contiene errores que deben corregirse antes de generar el ZIP.';
                $this->mensaje = null;
                return;
            }

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->zipPath = (string) $result['zip_path'];
            $this->zipName = (string) $result['zip_name'];
            $this->marcarNitComoUsado();
            $this->generado = true;
            $this->mensaje = 'El TXT y el ZIP de la Resolución 1552 de Proteger fueron generados correctamente.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible procesar el archivo: ' . $exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarZip()
    {
        if (! $this->generado || ! is_string($this->zipPath) || ! is_file($this->zipPath)) {
            $this->error = 'El ZIP ya no está disponible. Genera nuevamente el informe.';
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
        if (! $this->generado || ! is_string($this->txtPath) || ! is_file($this->txtPath)) {
            $this->error = 'El TXT ya no está disponible. Genera nuevamente el informe.';
            return null;
        }

        return response()->download(
            $this->txtPath,
            $this->txtName ?: basename($this->txtPath),
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public function reiniciar(): void
    {
        $this->reset([
            'archivo', 'procesando', 'generado', 'mensaje', 'error', 'resumen',
            'erroresValidacion', 'advertencias', 'txtPath', 'txtName', 'zipPath', 'zipName',
        ]);
        $this->periodo = now()->subMonth()->format('Y-m');
        $this->ipsNitSeleccionado = null;
        $this->ipsNombre = '';
        $this->nitReceptor = '900144397';
        $this->cargarIpsNitsGuardados();

        if ($this->ipsNitsGuardados !== []) {
            $primero = $this->ipsNitsGuardados[0];
            $this->ipsNitSeleccionado = (int) $primero['id'];
            $this->nitReceptor = (string) $primero['nit'];
            $this->ipsNombre = (string) ($primero['nombre'] ?? '');
        }

        $this->resetValidation();
    }

    private function limpiarResultado(): void
    {
        $this->generado = false;
        $this->resumen = [];
        $this->erroresValidacion = [];
        $this->advertencias = [];
        $this->txtPath = null;
        $this->txtName = null;
        $this->zipPath = null;
        $this->zipName = null;
    }

    private function cargarIpsNitsGuardados(): void
    {
        $this->ipsNitsGuardados = IpsNitGuardado::query()
            ->where('user_id', auth()->id())
            ->orderByRaw('last_used_at IS NULL')
            ->orderByDesc('last_used_at')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'nit'])
            ->map(static fn (IpsNitGuardado $item): array => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'nit' => $item->nit,
            ])
            ->all();
    }

    private function marcarNitComoUsado(): void
    {
        $nit = preg_replace('/\D+/', '', $this->nitReceptor) ?? '';

        if ($nit === '') {
            return;
        }

        $registro = IpsNitGuardado::query()
            ->where('user_id', auth()->id())
            ->where('nit', $nit)
            ->first();

        if ($registro === null) {
            return;
        }

        $registro->forceFill(['last_used_at' => now()])->save();
        $this->ipsNitSeleccionado = $registro->id;
        $this->cargarIpsNitsGuardados();
    }

    public function render()
    {
        return view('livewire.informes.resolucion1552-proteger-uploader');
    }
}
