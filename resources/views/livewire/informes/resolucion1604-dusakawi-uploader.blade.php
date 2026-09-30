<div class="mx-auto max-w-6xl space-y-6 p-6">
    <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-emerald-700">DUSAKAWI · Resolución 1604</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Preparar TXT de medicamentos</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Convierte el Excel DSK al TXT de Aryuwi Soft con línea de control y 25 campos separados por pipe (|).</p>
            </div>
            <div class="flex gap-3 text-sm font-medium">
                <a href="{{ route('informes.resolucion-1604.dusakawi.corregir') }}" class="text-amber-700 hover:text-amber-900">Corregir informe rechazado</a>
                <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">Volver al panel</a>
            </div>
        </div>
    </section>

    @if ($error)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $error }}</div>
    @endif
    @if ($mensaje)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ $mensaje }}</div>
    @endif

    <section class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm md:grid-cols-2">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-800">Período a reportar</label>
            <input type="month" wire:model="periodo" class="w-full rounded-xl border-slate-300" />
            @error('periodo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-800">Excel DSK</label>
            <input type="file" wire:model="archivo" accept=".xlsx,.xls" class="block w-full rounded-xl border border-slate-300 p-2 text-sm" />
            @error('archivo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
            <div class="grid gap-2 md:grid-cols-3">
                <div><span class="font-semibold">Contrato:</span> ASE-44430-2026-52</div>
                <div><span class="font-semibold">NIT:</span> 900144397</div>
                <div><span class="font-semibold">Razón social:</span> Wayuu Anashii</div>
                <div><span class="font-semibold">Habilitación:</span> 444300063502</div>
                <div><span class="font-semibold">Municipio:</span> 44430</div>
                <div><span class="font-semibold">Departamento:</span> 44</div>
            </div>
        </div>

        <div class="md:col-span-2 flex flex-wrap gap-3">
            <button wire:click="generar" wire:loading.attr="disabled" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="generar">Generar TXT</span>
                <span wire:loading wire:target="generar">Procesando...</span>
            </button>
            <button wire:click="reiniciar" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">Nuevo proceso</button>
            @if ($generado)
                <button wire:click="descargarTxt" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Descargar TXT</button>
            @endif
        </div>
    </section>

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['Registros', $resumen['registros'] ?? 0],
                ['Válidos', $resumen['validos'] ?? 0],
                ['Con error', $resumen['invalidos'] ?? 0],
                ['Errores', $resumen['errores'] ?? 0],
                ['Advertencias', $resumen['advertencias'] ?? 0],
            ] as [$label, $value])
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($lineaControl)
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
            <p class="text-sm font-semibold text-emerald-800">Línea de control generada</p>
            <code class="mt-2 block overflow-x-auto whitespace-nowrap rounded-lg bg-white p-3 text-sm text-slate-800">{{ $lineaControl }}</code>
        </section>
    @endif

    @if ($erroresValidacion !== [])
        <section class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-red-700">Errores estructurales</h2>
            <div class="mt-3 max-h-80 overflow-auto text-sm">
                @foreach (array_slice($erroresValidacion, 0, 100) as $item)
                    <div class="border-b border-slate-100 py-2">Fila {{ $item['source_row'] ?? '?' }} · <strong>{{ $item['field'] ?? '' }}</strong>: {{ $item['message'] ?? '' }}</div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($advertencias !== [])
        <section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-amber-700">Advertencias para el primer cargue</h2>
            <p class="mt-1 text-sm text-slate-600">No bloquean el TXT; sirven para comparar con los errores reales que devuelva Aryuwi.</p>
            <div class="mt-3 max-h-80 overflow-auto text-sm">
                @foreach (array_slice($advertencias, 0, 150) as $item)
                    <div class="border-b border-slate-100 py-2">Fila {{ $item['row'] ?? '?' }} · <strong>{{ $item['field'] ?? '' }}</strong>: {{ $item['message'] ?? '' }} @if(($item['value'] ?? '') !== '') <span class="text-slate-500">({{ $item['value'] }})</span> @endif</div>
                @endforeach
            </div>
        </section>
    @endif
</div>
