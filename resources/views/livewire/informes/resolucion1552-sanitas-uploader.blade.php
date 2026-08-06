<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-sky-700 via-blue-700 to-indigo-800 text-white shadow-xl">
        <div class="grid gap-8 p-7 sm:p-9 lg:grid-cols-[minmax(0,1fr)_330px] lg:items-center">
            <div>
                <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-widest">SIGIRES</span>
                <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Resolución 1552 · Sanitas</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-blue-100 sm:text-base">
                    Convierte el Excel institucional en un TXT ANSI con encabezado, 10 campos separados por tabulador y un ZIP listo para cargar en SIGIRES.
                </p>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded-2xl border border-white/20 bg-white/10 px-3 py-4"><p class="font-black">TAB</p><p class="mt-1 text-blue-100">Separador</p></div>
                <div class="rounded-2xl border border-white/20 bg-white/10 px-3 py-4"><p class="font-black">ANSI</p><p class="mt-1 text-blue-100">Codificación</p></div>
                <div class="rounded-2xl border border-white/20 bg-white/10 px-3 py-4"><p class="font-black">10</p><p class="mt-1 text-blue-100">Campos</p></div>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_350px]">
        <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
            <form wire:submit="generar" class="space-y-6">
                <div>
                    <label for="periodo-1552-sanitas" class="text-sm font-semibold text-neutral-900 dark:text-white">Período reportado</label>
                    <p class="mt-1 text-xs text-neutral-500">La fecha final del mes se usará en el nombre DDMMAAAA.</p>
                    <input id="periodo-1552-sanitas" type="month" wire:model="periodo"
                        class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-950 sm:max-w-sm">
                    @error('periodo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="archivo-1552-sanitas" class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center transition hover:border-blue-400 hover:bg-blue-50/40 dark:border-neutral-700 dark:bg-neutral-950/40">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-white text-blue-700 shadow-sm dark:bg-neutral-900">⇧</div>
                        <p class="mt-4 text-sm font-semibold text-neutral-900 dark:text-white">{{ $archivo ? $archivo->getClientOriginalName() : 'Selecciona el Excel de Sanitas' }}</p>
                        <p class="mt-1 text-xs text-neutral-500">XLSX o XLS · máximo 30 MB</p>
                        <input id="archivo-1552-sanitas" type="file" wire:model="archivo" accept=".xlsx,.xls" class="sr-only">
                    </label>
                    @error('archivo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                    <button type="submit" wire:loading.attr="disabled" wire:target="generar,archivo" @disabled(! $archivo)
                        class="rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50">
                        <span wire:loading.remove wire:target="generar">Validar y generar ZIP</span>
                        <span wire:loading wire:target="generar">Procesando...</span>
                    </button>
                    @if ($archivo || $generado || $error)
                        <button type="button" wire:click="reiniciar" class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold dark:border-neutral-700">Limpiar</button>
                    @endif
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="font-bold text-neutral-900 dark:text-white">Prevalidaciones SIGIRES</h2>
                <ol class="mt-5 space-y-3 text-sm text-neutral-600 dark:text-neutral-300">
                    @foreach ([
                        'Detecta los 10 campos aunque el encabezado de especialidad esté vacío',
                        'Valida documento, régimen y código de especialidad',
                        'Reemplaza teléfono vacío por 9999999999',
                        'Valida las tres fechas y su orden',
                        'Exige horas-especialista mayores que cero',
                        'Genera TXT ANSI con encabezado dentro del ZIP',
                    ] as $step)
                        <li class="flex gap-3"><span class="font-bold text-blue-700">✓</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </section>

    @if ($mensaje)<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ $mensaje }}</div>@endif
    @if ($error)<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{{ $error }}</div>@endif

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-7">
            @foreach ([['registros','Registros'],['validos','Válidos'],['invalidos','Inválidos'],['errores','Errores'],['advertencias','Advertencias'],['duplicados','Duplicados'],['telefonos','Teléfonos corregidos']] as [$key,$label])
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">{{ $resumen[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($generado)
        <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-bold text-neutral-900 dark:text-white">Archivos listos</h2><p class="mt-1 text-sm text-neutral-500">{{ $zipName }}</p><p class="mt-1 text-xs text-neutral-400">El ZIP contiene únicamente {{ $txtName }}.</p></div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" wire:click="descargarTxt" class="rounded-xl border border-emerald-600 px-5 py-3 text-sm font-semibold text-emerald-700">Descargar TXT</button>
                    <button type="button" wire:click="descargarZip" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Descargar ZIP</button>
                </div>
            </div>
        </section>
    @endif

    @if ($erroresValidacion !== [])
        <section class="overflow-hidden rounded-3xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-neutral-900">
            <div class="border-b border-red-100 bg-red-50 px-6 py-4 font-bold text-red-800">Errores encontrados</div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-neutral-200 text-sm"><thead><tr><th class="px-5 py-3 text-left">Fila</th><th class="px-5 py-3 text-left">Campo</th><th class="px-5 py-3 text-left">Valor</th><th class="px-5 py-3 text-left">Detalle</th></tr></thead><tbody class="divide-y divide-neutral-100">
                @foreach ($erroresValidacion as $item)<tr><td class="px-5 py-3">{{ data_get($item,'source_row','—') }}</td><td class="px-5 py-3">{{ data_get($item,'field_name','—') }}</td><td class="px-5 py-3">{{ data_get($item,'value','—') }}</td><td class="px-5 py-3 text-red-700">{{ data_get($item,'message','Error') }}</td></tr>@endforeach
            </tbody></table></div>
        </section>
    @endif

    @if ($advertencias !== [])
        <details class="rounded-3xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-900 dark:bg-neutral-900">
            <summary class="cursor-pointer font-bold text-amber-800">Advertencias ({{ count($advertencias) }})</summary>
            <div class="mt-4 space-y-3 text-sm">@foreach ($advertencias as $item)<p>Fila {{ data_get($item,'source_row','—') }}: {{ data_get($item,'message','') }}</p>@endforeach</div>
        </details>
    @endif
</div>
