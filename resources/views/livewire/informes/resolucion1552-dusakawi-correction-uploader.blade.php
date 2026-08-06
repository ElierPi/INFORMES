<div class="space-y-7">
    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Corrector de rechazos</span>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">Resolución 1552 · DUSAKAWI</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-300">Carga el TXT presentado y el archivo de errores. El sistema excluye líneas repetidas, cambia RC a TI o TI a CC, renumera los consecutivos y actualiza la línea de control.</p>
            </div>
            <a href="{{ route('informes.resolucion-1552') }}" class="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-semibold dark:border-neutral-700">Volver a preparar informe</a>
        </div>
    </section>

    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
        <form wire:submit="corregir" class="space-y-6">
            <div class="grid gap-5 lg:grid-cols-2">
                <label class="rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 p-6 dark:border-neutral-700 dark:bg-neutral-950/40">
                    <span class="text-sm font-bold text-neutral-900 dark:text-white">TXT presentado</span>
                    <span class="mt-2 block text-xs text-neutral-500">{{ $archivoInforme ? $archivoInforme->getClientOriginalName() : 'Selecciona el TXT que cargaste' }}</span>
                    <input type="file" wire:model="archivoInforme" accept=".txt" class="mt-4 block w-full text-sm">
                    @error('archivoInforme') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 p-6 dark:border-neutral-700 dark:bg-neutral-950/40">
                    <span class="text-sm font-bold text-neutral-900 dark:text-white">TXT de errores</span>
                    <span class="mt-2 block text-xs text-neutral-500">{{ $archivoErrores ? $archivoErrores->getClientOriginalName() : 'Selecciona el archivo devuelto por DUSAKAWI' }}</span>
                    <input type="file" wire:model="archivoErrores" accept=".txt" class="mt-4 block w-full text-sm">
                    @error('archivoErrores') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                <button type="submit" wire:loading.attr="disabled" @disabled(! $archivoInforme || ! $archivoErrores) class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">
                    <span wire:loading.remove wire:target="corregir">Analizar y corregir</span>
                    <span wire:loading wire:target="corregir">Procesando...</span>
                </button>
                <button type="button" wire:click="reiniciar" class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold dark:border-neutral-700">Limpiar</button>
            </div>
        </form>
    </section>

    @if ($error)
        <section class="rounded-3xl border border-red-200 bg-red-50 p-6 text-sm text-red-800">{{ $error }}</section>
    @endif

    @if ($mensaje)
        <section class="rounded-3xl border border-blue-200 bg-blue-50 p-6 text-sm text-blue-900">{{ $mensaje }}</section>
    @endif

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'Registros originales' => data_get($resumen, 'originales'),
                'Registros finales' => data_get($resumen, 'corregidos'),
                'Líneas excluidas' => data_get($resumen, 'eliminados'),
                'Tipos modificados' => data_get($resumen, 'actualizados'),
                'Errores interpretados' => data_get($resumen, 'errores'),
                'Correcciones automáticas' => data_get($resumen, 'automaticos'),
                'Pendientes manuales' => data_get($resumen, 'manuales'),
                'Control actualizado' => data_get($resumen, 'control_anterior').' → '.data_get($resumen, 'control_nuevo'),
            ] as $label => $value)
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">{{ $value }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($generado)
        <section class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-bold text-emerald-900">TXT corregido listo</h2><p class="mt-1 text-sm text-emerald-800">Se conservará exactamente el nombre original: {{ $outputName }}</p></div>
                <button type="button" wire:click="descargarTxt" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Descargar TXT corregido</button>
            </div>
        </section>
    @endif

    @if ($auditoria !== [])
        <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="border-b border-neutral-200 px-6 py-4 font-bold dark:border-neutral-800">Auditoría de correcciones</div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-neutral-200 text-sm"><thead><tr><th class="px-4 py-3 text-left">Línea</th><th class="px-4 py-3 text-left">Documento</th><th class="px-4 py-3 text-left">CUPS</th><th class="px-4 py-3 text-left">Anterior</th><th class="px-4 py-3 text-left">Nuevo</th><th class="px-4 py-3 text-left">Acción</th></tr></thead><tbody class="divide-y divide-neutral-100">
                @foreach ($auditoria as $item)<tr><td class="px-4 py-3">{{ data_get($item,'line') }}</td><td class="px-4 py-3">{{ data_get($item,'document_type') }} {{ data_get($item,'document_number') }}</td><td class="px-4 py-3">{{ data_get($item,'cups') }}</td><td class="px-4 py-3">{{ data_get($item,'previous_value','—') }}</td><td class="px-4 py-3">{{ data_get($item,'new_value','—') }}</td><td class="px-4 py-3">{{ data_get($item,'action') }}</td></tr>@endforeach
            </tbody></table></div>
        </section>
    @endif

    @if ($pendientes !== [])
        <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"><h2 class="font-bold">Pendientes manuales</h2>@foreach ($pendientes as $item)<p class="mt-2">Línea {{ data_get($item,'line') }}: {{ data_get($item,'reason') }}</p>@endforeach</section>
    @endif
</div>
