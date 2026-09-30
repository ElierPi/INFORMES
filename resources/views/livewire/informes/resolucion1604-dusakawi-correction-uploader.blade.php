<div class="mx-auto max-w-6xl space-y-6 p-6">
    <section class="rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-amber-700">DUSAKAWI · Resolución 1604</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Corregir informe rechazado</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">Carga el TXT enviado a Aryuwi y el TXT de errores. El sistema aplica las reglas confirmadas por DUSAKAWI y conserva el mismo nombre del informe.</p>
            </div>
            <div class="flex gap-3 text-sm font-medium">
                <a href="{{ route('informes.resolucion-1604.dusakawi') }}" class="text-emerald-700 hover:text-emerald-900">Preparar informe</a>
                <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">Panel</a>
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
            <label class="mb-2 block text-sm font-semibold text-slate-800">TXT original cargado a DUSAKAWI</label>
            <input type="file" wire:model="archivoInforme" accept=".txt" class="block w-full rounded-xl border border-slate-300 p-2 text-sm" />
        </div>
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-800">TXT de errores de Aryuwi</label>
            <input type="file" wire:model="archivoErrores" accept=".txt" class="block w-full rounded-xl border border-slate-300 p-2 text-sm" />
        </div>

        <div class="md:col-span-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
            <p class="font-semibold">Reglas automáticas actuales</p>
            <p class="mt-1">CUM inexistente/vacío → excluir · Afiliado inexistente → excluir · Diagnóstico múltiple → primer CIE10 · Duración &gt; 30 → 30 · Año 0206 → 2026.</p>
        </div>

        <div class="md:col-span-2 flex flex-wrap gap-3">
            <button wire:click="corregir" wire:loading.attr="disabled" class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="corregir">Analizar y corregir</span>
                <span wire:loading wire:target="corregir">Procesando...</span>
            </button>
            <button wire:click="reiniciar" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700">Nuevo proceso</button>
            @if ($generado)
                <button wire:click="descargarTxt" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Descargar TXT corregido</button>
            @endif
        </div>
    </section>

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ([
                ['Errores EPS', $resumen['errores'] ?? 0],
                ['Originales', $resumen['originales'] ?? 0],
                ['Eliminados', $resumen['eliminados'] ?? 0],
                ['Actualizados', $resumen['actualizados'] ?? 0],
                ['Finales', $resumen['finales'] ?? 0],
                ['Manuales', $resumen['manuales'] ?? 0],
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
            <p class="text-sm font-semibold text-emerald-800">Nueva línea de control</p>
            <code class="mt-2 block overflow-x-auto whitespace-nowrap rounded-lg bg-white p-3 text-sm text-slate-800">{{ $lineaControl }}</code>
        </section>
    @endif

    @if ($pendientes !== [])
        <section class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-red-700">Pendientes manuales</h2>
            <div class="mt-3 max-h-72 overflow-auto text-sm">
                @foreach ($pendientes as $item)
                    <div class="border-b border-slate-100 py-2">Línea {{ $item['line'] ?? '?' }} · {{ $item['document'] ?? '' }} · <strong>{{ $item['field'] ?? '' }}</strong>: {{ $item['reason'] ?? '' }}</div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($auditoria !== [])
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-800">Auditoría de correcciones</h2>
            <div class="mt-3 max-h-96 overflow-auto text-sm">
                @foreach (array_slice($auditoria, 0, 300) as $item)
                    <div class="border-b border-slate-100 py-2">
                        Línea {{ $item['line'] ?? '?' }} · {{ $item['document'] ?? '' }} · <strong>{{ $item['field'] ?? '' }}</strong> · {{ $item['action'] ?? '' }}
                        @if(($item['previous_value'] ?? '') !== '') <span class="text-slate-500">[{{ $item['previous_value'] }} → {{ $item['new_value'] ?? '' }}]</span> @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
