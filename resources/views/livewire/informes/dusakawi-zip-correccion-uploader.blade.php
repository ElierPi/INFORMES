<div class="mx-auto w-full max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="border-b border-neutral-200 px-6 py-6 dark:border-neutral-700 sm:px-8">
            <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                Resolución 202
            </p>

            <h1 class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">
                Corregir ZIP de Dusakawi
            </h1>

            <p class="mt-2 max-w-4xl text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                Carga el ZIP original de Dusakawi y el archivo de errores.
                El sistema conserva la estructura interna, corrige el Excel
                de la Resolución 202 y genera un nuevo ZIP listo para cargar.
            </p>
        </div>

        <form wire:submit.prevent="procesar" class="space-y-6 p-6 sm:p-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                        ZIP original de Dusakawi
                    </label>

                    <input
                        type="file"
                        wire:model="archivoZip"
                        accept=".zip"
                        class="block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                    >

                    @error('archivoZip')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                        Archivo de errores
                    </label>

                    <input
                        type="file"
                        wire:model="archivoErrores"
                        accept=".xlsx,.xls,.html,.htm,.txt"
                        class="block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                    >

                    @error('archivoErrores')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                        Fecha de corte
                    </label>

                    <input
                        type="date"
                        wire:model="fechaCorte"
                        class="block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-white"
                    >

                    @error('fechaCorte')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="procesar,archivoZip,archivoErrores"
                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="procesar">
                        Procesar ZIP
                    </span>

                    <span wire:loading wire:target="procesar">
                        Procesando...
                    </span>
                </button>

                @if ($procesado && $zipCorregidoPath)
                    <button
                        type="button"
                        wire:click="descargar"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700"
                    >
                        Descargar ZIP corregido
                    </button>
                @endif
            </div>
        </form>
    </section>

    @if ($errorProceso)
        <section class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
            <p class="font-semibold">Ocurrió un error</p>
            <p class="mt-1">{{ $errorProceso }}</p>
        </section>
    @endif

    @if ($procesado)
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ([
                'Registros' => $resumen['registros_leidos'] ?? 0,
                'Errores' => $resumen['errores_reportados'] ?? 0,
                'Corregidos' => $resumen['celdas_corregidas'] ?? 0,
                'Pendientes' => $resumen['pendientes'] ?? 0,
                'Ya válidos' => $resumen['ya_validos'] ?? 0,
                'Archivo interno' => $resumen['archivo_interno'] ?? '-',
            ] as $label => $value)
                <article class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        {{ $label }}
                    </p>
                    <p class="mt-2 break-words text-lg font-bold text-neutral-900 dark:text-white">
                        {{ $value }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                Correcciones realizadas
            </h2>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead>
                        <tr class="text-left text-neutral-500">
                            <th class="px-3 py-2">Registro</th>
                            <th class="px-3 py-2">Variable</th>
                            <th class="px-3 py-2">Campo</th>
                            <th class="px-3 py-2">Anterior</th>
                            <th class="px-3 py-2">Nuevo</th>
                            <th class="px-3 py-2">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @forelse ($correcciones as $item)
                            <tr>
                                <td class="px-3 py-2">{{ $item['registro'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $item['variable'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $item['campo'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $item['valor_anterior'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $item['valor_nuevo'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $item['motivo'] ?? $item['detalle'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-neutral-500">
                                    No se aplicaron correcciones.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($pendientes !== [])
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950/20">
                <h2 class="text-lg font-bold text-amber-900 dark:text-amber-100">
                    Pendientes de revisión manual
                </h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-amber-200 text-sm dark:divide-amber-900">
                        <thead>
                            <tr class="text-left text-amber-800 dark:text-amber-200">
                                <th class="px-3 py-2">Registro</th>
                                <th class="px-3 py-2">Variable</th>
                                <th class="px-3 py-2">Campo</th>
                                <th class="px-3 py-2">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendientes as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item['registro'] ?? $item['fila'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $item['variable'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $item['campo'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $item['detalle'] ?? $item['motivo'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
