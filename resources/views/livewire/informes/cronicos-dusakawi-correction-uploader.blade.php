<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="rounded-2xl border border-cyan-200 bg-white p-6 shadow-sm dark:border-cyan-900 dark:bg-zinc-900">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-cyan-700 dark:text-cyan-300">DUSAKAWI · Crónicos</p>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Corregir Excel rechazado</h1>
                <p class="mt-2 max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
                    Carga el Excel original y el Excel de errores. El módulo aplica únicamente reglas seguras y conserva el mismo nombre del archivo original.
                </p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300">Volver al panel</a>
        </div>
    </div>

    @if ($error)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $error }}</div>
    @endif

    @if ($mensaje)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">{{ $mensaje }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <label class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <span class="block text-sm font-semibold text-zinc-900 dark:text-white">Excel original de crónicos</span>
            <span class="mt-1 block text-xs text-zinc-500">El mismo archivo que fue cargado a DUSAKAWI.</span>
            <input type="file" wire:model="archivoInforme" accept=".xlsx,.xls" class="mt-4 block w-full text-sm text-zinc-700 dark:text-zinc-200" />
            @error('archivoInforme') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <span class="block text-sm font-semibold text-zinc-900 dark:text-white">Excel de errores DUSAKAWI</span>
            <span class="mt-1 block text-xs text-zinc-500">Archivo errores_carga_YYYY-MM.xlsx devuelto por la plataforma.</span>
            <input type="file" wire:model="archivoErrores" accept=".xlsx,.xls" class="mt-4 block w-full text-sm text-zinc-700 dark:text-zinc-200" />
            @error('archivoErrores') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
        </label>
    </div>

    <div class="rounded-2xl border border-violet-200 bg-violet-50/60 p-5 dark:border-violet-900 dark:bg-violet-950/20">
        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="eliminarDuplicados" class="mt-1 h-4 w-4 rounded border-zinc-300 text-violet-600 focus:ring-violet-500" />
            <span>
                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">Eliminar documentos duplicados</span>
                <span class="mt-1 block text-xs text-zinc-600 dark:text-zinc-300">
                    Opcional. Compara TIPO_DOCUMENTO + NUMERO_DOCUMENTO, conserva la primera aparición, elimina las siguientes y renumera ORDEN. Úsalo cuando DUSAKAWI reporte duplicados uno por uno.
                </span>
            </span>
        </label>
    </div>

    <div class="flex flex-wrap gap-3">
        <button wire:click="corregir" wire:loading.attr="disabled" class="rounded-lg bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-cyan-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="corregir">Analizar y corregir</span>
            <span wire:loading wire:target="corregir">Procesando...</span>
        </button>
        @if ($generado)
            <button wire:click="descargarExcel" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Descargar Excel corregido</button>
        @endif
        <button wire:click="reiniciar" class="rounded-lg border border-zinc-300 px-5 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Nuevo proceso</button>
    </div>

    @if ($resumen)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['Errores analizados', $resumen['errores'] ?? 0], ['Correcciones automáticas', $resumen['automaticos'] ?? 0], ['Celdas modificadas', $resumen['celdas'] ?? 0], ['Duplicados eliminados', $resumen['duplicados'] ?? 0], ['Pendientes manuales', $resumen['manuales'] ?? 0]] as [$label, $value])
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($auditoria)
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Correcciones automáticas</h2>
            </div>
            <div class="max-h-[32rem] overflow-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                    <thead class="sticky top-0 bg-zinc-50 dark:bg-zinc-950">
                        <tr>
                            <th class="px-4 py-3 text-left">Fila</th><th class="px-4 py-3 text-left">Documento</th><th class="px-4 py-3 text-left">Campo</th><th class="px-4 py-3 text-left">Anterior</th><th class="px-4 py-3 text-left">Nuevo</th><th class="px-4 py-3 text-left">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($auditoria as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $item['row'] }}</td>
                                <td class="px-4 py-3">{{ $item['document'] }}</td>
                                <td class="px-4 py-3 font-medium">{{ $item['column'] }}</td>
                                <td class="px-4 py-3">{{ $item['previous_value'] }}</td>
                                <td class="px-4 py-3">{{ $item['new_value'] }}</td>
                                <td class="px-4 py-3">{{ $item['action'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($pendientes)
        <div class="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/20">
            <div class="border-b border-amber-200 px-5 py-4 dark:border-amber-900">
                <h2 class="font-semibold text-amber-900 dark:text-amber-100">Revisión manual</h2>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">No se inventan valores clínicos ni de afiliación.</p>
            </div>
            <div class="max-h-[28rem] overflow-auto">
                <table class="min-w-full divide-y divide-amber-200 text-sm dark:divide-amber-900">
                    <thead class="sticky top-0 bg-amber-100/80 dark:bg-amber-950"><tr><th class="px-4 py-3 text-left">Fila</th><th class="px-4 py-3 text-left">Documento</th><th class="px-4 py-3 text-left">Campo</th><th class="px-4 py-3 text-left">Motivo</th></tr></thead>
                    <tbody class="divide-y divide-amber-100 dark:divide-amber-900/70">
                        @foreach ($pendientes as $item)
                            <tr><td class="px-4 py-3">{{ $item['row'] }}</td><td class="px-4 py-3">{{ $item['document'] }}</td><td class="px-4 py-3 font-medium">{{ $item['field'] }}</td><td class="px-4 py-3">{{ $item['reason'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
