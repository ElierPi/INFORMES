<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="px-6 py-7 sm:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-300">
                        Preparar informe
                    </div>
                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">
                        Resolución 1552 · Proteger
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                        Convierte el Excel institucional en un TXT UTF-8 de 14 campos separado por comas y genera el ZIP listo para cargar.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-2xl border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                        <p class="font-bold text-neutral-900 dark:text-white">,</p>
                        <p class="mt-1 text-neutral-500">Separador</p>
                    </div>
                    <div class="rounded-2xl border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                        <p class="font-bold text-neutral-900 dark:text-white">UTF-8</p>
                        <p class="mt-1 text-neutral-500">Codificación</p>
                    </div>
                    <div class="rounded-2xl border border-neutral-200 px-4 py-3 dark:border-neutral-700">
                        <p class="font-bold text-neutral-900 dark:text-white">14</p>
                        <p class="mt-1 text-neutral-500">Campos</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
            <form wire:submit="generar" class="space-y-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="periodo-1552-proteger" class="text-sm font-semibold text-neutral-900 dark:text-white">Período reportado</label>
                        <p class="mt-1 text-xs text-neutral-500">Se utiliza para validar las fechas y construir el nombre MMAAAA.</p>
                        <input id="periodo-1552-proteger" type="month" wire:model="periodo"
                            class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                        @error('periodo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label for="ips-nit-guardado-1552-proteger" class="text-sm font-semibold text-neutral-900 dark:text-white">IPS/NIT guardado</label>
                            <p class="mt-1 text-xs text-neutral-500">Selecciona una IPS usada anteriormente o deja “NIT manual” para escribir otro.</p>
                            <select id="ips-nit-guardado-1552-proteger" wire:model.live="ipsNitSeleccionado"
                                class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                                <option value="">NIT manual / nuevo</option>
                                @foreach ($ipsNitsGuardados as $ips)
                                    <option value="{{ $ips['id'] }}">
                                        {{ $ips['nombre'] ? $ips['nombre'].' — ' : '' }}{{ $ips['nit'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="nit-receptor-1552-proteger" class="text-sm font-semibold text-neutral-900 dark:text-white">NIT para el nombre del archivo</label>
                            <input id="nit-receptor-1552-proteger" type="text" wire:model="nitReceptor" inputmode="numeric" maxlength="12" placeholder="Ej. 900144397"
                                class="mt-2 w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                            @error('nitReceptor') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="ips-nombre-1552-proteger" class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Nombre de la IPS (opcional)</label>
                            <input id="ips-nombre-1552-proteger" type="text" wire:model="ipsNombre" maxlength="120" placeholder="Ej. CIDSMA, Anashii, IPS Centro..."
                                class="mt-2 w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                            @error('ipsNombre') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="guardarIpsNit" wire:loading.attr="disabled" wire:target="guardarIpsNit"
                                class="rounded-lg border border-violet-300 bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-800 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/30 dark:text-violet-200">
                                <span wire:loading.remove wire:target="guardarIpsNit">Guardar IPS/NIT</span>
                                <span wire:loading wire:target="guardarIpsNit">Guardando...</span>
                            </button>

                            @if ($ipsNitSeleccionado)
                                <button type="button" wire:click="eliminarIpsNit"
                                    wire:confirm="¿Eliminar este IPS/NIT de tus guardados?"
                                    class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300">
                                    Eliminar guardado
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    <label for="archivo-1552-proteger"
                        class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center transition hover:border-violet-400 hover:bg-violet-50/40 dark:border-neutral-700 dark:bg-neutral-950/40">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-white text-violet-600 shadow-sm dark:bg-neutral-900">⇧</div>
                        <p class="mt-4 text-sm font-semibold text-neutral-900 dark:text-white">
                            {{ $archivo ? $archivo->getClientOriginalName() : 'Selecciona el Excel de la 1552' }}
                        </p>
                        <p class="mt-1 text-xs text-neutral-500">XLSX o XLS · máximo 30 MB</p>
                        <input id="archivo-1552-proteger" type="file" wire:model="archivo" accept=".xlsx,.xls" class="sr-only">
                    </label>
                    @error('archivo') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                    <button type="submit" wire:loading.attr="disabled" wire:target="generar,archivo" @disabled(! $archivo)
                        class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
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
                <h2 class="font-bold text-neutral-900 dark:text-white">Validaciones</h2>
                <ol class="mt-5 space-y-3 text-sm text-neutral-600 dark:text-neutral-300">
                    @foreach ([
                        'Detecta los 14 encabezados del formato',
                        'Ignora filas con un único campo diligenciado',
                        'Convierte fechas a DD/MM/AAAA',
                        'Valida que las fechas pertenezcan al período',
                        'Advierte duplicados sin eliminarlos',
                        'Genera TXT y ZIP con el mismo nombre base',
                    ] as $step)
                        <li class="flex gap-3"><span class="font-bold text-violet-600">✓</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </section>

    @if ($mensaje)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ $mensaje }}</div>
    @endif
    @if ($error)
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{{ $error }}</div>
    @endif

    @if ($resumen !== [])
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-7">
            @foreach ([
                ['registros', 'Registros'], ['validos', 'Válidos'], ['invalidos', 'Inválidos'],
                ['errores', 'Errores'], ['advertencias', 'Advertencias'], ['duplicados', 'Duplicados'], ['omitidos', 'Omitidos'],
            ] as [$key, $label])
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">{{ $resumen[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($generado)
        <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-neutral-900 dark:text-white">Archivos listos</h2>
                    <p class="mt-1 text-sm text-neutral-500">{{ $zipName }}</p>
                    <p class="mt-1 text-xs text-neutral-400">El ZIP contiene únicamente {{ $txtName }}.</p>
                </div>
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
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead><tr><th class="px-5 py-3 text-left">Fila</th><th class="px-5 py-3 text-left">Campo</th><th class="px-5 py-3 text-left">Valor</th><th class="px-5 py-3 text-left">Detalle</th></tr></thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($erroresValidacion as $item)
                            <tr><td class="px-5 py-3">{{ data_get($item, 'source_row', '—') }}</td><td class="px-5 py-3">{{ data_get($item, 'field_name', '—') }}</td><td class="px-5 py-3">{{ data_get($item, 'value', '—') }}</td><td class="px-5 py-3 text-red-700">{{ data_get($item, 'message', 'Error') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($advertencias !== [])
        <details class="rounded-3xl border border-amber-200 bg-white p-5 shadow-sm dark:border-amber-900 dark:bg-neutral-900">
            <summary class="cursor-pointer font-bold text-amber-800">Advertencias ({{ count($advertencias) }})</summary>
            <div class="mt-4 space-y-3 text-sm">
                @foreach ($advertencias as $item)
                    <p>Fila {{ data_get($item, 'source_row', '—') }}: {{ data_get($item, 'message', '') }}</p>
                @endforeach
            </div>
        </details>
    @endif
</div>
