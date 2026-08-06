<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="px-6 py-7 sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">Corregir informe</div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">Resolución 1552 · Familiar de Colombia</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-300">Carga el TXT rechazado y el archivo de errores. El sistema elimina duplicados reportados y corrige el tipo de documento cuando Familiar no encuentra al afiliado: RC → TI y TI → CC.</p>
                </div>
                <a href="{{ route('informes.resolucion-1552.familiar-colombia') }}" class="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-semibold dark:border-neutral-700">Volver a preparar informe</a>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
        <form wire:submit="corregir" class="space-y-6">
            <div class="grid gap-5 lg:grid-cols-2">
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-6 py-9 text-center dark:border-neutral-700 dark:bg-neutral-950/40">
                    <span class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $archivoInforme ? $archivoInforme->getClientOriginalName() : 'TXT enviado a Familiar' }}</span>
                    <span class="mt-1 text-xs text-neutral-500">Archivo de 14 campos separado por punto y coma</span>
                    <input type="file" wire:model="archivoInforme" accept=".txt" class="sr-only">
                    @error('archivoInforme') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-6 py-9 text-center dark:border-neutral-700 dark:bg-neutral-950/40">
                    <span class="text-sm font-semibold text-neutral-900 dark:text-white">{{ $archivoErrores ? $archivoErrores->getClientOriginalName() : 'TXT de errores de Familiar' }}</span>
                    <span class="mt-1 text-xs text-neutral-500">Mensajes por número de línea</span>
                    <input type="file" wire:model="archivoErrores" accept=".txt" class="sr-only">
                    @error('archivoErrores') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                <button type="submit" wire:loading.attr="disabled" @disabled(! $archivoInforme || ! $archivoErrores) class="rounded-xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="corregir">Analizar y corregir</span>
                    <span wire:loading wire:target="corregir">Procesando...</span>
                </button>
                @if ($archivoInforme || $archivoErrores || $generado || $error)
                    <button type="button" wire:click="reiniciar" class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold dark:border-neutral-700">Limpiar</button>
                @endif
            </div>
        </form>
    </section>

    @if ($mensaje)<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ $mensaje }}</div>@endif
    @if ($error)<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{{ $error }}</div>@endif

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            @foreach ([['originales','Originales'],['corregidos','Salida'],['actualizados','Actualizados'],['eliminados','Excluidos'],['errores','Errores leídos'],['automaticos','Automáticos'],['manuales','Manuales']] as [$key,$label])
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">{{ $resumen[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($generado)
        <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-bold text-neutral-900 dark:text-white">TXT corregido listo</h2><p class="mt-1 text-sm text-neutral-500">{{ $outputName }}</p></div>
                <button type="button" wire:click="descargarTxt" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Descargar TXT corregido</button>
            </div>
        </section>
    @endif

    @if ($auditoria !== [])
        <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="border-b border-neutral-200 px-6 py-4 font-bold dark:border-neutral-800">Auditoría de correcciones</div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-neutral-200 text-sm"><thead><tr><th class="px-4 py-3 text-left">Línea</th><th class="px-4 py-3 text-left">Documento</th><th class="px-4 py-3 text-left">CUPS</th><th class="px-4 py-3 text-left">Fecha</th><th class="px-4 py-3 text-left">Anterior</th><th class="px-4 py-3 text-left">Nuevo</th><th class="px-4 py-3 text-left">Acción</th></tr></thead><tbody class="divide-y divide-neutral-100">
                @foreach ($auditoria as $item)<tr><td class="px-4 py-3">{{ data_get($item,'line') }}</td><td class="px-4 py-3">{{ data_get($item,'document_type') }} {{ data_get($item,'document_number') }}</td><td class="px-4 py-3">{{ data_get($item,'cups') }}</td><td class="px-4 py-3">{{ data_get($item,'appointment_date') }}</td><td class="px-4 py-3">{{ data_get($item,'previous_value','—') }}</td><td class="px-4 py-3">{{ data_get($item,'new_value','—') }}</td><td class="px-4 py-3">{{ data_get($item,'action') }}</td></tr>@endforeach
            </tbody></table></div>
        </section>
    @endif

    @if ($pendientes !== [])
        <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"><h2 class="font-bold">Pendientes manuales</h2>@foreach ($pendientes as $item)<p class="mt-2">Línea {{ data_get($item,'line') }}: {{ data_get($item,'reason') }}</p>@endforeach</section>
    @endif
</div>
