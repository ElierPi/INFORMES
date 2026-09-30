<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="px-6 py-7 sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">Corregir informe</div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">Resolución 1604 · Familiar Colombia · CIDSMA</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                        Cruza el TXT cargado con el archivo de errores de Familiar y aplica únicamente reglas confirmadas por la plataforma.
                    </p>
                </div>
                <a href="{{ route('informes.resolucion-1604.familiar-colombia') }}" wire:navigate class="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-semibold dark:border-neutral-700">Preparar informe</a>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
            <form wire:submit="corregir" class="space-y-6">
                <div>
                    <label for="archivo-informe-1604-correccion" class="text-sm font-semibold text-neutral-900 dark:text-white">TXT cargado en Familiar</label>
                    <label for="archivo-informe-1604-correccion" class="mt-3 flex cursor-pointer items-center justify-between rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-5 py-6 dark:border-neutral-700 dark:bg-neutral-950/40">
                        <div><p class="text-sm font-semibold">{{ $archivoInforme ? $archivoInforme->getClientOriginalName() : 'Selecciona el TXT original' }}</p><p class="mt-1 text-xs text-neutral-500">Se conservará exactamente este nombre al descargar.</p></div><span class="text-2xl">⇧</span>
                        <input id="archivo-informe-1604-correccion" type="file" wire:model="archivoInforme" accept=".txt" class="sr-only">
                    </label>
                    @error('archivoInforme') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="archivo-errores-1604-correccion" class="text-sm font-semibold text-neutral-900 dark:text-white">TXT de errores devuelto por Familiar</label>
                    <label for="archivo-errores-1604-correccion" class="mt-3 flex cursor-pointer items-center justify-between rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-5 py-6 dark:border-neutral-700 dark:bg-neutral-950/40">
                        <div><p class="text-sm font-semibold">{{ $archivoErrores ? $archivoErrores->getClientOriginalName() : 'Selecciona el archivo de errores' }}</p><p class="mt-1 text-xs text-neutral-500">TXT · errores por número de línea.</p></div><span class="text-2xl">⇧</span>
                        <input id="archivo-errores-1604-correccion" type="file" wire:model="archivoErrores" accept=".txt" class="sr-only">
                    </label>
                    @error('archivoErrores') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                    <button type="submit" wire:loading.attr="disabled" wire:target="corregir,archivoInforme,archivoErrores" @disabled(! $archivoInforme || ! $archivoErrores) class="rounded-xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="corregir">Analizar y corregir</span>
                        <span wire:loading wire:target="corregir">Corrigiendo...</span>
                    </button>
                    @if ($archivoInforme || $archivoErrores || $corregido || $error)
                        <button type="button" wire:click="reiniciar" class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold dark:border-neutral-700">Limpiar</button>
                    @endif
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="font-bold text-neutral-900 dark:text-white">Reglas actuales</h2>
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    <p class="font-semibold">CUM no existe en la base de datos</p>
                    <p class="mt-2">Se excluye únicamente la línea reportada. No se inventa ni reemplaza el CUM.</p>
                </div>
                <p class="mt-4 text-xs leading-5 text-neutral-500">Los errores que aún no tengan regla automática se mostrarán como pendientes manuales y no se modificarán silenciosamente.</p>
            </div>
        </aside>
    </section>

    @if ($mensaje)<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ $mensaje }}</div>@endif
    @if ($error)<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{{ $error }}</div>@endif

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['originales','Originales'],['excluidos','Excluidos'],['finales','Finales'],['automaticas','Correcciones'],['manuales','Pendientes manuales']] as [$key,$label])
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900"><p class="text-sm text-neutral-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ $resumen[$key] ?? 0 }}</p></div>
            @endforeach
        </section>
    @endif

    @if ($corregido)
        <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-bold">TXT corregido</h2><p class="mt-1 text-sm text-neutral-500">{{ $txtName }}</p></div>
                <button type="button" wire:click="descargarTxt" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Descargar con el mismo nombre</button>
            </div>
        </section>
    @endif

    @if ($auditoria !== [])
        <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="border-b border-neutral-200 px-6 py-4"><h2 class="font-bold">Auditoría de correcciones</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead><tr><th class="px-5 py-3 text-left">Línea</th><th class="px-5 py-3 text-left">Documento</th><th class="px-5 py-3 text-left">Paciente</th><th class="px-5 py-3 text-left">Medicamento</th><th class="px-5 py-3 text-left">CUM</th><th class="px-5 py-3 text-left">Acción</th></tr></thead>
                <tbody class="divide-y divide-neutral-100">@foreach ($auditoria as $item)<tr><td class="px-5 py-3">{{ data_get($item,'line') }}</td><td class="px-5 py-3">{{ data_get($item,'document_type') }} {{ data_get($item,'document') }}</td><td class="px-5 py-3">{{ data_get($item,'patient') }}</td><td class="px-5 py-3">{{ data_get($item,'technology') }}</td><td class="px-5 py-3 font-mono">{{ data_get($item,'cum') }}</td><td class="px-5 py-3 font-semibold text-amber-700">Excluida</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    @if ($pendientesManuales !== [])
        <section class="rounded-3xl border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-950/20">
            <h2 class="font-bold text-red-800 dark:text-red-200">Errores nuevos sin regla automática</h2>
            <div class="mt-4 space-y-3 text-sm text-red-700 dark:text-red-300">@foreach ($pendientesManuales as $item)<p><strong>Línea {{ data_get($item,'line','—') }}:</strong> {{ data_get($item,'message') }} — {{ data_get($item,'reason') }}</p>@endforeach</div>
        </section>
    @endif
</div>
