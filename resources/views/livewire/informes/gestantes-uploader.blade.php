<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-medium text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
            {{ session('warning') }}
        </div>
    @endif

    <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                    Gestantes SIGIRES · Reporte semanal
                </p>

                <h1 class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">
                    Gestante semanal
                </h1>

                <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                    Carga el Excel de las cinco hojas oficiales. El módulo aplica correcciones seguras,
                    vuelve a validar y te entrega el Excel corregido. Siempre genera además el TXT ANSI delimitado por | y el ZIP para probarlo en SIGIRES, aunque queden pendientes internos.
                </p>
            </div>

            <div class="rounded-xl bg-emerald-50 px-4 py-3 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                1 - Control · 2 - ID gestantes · 3 - Atenciones · 4 - Seguimientos · 5 - Urgencias
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <label class="text-sm font-semibold text-neutral-900 dark:text-white">
            Excel semanal de gestantes
        </label>

        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
            Formatos permitidos: .xlsx y .xls. Máximo 30 MB.
        </p>

        <input
            type="file"
            wire:model="archivo"
            accept=".xlsx,.xls"
            class="mt-4 block w-full rounded-xl border border-neutral-300 bg-white text-sm text-neutral-700
                   file:mr-4 file:border-0 file:bg-emerald-50 file:px-4 file:py-3 file:font-semibold file:text-emerald-700
                   dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200"
        >

        @error('archivo')
            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror

        <div class="mt-5 flex flex-wrap gap-3">
            <button
                type="button"
                wire:click="analyze"
                wire:loading.attr="disabled"
                wire:target="analyze"
                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="analyze">Validar y preparar</span>
                <span wire:loading wire:target="analyze">Procesando...</span>
            </button>

            @if (! empty($correctedFile))
                <button
                    type="button"
                    wire:click="downloadCorrectedExcel"
                    class="rounded-xl border border-neutral-300 bg-white px-5 py-2.5 text-sm font-semibold text-neutral-800 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white"
                >
                    Descargar Excel corregido
                </button>
            @endif

            @if (! empty($zipPath))
                <button
                    type="button"
                    wire:click="downloadZip"
                    class="rounded-xl bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black dark:bg-white dark:text-neutral-900"
                >
                    Descargar ZIP SIGIRES
                </button>
            @endif

            @if (! empty($txtPath))
                <button
                    type="button"
                    wire:click="downloadTxt"
                    class="rounded-xl border border-neutral-300 bg-white px-5 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200"
                >
                    Descargar TXT
                </button>
            @endif
        </div>
    </section>

    @if ($analyzed)
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Correcciones</p>
                <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $totalCorrections }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Pendientes</p>
                <p class="mt-2 text-3xl font-bold {{ count($errorsReport) ? 'text-amber-600' : 'text-emerald-600' }}">
                    {{ count($errorsReport) }}
                </p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:col-span-2">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Estado de entrega</p>

                @if (! empty($zipPath))
                    <p class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                        ZIP SIGIRES generado correctamente.
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">
                        Contiene el TXT delimitado por pipe y codificado en ANSI. Los pendientes internos no bloquean la descarga.
                    </p>
                @else
                    <p class="mt-2 text-sm font-semibold text-amber-700 dark:text-amber-400">
                        No fue posible generar el ZIP.
                    </p>
                @endif
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($summary as $item)
                <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                        {{ $item['sheet'] }}
                    </p>

                    <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">
                        {{ $item['records'] }}
                    </p>

                    @if ($item['errors'] > 0)
                        <p class="mt-1 text-sm font-medium text-amber-600">
                            {{ $item['errors'] }} {{ $item['errors'] === 1 ? 'pendiente' : 'pendientes' }}
                        </p>
                    @else
                        <p class="mt-1 text-sm font-medium text-emerald-600">Sin pendientes</p>
                    @endif
                </div>
            @endforeach
        </section>

        @if ($corrections !== [])
            <section class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
                <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                    <h2 class="font-semibold text-emerald-800 dark:text-emerald-300">
                        Correcciones automáticas aplicadas
                    </h2>
                    <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                        {{ $totalCorrections }} cambios seguros fueron aplicados antes de volver a validar.
                    </p>
                </div>

                <div class="max-h-[420px] overflow-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
                        <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Hoja</th>
                                <th class="px-4 py-3 text-left font-semibold">Fila</th>
                                <th class="px-4 py-3 text-left font-semibold">Columna</th>
                                <th class="px-4 py-3 text-left font-semibold">Campo</th>
                                <th class="px-4 py-3 text-left font-semibold">Anterior</th>
                                <th class="px-4 py-3 text-left font-semibold">Nuevo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($corrections as $correction)
                                <tr wire:key="weekly-correction-{{ $loop->index }}">
                                    <td class="px-4 py-3">{{ $correction['sheet'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $correction['row'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $correction['column'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $correction['field'] ?? '—' }}</td>
                                    <td class="max-w-48 break-words px-4 py-3">
                                        {{ ($correction['old_value'] ?? '') === '' ? '—' : $correction['old_value'] }}
                                    </td>
                                    <td class="max-w-48 break-words px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-400">
                                        {{ ($correction['new_value'] ?? '') === '' ? '—' : $correction['new_value'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($errorsReport !== [])
            <section class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm dark:border-amber-900 dark:bg-neutral-900">
                <div class="border-b border-amber-100 bg-amber-50 px-5 py-4 dark:border-amber-900 dark:bg-amber-950/30">
                    <h2 class="font-semibold text-amber-900 dark:text-amber-300">
                        Pendientes después de corregir
                    </h2>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                        Estos datos no se inventan. El ZIP ya puede descargarse y cargarse en SIGIRES; luego usamos el LOG real devuelto por la EPS en el corrector.
                    </p>
                </div>

                <div class="max-h-[600px] overflow-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
                        <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Hoja</th>
                                <th class="px-4 py-3 text-left font-semibold">Fila</th>
                                <th class="px-4 py-3 text-left font-semibold">Columna</th>
                                <th class="px-4 py-3 text-left font-semibold">Campo</th>
                                <th class="px-4 py-3 text-left font-semibold">Valor</th>
                                <th class="px-4 py-3 text-left font-semibold">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($errorsReport as $error)
                                <tr wire:key="weekly-error-{{ $loop->index }}">
                                    <td class="px-4 py-3">{{ $error['sheet'] }}</td>
                                    <td class="px-4 py-3">{{ $error['row'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $error['column'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $error['field'] ?? '—' }}</td>
                                    <td class="max-w-48 break-words px-4 py-3">
                                        {{ ($error['value'] ?? '') === '' ? '—' : $error['value'] }}
                                    </td>
                                    <td class="min-w-80 px-4 py-3 font-medium text-amber-800 dark:text-amber-300">
                                        {{ $error['message'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-900 dark:bg-emerald-950/30">
                <h2 class="text-lg font-semibold text-emerald-800 dark:text-emerald-300">
                    Reporte semanal listo
                </h2>

                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                    El Excel corregido no tiene pendientes internos y el ZIP ya está generado para SIGIRES.
                </p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        wire:click="downloadCorrectedExcel"
                        class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"
                    >
                        Descargar Excel corregido
                    </button>

                    <button
                        type="button"
                        wire:click="downloadZip"
                        class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
                    >
                        Descargar ZIP SIGIRES
                    </button>
                </div>
            </section>
        @endif
    @endif
</div>
