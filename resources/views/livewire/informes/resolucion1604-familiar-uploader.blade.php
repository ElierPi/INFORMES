<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="px-6 py-7 sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">Preparar informe</div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">Resolución 1604 · Familiar Colombia · CIDSMA</h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                        Convierte el formato de gestión farmacéutica en TXT ANSI de 39 campos, separado por punto y coma, sin encabezado ni línea vacía final.
                    </p>
                </div>
                <div class="flex flex-col items-end gap-3">
                    <a href="{{ route('informes.resolucion-1604.familiar-colombia.corregir') }}" wire:navigate class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Corregir informe rechazado</a>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-2xl border border-neutral-200 px-4 py-3 dark:border-neutral-700"><p class="font-bold">;</p><p class="mt-1 text-neutral-500">Separador</p></div>
                    <div class="rounded-2xl border border-neutral-200 px-4 py-3 dark:border-neutral-700"><p class="font-bold">ANSI</p><p class="mt-1 text-neutral-500">Codificación</p></div>
                    <div class="rounded-2xl border border-neutral-200 px-4 py-3 dark:border-neutral-700"><p class="font-bold">39</p><p class="mt-1 text-neutral-500">Campos</p></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
            <form wire:submit="generar" class="space-y-6">
                <div>
                    <label for="periodo-1604-familiar" class="text-sm font-semibold text-neutral-900 dark:text-white">Período de cargue</label>
                    <p class="mt-1 text-xs text-neutral-500">El módulo seleccionará automáticamente la hoja del mes indicado.</p>
                    <input id="periodo-1604-familiar" type="month" wire:model="periodo" class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                    @error('periodo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="archivo-1604-familiar" class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40 dark:border-neutral-700 dark:bg-neutral-950/40">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm dark:bg-neutral-900">⇧</div>
                        <p class="mt-4 text-sm font-semibold text-neutral-900 dark:text-white">{{ $archivo ? $archivo->getClientOriginalName() : 'Selecciona el Excel de la Resolución 1604' }}</p>
                        <p class="mt-1 text-xs text-neutral-500">XLSX o XLS · máximo 30 MB</p>
                        <input id="archivo-1604-familiar" type="file" wire:model="archivo" accept=".xlsx,.xls" class="sr-only">
                    </label>
                    @error('archivo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                    <button type="submit" wire:loading.attr="disabled" wire:target="generar,archivo" @disabled(! $archivo) class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
                        <span wire:loading.remove wire:target="generar">Validar y generar TXT</span>
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
                <h2 class="font-bold text-neutral-900 dark:text-white">Reglas automáticas</h2>
                <ol class="mt-5 space-y-3 text-sm text-neutral-600 dark:text-neutral-300">
                    @foreach ([
                        'Detecta los 39 campos del portal actual',
                        'Selecciona la hoja según el período',
                        'MAICAO → 44430 y LA GUAJIRA → 44',
                        'Corrige filas de julio sin modalidad: 1 (Cápita)',
                        'Fechas de entrega/solicitud → DD/MM/AAAA HH:MM',
                        'Fecha de factura → DD/MM/AAAA',
                        'Genera TXT ANSI sin encabezado ni línea final',
                    ] as $step)
                        <li class="flex gap-3"><span class="font-bold text-emerald-600">✓</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                Los campos 37–39 (Número contrato, Lote y Observación) se conservan porque el portal actual los exige, aunque no aparecen en la circular anterior de 36 campos.
            </div>
        </aside>
    </section>

    @if ($mensaje)<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ $mensaje }}</div>@endif
    @if ($error)<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{{ $error }}</div>@endif

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ([['registros','Registros'],['validos','Válidos'],['invalidos','Inválidos'],['errores','Errores'],['advertencias','Advertencias'],['correcciones','Autocorrecciones']] as [$key,$label])
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">{{ $resumen[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($hoja)
        <p class="text-sm text-neutral-500">Hoja procesada: <span class="font-semibold text-neutral-800 dark:text-neutral-200">{{ $hoja }}</span></p>
    @endif

    @if ($generado)
        <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="font-bold text-neutral-900 dark:text-white">Archivo listo para probar en Familiar</h2><p class="mt-1 text-sm text-neutral-500">{{ $txtName }}</p></div>
                <button type="button" wire:click="descargarTxt" class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Descargar TXT</button>
            </div>
        </section>
    @endif

    @if ($erroresValidacion !== [])
        <section class="overflow-hidden rounded-3xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-neutral-900">
            <div class="border-b border-red-100 bg-red-50 px-6 py-4 font-bold text-red-800">Errores encontrados</div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead><tr><th class="px-5 py-3 text-left">Fila</th><th class="px-5 py-3 text-left">#</th><th class="px-5 py-3 text-left">Campo</th><th class="px-5 py-3 text-left">Valor</th><th class="px-5 py-3 text-left">Detalle</th></tr></thead>
                <tbody class="divide-y divide-neutral-100">@foreach ($erroresValidacion as $item)<tr><td class="px-5 py-3">{{ data_get($item,'source_row','—') }}</td><td class="px-5 py-3">{{ data_get($item,'field_number','—') }}</td><td class="px-5 py-3">{{ data_get($item,'field_name','—') }}</td><td class="px-5 py-3">{{ data_get($item,'value','—') }}</td><td class="px-5 py-3 text-red-700">{{ data_get($item,'message','Error') }}</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    @if ($advertencias !== [])
        <details class="rounded-3xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-900 dark:bg-neutral-900">
            <summary class="cursor-pointer font-bold text-amber-800">Transformaciones y advertencias ({{ count($advertencias) }})</summary>
            <div class="mt-4 max-h-96 space-y-3 overflow-y-auto text-sm">@foreach ($advertencias as $item)<p>Fila {{ data_get($item,'source_row','—') }} · {{ data_get($item,'field_name','') }}: {{ data_get($item,'message','') }}</p>@endforeach</div>
        </details>
    @endif
</div>
