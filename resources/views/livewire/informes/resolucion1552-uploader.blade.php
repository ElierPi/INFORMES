<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="absolute inset-y-0 right-0 hidden w-1/3 bg-gradient-to-l from-emerald-100/70 to-transparent dark:from-emerald-950/30 lg:block"></div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                        Generador oficial TXT + ZIP
                    </div>

                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">
                        Resolución 1552
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-neutral-600 dark:text-neutral-300">
                        Carga el Excel de citas, homologa las especialidades, valida los registros y descarga los archivos listos para presentar.
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-2xl border border-neutral-200 bg-white/80 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900/80">
                        <p class="font-bold text-neutral-900 dark:text-white">TAB</p>
                        <p class="mt-1 text-neutral-500">Separador</p>
                    </div>
                    <div class="rounded-2xl border border-neutral-200 bg-white/80 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900/80">
                        <p class="font-bold text-neutral-900 dark:text-white">1252</p>
                        <p class="mt-1 text-neutral-500">Codificación</p>
                    </div>
                    <div class="rounded-2xl border border-neutral-200 bg-white/80 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-900/80">
                        <p class="font-bold text-neutral-900 dark:text-white">ZIP</p>
                        <p class="mt-1 text-neutral-500">Salida</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
            <form wire:submit="generar" class="space-y-6">
                <div>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                                Archivo fuente
                            </h2>
                            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                Selecciona el Excel exportado por la IPS.
                            </p>
                        </div>

                        @if ($archivo)
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                Archivo cargado
                            </span>
                        @endif
                    </div>

                    <label
                        for="archivo-1552"
                        class="mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-neutral-300 bg-neutral-50 px-6 py-10 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40 dark:border-neutral-700 dark:bg-neutral-950/40 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/20"
                    >
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm dark:bg-neutral-900 dark:text-emerald-400">
                            <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16.5V4.5m0 0-4 4m4-4 4 4M5.25 14.25v4.5h13.5v-4.5" />
                            </svg>
                        </div>

                        <p class="mt-4 text-sm font-semibold text-neutral-900 dark:text-white">
                            {{ $archivo ? $archivo->getClientOriginalName() : 'Haz clic para seleccionar el Excel' }}
                        </p>

                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            XLSX o XLS · máximo 30 MB
                        </p>

                        <input
                            id="archivo-1552"
                            type="file"
                            wire:model="archivo"
                            accept=".xlsx,.xls"
                            class="sr-only"
                        >

                        <div wire:loading wire:target="archivo" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400">
                            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                            </svg>
                            Cargando archivo...
                        </div>
                    </label>

                    @error('archivo')
                        <p class="mt-2 text-sm font-medium text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="generar,archivo"
                        @disabled(! $archivo)
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="generar">
                            Validar y generar archivos
                        </span>
                        <span wire:loading wire:target="generar" class="inline-flex items-center gap-2">
                            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>

                    @if ($archivo || $generado || $error)
                        <button
                            type="button"
                            wire:click="reiniciar"
                            class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                        >
                            Limpiar
                        </button>
                    @endif
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="font-bold text-neutral-900 dark:text-white">
                    Proceso automático
                </h2>

                <ol class="mt-5 space-y-4 text-sm">
                    @foreach ([
                        'Detectar hoja y encabezados',
                        'Homologar columnas del Excel',
                        'Aplicar perfil de la IPS',
                        'Convertir especialidades oficiales',
                        'Validar y generar TXT + ZIP',
                    ] as $step)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                ✓
                            </span>
                            <span class="leading-6 text-neutral-600 dark:text-neutral-300">
                                {{ $step }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
                Los teléfonos faltantes se completan con <strong>9999999999</strong>, según la regla configurada.
            </div>
        </aside>
    </section>

    @if ($mensaje)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold dark:bg-emerald-900">✓</span>
            <p class="pt-0.5 text-sm font-medium">{{ $mensaje }}</p>
        </div>
    @endif

    @if ($error)
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-red-100 font-bold dark:bg-red-900">!</span>
            <p class="pt-0.5 text-sm font-medium">{{ $error }}</p>
        </div>
    @endif

    @if ($resumen !== [])
        <section>
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">
                        Resultado del análisis
                    </h2>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Resumen de los registros procesados.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @php
                    $cards = [
                        ['key' => 'registros', 'label' => 'Registros', 'class' => 'text-neutral-900 dark:text-white'],
                        ['key' => 'validos', 'label' => 'Válidos', 'class' => 'text-emerald-600 dark:text-emerald-400'],
                        ['key' => 'invalidos', 'label' => 'Inválidos', 'class' => 'text-red-600 dark:text-red-400'],
                        ['key' => 'errores', 'label' => 'Errores', 'class' => 'text-red-600 dark:text-red-400'],
                        ['key' => 'advertencias', 'label' => 'Advertencias', 'class' => 'text-amber-600 dark:text-amber-400'],
                    ];
                @endphp

                @foreach ($cards as $card)
                    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">
                            {{ $card['label'] }}
                        </p>
                        <p class="mt-2 text-3xl font-bold {{ $card['class'] }}">
                            {{ $resumen[$card['key']] ?? 0 }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($generado)
        <section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="border-b border-emerald-100 bg-emerald-50/70 px-6 py-5 dark:border-emerald-900 dark:bg-emerald-950/30">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                            Archivos generados
                        </h2>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                            Prestador {{ $resumen['prestador'] ?? '—' }} · {{ $resumen['codificacion'] ?? 'Windows-1252' }}
                        </p>
                    </div>

                    <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        Listo para descargar
                    </span>
                </div>
            </div>

            <div class="grid gap-4 p-6 lg:grid-cols-2">
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-neutral-200 p-4 dark:border-neutral-700">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Archivo TXT</p>
                        <p class="mt-1 truncate text-sm font-medium text-neutral-900 dark:text-white">{{ $txtName }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="descargarTxt"
                        class="shrink-0 rounded-xl border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        Descargar
                    </button>
                </div>

                <div class="flex items-center justify-between gap-4 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Archivo ZIP oficial</p>
                        <p class="mt-1 truncate text-sm font-medium text-neutral-900 dark:text-white">{{ $zipName }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="descargarZip"
                        class="shrink-0 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        Descargar ZIP
                    </button>
                </div>
            </div>
        </section>
    @endif

    @if ($erroresValidacion !== [])
        <section class="overflow-hidden rounded-3xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-neutral-900">
            <div class="border-b border-red-200 bg-red-50 px-6 py-5 dark:border-red-900 dark:bg-red-950/30">
                <h2 class="font-bold text-red-900 dark:text-red-300">
                    Errores encontrados ({{ count($erroresValidacion) }})
                </h2>
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                    Corrige estos datos en el Excel y vuelve a procesarlo.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800/60">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold text-neutral-600 dark:text-neutral-300">Fila</th>
                            <th class="px-5 py-3 text-left font-semibold text-neutral-600 dark:text-neutral-300">Campo</th>
                            <th class="px-5 py-3 text-left font-semibold text-neutral-600 dark:text-neutral-300">Valor</th>
                            <th class="px-5 py-3 text-left font-semibold text-neutral-600 dark:text-neutral-300">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($erroresValidacion as $item)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-5 py-4 font-medium text-neutral-900 dark:text-white">
                                    {{ data_get($item, 'source_row', '—') }}
                                </td>
                                <td class="px-5 py-4 text-neutral-700 dark:text-neutral-300">
                                    {{ data_get($item, 'field_name', data_get($item, 'field', '—')) }}
                                </td>
                                <td class="max-w-56 break-words px-5 py-4 text-neutral-700 dark:text-neutral-300">
                                    {{ data_get($item, 'value', '—') === '' ? '(vacío)' : data_get($item, 'value', '—') }}
                                </td>
                                <td class="min-w-72 px-5 py-4 text-red-700 dark:text-red-300">
                                    {{ data_get($item, 'message', 'Error de validación') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($advertencias !== [])
        <details class="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm dark:border-amber-900 dark:bg-neutral-900">
            <summary class="cursor-pointer bg-amber-50 px-6 py-5 font-bold text-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
                Transformaciones automáticas ({{ count($advertencias) }})
            </summary>

            <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @foreach ($advertencias as $item)
                    <div class="flex gap-3 px-6 py-4 text-sm">
                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-900 dark:text-amber-300">!</span>
                        <div>
                            <p class="font-medium text-neutral-900 dark:text-white">
                                Fila {{ data_get($item, 'source_row', '—') }} · {{ data_get($item, 'field_name', 'Dato transformado') }}
                            </p>
                            <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                                {{ data_get($item, 'message', 'Valor transformado automáticamente.') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
