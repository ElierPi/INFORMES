<?php

namespace App\Livewire\Informes;

use App\Services\Resolucion1604\FamiliarColombia\Resolucion1604FamiliarCorrectionService;
use App\Support\Livewire\HandlesTemporaryUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

final class Resolucion1604FamiliarCorrectionUploader extends Component
{
    use HandlesTemporaryUploads;
    use WithFileUploads;

    public $archivoInforme = null;
    public $archivoErrores = null;
    public bool $procesando = false;
    public bool $corregido = false;
    public ?string $mensaje = null;
    public ?string $error = null;
    public ?string $txtPath = null;
    public ?string $txtName = null;
    public array $resumen = [];
    public array $auditoria = [];
    public array $pendientesManuales = [];

    protected function rules(): array
    {
        return [
            'archivoInforme' => ['required'],
            'archivoErrores' => ['required'],
        ];
    }

    public function corregir(Resolucion1604FamiliarCorrectionService $service): void
    {
        $this->procesando = true;
        $this->error = null;
        $this->mensaje = 'Analizando el TXT y los errores de Familiar...';
        $this->limpiarResultado();

        try {
            $this->validate();
            $originalName = $this->archivoInforme?->getClientOriginalName() ?: 'informe_1604.txt';

            $folder = 'uploads/resolucion1604-familiar-correccion/'.Str::uuid();
            $inputPath = $this->stabilizeUpload(
                upload: $this->archivoInforme,
                extensions: ['txt'],
                maxBytes: 30 * 1024 * 1024,
                folder: $folder,
                baseName: 'informe'
            );
            $errorsPath = $this->stabilizeUpload(
                upload: $this->archivoErrores,
                extensions: ['txt'],
                maxBytes: 10 * 1024 * 1024,
                folder: $folder,
                baseName: 'errores'
            );
            $outputDirectory = Storage::disk('local')->path($folder.'/salida');

            $result = $service->correct($inputPath, $errorsPath, $originalName, $outputDirectory);

            $this->txtPath = (string) $result['txt_path'];
            $this->txtName = (string) $result['txt_name'];
            $this->auditoria = is_array($result['audit'] ?? null) ? $result['audit'] : [];
            $this->pendientesManuales = is_array($result['manual'] ?? null) ? $result['manual'] : [];
            $this->resumen = [
                'originales' => (int) ($result['original_records'] ?? 0),
                'excluidos' => (int) ($result['excluded_records'] ?? 0),
                'finales' => (int) ($result['final_records'] ?? 0),
                'automaticas' => (int) ($result['automatic_corrections'] ?? 0),
                'manuales' => (int) ($result['manual_count'] ?? 0),
            ];

            $this->corregido = true;
            $this->mensaje = $this->pendientesManuales === []
                ? 'El informe fue corregido. Las líneas con CUM inexistente fueron excluidas y el nombre original se conservó.'
                : 'Se aplicaron las reglas conocidas. Hay errores nuevos que requieren revisión antes del cargue.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'No fue posible corregir el informe: '.$exception->getMessage();
            $this->mensaje = null;
        } finally {
            $this->procesando = false;
        }
    }

    public function descargarTxt()
    {
        if (! $this->corregido || ! is_string($this->txtPath) || ! is_file($this->txtPath)) {
            $this->error = 'El TXT corregido ya no está disponible. Procesa nuevamente los archivos.';
            return null;
        }

        return response()->download(
            $this->txtPath,
            $this->txtName ?: basename($this->txtPath),
            ['Content-Type' => 'text/plain; charset=Windows-1252']
        );
    }

    public function descargarErroresSinRegla()
    {
        if ($this->pendientesManuales === []) {
            $this->error = 'No hay errores sin regla automática para descargar.';
            return null;
        }

        $content = $this->crearReporteErroresSinRegla();
        $fileName = 'ERRORES_SIN_REGLA_1604_FAMILIAR_'.now()->format('Ymd_His').'.txt';

        return response()->streamDownload(
            static function () use ($content): void {
                echo "\xEF\xBB\xBF".$content;
            },
            $fileName,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function crearReporteErroresSinRegla(): string
    {
        $lines = [
            'RESOLUCION 1604 - FAMILIAR COLOMBIA - MULTI-IPS',
            'ERRORES SIN REGLA AUTOMATICA',
            'Generado: '.now()->format('Y-m-d H:i:s'),
            'Total pendientes: '.count($this->pendientesManuales),
            '',
            'Este archivo contiene únicamente errores que el sistema todavía no sabe corregir automáticamente.',
            'Puede compartirse para analizar nuevos patrones y crear reglas posteriores.',
            '',
        ];

        foreach ($this->pendientesManuales as $index => $item) {
            $lines[] = str_repeat('=', 90);
            $lines[] = 'PENDIENTE '.($index + 1);
            $lines[] = 'Linea: '.data_get($item, 'line', '—');
            $lines[] = 'NIT IPS: '.data_get($item, 'nit', '');
            $lines[] = 'Contrato: '.data_get($item, 'contract', '');
            $lines[] = 'Documento: '.trim(data_get($item, 'document_type', '').' '.data_get($item, 'document', ''));
            $lines[] = 'Paciente: '.data_get($item, 'patient', '');
            $lines[] = 'Medicamento/Tecnologia: '.data_get($item, 'technology', '');
            $lines[] = 'CUM: '.data_get($item, 'cum', '');
            $lines[] = 'Nro. formula: '.data_get($item, 'formula', '');
            $lines[] = 'Mensaje de Familiar: '.data_get($item, 'message', '');
            $lines[] = 'Motivo interno: '.data_get($item, 'reason', '');
            $lines[] = '';
            $lines[] = 'REGISTRO ORIGINAL - 39 CAMPOS:';

            $record = data_get($item, 'record', []);
            if (is_array($record)) {
                $position = 1;
                foreach ($record as $fieldName => $value) {
                    $lines[] = str_pad((string) $position, 2, '0', STR_PAD_LEFT).' '.$fieldName.' = '.(string) $value;
                    $position++;
                }
            }

            $lines[] = '';
        }

        return implode("\r\n", $lines);
    }

    public function reiniciar(): void
    {
        $this->reset([
            'archivoInforme', 'archivoErrores', 'procesando', 'corregido', 'mensaje', 'error',
            'txtPath', 'txtName', 'resumen', 'auditoria', 'pendientesManuales',
        ]);
        $this->resetValidation();
    }

    private function limpiarResultado(): void
    {
        $this->corregido = false;
        $this->txtPath = null;
        $this->txtName = null;
        $this->resumen = [];
        $this->auditoria = [];
        $this->pendientesManuales = [];
    }

    public function render()
    {
        return view('livewire.informes.resolucion1604-familiar-correction-uploader');
    }
}
