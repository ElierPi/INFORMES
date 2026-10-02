<div class="mx-auto w-full max-w-6xl space-y-6">
    <section class="rounded-3xl border border-pink-200 bg-white p-6 shadow-sm dark:border-pink-900 dark:bg-neutral-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-pink-600 dark:text-pink-400">SIGIRES · Gestantes</p>
                <h1 class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">Gestante mensual</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-400">Valida la estructura de 148 campos y genera el XLSX dentro del ZIP con el nombre exigido por SIGIRES.</p>
            </div>
            <a href="{{ route('dashboard') }}" wire:navigate class="text-sm font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white">Volver al panel</a>
        </div>
    </section>

    @if ($errorMessage)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $errorMessage }}</div>
    @endif

    <section class="grid gap-5 lg:grid-cols-3">
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 lg:col-span-2">
            <label class="text-sm font-semibold text-neutral-900 dark:text-white">Excel de gestantes (.xlsx)</label>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Debe conservar la hoja “Gestante” y los 148 campos A:ER.</p>
            <input type="file" wire:model="archivo" accept=".xlsx" class="mt-4 block w-full rounded-xl border border-neutral-300 bg-white text-sm text-neutral-700 file:mr-4 file:border-0 file:bg-pink-50 file:px-4 file:py-3 file:font-semibold file:text-pink-700 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200">
            @error('archivo') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <label class="text-sm font-semibold text-neutral-900 dark:text-white">Mes a reportar</label>
            <input type="month" wire:model="periodo" class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-950">
            @error('periodo') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <label class="text-sm font-semibold text-neutral-900 dark:text-white">IPS / Prestador</label>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Wayuu Anashii queda guardada por defecto. Los nuevos prestadores quedan disponibles para próximos cargues.</p>
                <select wire:model.live="prestadorSeleccionado" class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                    @foreach ($prestadores as $prestador)
                        <option value="{{ $prestador['code'] }}">{{ $prestador['name'] }} · {{ $prestador['code'] }}</option>
                    @endforeach
                    <option value="__nuevo__">+ Agregar nueva IPS / prestador</option>
                </select>
            </div>
            <div class="w-full md:w-72">
                <label class="text-sm font-semibold text-neutral-900 dark:text-white">Código de habilitación</label>
                <input type="text" value="{{ $codigoHabilitacion }}" readonly class="mt-3 w-full rounded-xl border border-neutral-300 bg-neutral-100 px-3 py-2.5 text-sm font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
            </div>
            <button type="button" wire:click="abrirNuevoPrestador" class="rounded-xl border border-pink-200 bg-pink-50 px-4 py-2.5 text-sm font-semibold text-pink-700 hover:bg-pink-100 dark:border-pink-900 dark:bg-pink-950/40 dark:text-pink-300">Agregar IPS</button>
        </div>

        @if ($prestadorMessage)
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">{{ $prestadorMessage }}</div>
        @endif

        @if ($mostrarNuevoPrestador)
            <div class="mt-5 grid gap-4 rounded-2xl border border-pink-200 bg-pink-50/50 p-5 dark:border-pink-900 dark:bg-pink-950/20 md:grid-cols-2">
                <div>
                    <label class="text-sm font-semibold text-neutral-900 dark:text-white">Nombre de la IPS / prestador</label>
                    <input type="text" wire:model="nuevoPrestadorNombre" placeholder="Ej. Nueva IPS" class="mt-2 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                    @error('nuevoPrestadorNombre') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-semibold text-neutral-900 dark:text-white">Código de habilitación</label>
                    <input type="text" maxlength="12" wire:model="nuevoPrestadorCodigo" placeholder="12 dígitos" class="mt-2 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                    @error('nuevoPrestadorCodigo') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <button type="button" wire:click="guardarPrestador" class="rounded-xl bg-pink-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-pink-700">Guardar IPS</button>
                    <button type="button" wire:click="cancelarNuevoPrestador" class="rounded-xl border border-neutral-300 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">Cancelar</button>
                </div>
            </div>
        @endif
    </section>

    <div class="flex flex-wrap gap-3">
        <button wire:click="procesar" wire:loading.attr="disabled" class="rounded-xl bg-pink-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-pink-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="procesar">Validar y preparar ZIP</span><span wire:loading wire:target="procesar">Procesando...</span>
        </button>
        <button wire:click="nuevoProceso" class="rounded-xl border border-neutral-300 bg-white px-5 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">Nuevo proceso</button>
    </div>

    @if ($resultado)
        <section class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900"><p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Registros</p><p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">{{ $resultado['registros'] }}</p></div>
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900"><p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Normalizaciones</p><p class="mt-2 text-3xl font-bold text-emerald-600">{{ $resultado['normalizaciones'] }}</p></div>
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900"><p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Advertencias</p><p class="mt-2 text-3xl font-bold {{ $resultado['warning_count'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $resultado['warning_count'] }}</p></div>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="font-semibold text-emerald-800 dark:text-emerald-300">Archivo preparado</p>
                @if (!empty($resultado['prestador'])) <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">IPS: {{ $resultado['prestador'] }}</p> @endif
                <p class="mt-1 break-all text-sm text-emerald-700 dark:text-emerald-400">{{ $resultado['download_name'] }}</p>
                <p class="mt-1 text-xs text-emerald-700/80">Fecha de corte: {{ $resultado['fecha_corte'] }}</p>
                <button wire:click="descargar" class="mt-4 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Descargar ZIP</button>
            </div>
        </section>
    @endif
</div>
