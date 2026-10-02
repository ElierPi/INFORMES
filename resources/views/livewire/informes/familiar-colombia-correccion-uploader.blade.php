<div class="mx-auto w-full max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="border-b border-neutral-200 px-6 py-6 dark:border-neutral-700 sm:px-8">
            <p class="text-sm font-semibold text-violet-600 dark:text-violet-400">
                Resolución 202
            </p>

            <h1 class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">
                Corregir errores de Familiar de Colombia
            </h1>

            <p class="mt-2 max-w-4xl text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                Carga el ZIP original enviado a SIGIRES y el Excel de errores.
                El sistema conserva la línea de control, corrige los registros
                tipo 2 y genera nuevamente un ZIP con TXT ANSI.
            </p>
        </div>

        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                    ZIP original cargado
                </label>

                <input
                    type="file"
                    wire:model="archivoZip"
                    accept=".zip"
                    class="block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-violet-50 file:px-4 file:py-2 file:font-semibold file:text-violet-700 dark:border-neutral-600 dark:bg-neutral-950"
                >

                @error('archivoZip')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div wire:loading wire:target="archivoZip" class="mt-2 text-sm font-semibold text-violet-600">
                    Cargando ZIP...
                </div>

                @if ($archivoZip)
                    <p class="mt-2 text-sm text-emerald-700">
                        {{ $archivoZip->getClientOriginalName() }}
                    </p>
                @endif
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                    Excel de errores SIGIRES
                </label>

                <input
                    type="file"
                    wire:model="archivoErrores"
                    accept=".xls,.xlsx"
                    class="block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-violet-50 file:px-4 file:py-2 file:font-semibold file:text-violet-700 dark:border-neutral-600 dark:bg-neutral-950"
                >

                @error('archivoErrores')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div wire:loading wire:target="archivoErrores" class="mt-2 text-sm font-semibold text-violet-600">
                    Cargando Excel...
                </div>

                @if ($archivoErrores)
                    <p class="mt-2 text-sm text-emerald-700">
                        {{ $archivoErrores->getClientOriginalName() }}
                    </p>
                @endif
            </div>
        </div>

        <div class="px-6 pb-6 sm:px-8 sm:pb-8">
            <button
                type="button"
                wire:click="procesar"
                wire:loading.attr="disabled"
                wire:target="procesar"
                class="inline-flex w-full items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="procesar">
                    Corregir y generar ZIP
                </span>

                <span wire:loading wire:target="procesar">
                    Procesando archivos...
                </span>
            </button>
        </div>
    </section>

    @if ($errorProceso)
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">
            {{ $errorProceso }}
        </div>
    @endif

    @if ($mensaje)
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800">
            {{ $mensaje }}
        </div>
    @endif

    @if ($procesado)
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Registros', 'value' => $estadisticas['records'] ?? 0],
                ['label' => 'Errores recibidos', 'value' => $estadisticas['errors_received'] ?? 0],
                ['label' => 'Correcciones', 'value' => $estadisticas['corrections'] ?? 0],
                ['label' => 'Pendientes', 'value' => $estadisticas['unresolved'] ?? 0],
            ] as $item)
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500">{{ $item['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                        {{ $item['value'] }}
                    </p>
                </div>
            @endforeach
        </section>

        <section class="rounded-3xl border border-violet-200 bg-white p-6 shadow-sm dark:border-violet-900 dark:bg-neutral-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                        ZIP corregido
                    </h2>

                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ $zipCorregidoName }}
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="descargar"
                    class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    Descargar ZIP corregido
                </button>
            </div>
        </section>
    @endif

    @if ($correcciones !== [])
        <section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="border-b border-emerald-100 px-6 py-5 dark:border-emerald-900">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="font-bold text-emerald-900 dark:text-emerald-200">
                        Correcciones aplicadas
                    </h2>

                    <p class="text-sm text-emerald-700 dark:text-emerald-300">
                        Mostrando {{ count($correcciones) }} correcciones
                    </p>
                </div>
            </div>

            <div class="max-h-[34rem] overflow-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">Fila</th>
                            <th class="px-5 py-3 text-left font-semibold">Variable</th>
                            <th class="px-5 py-3 text-left font-semibold">Tipo</th>
                            <th class="px-5 py-3 text-left font-semibold">Valor anterior</th>
                            <th class="px-5 py-3 text-left font-semibold">Valor nuevo</th>
                            <th class="px-5 py-3 text-left font-semibold">Descripción</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($correcciones as $item)
                            <tr class="align-top">
                                <td class="px-5 py-3 font-medium text-neutral-900 dark:text-white">
                                    {{ $item['record'] }}
                                </td>

                                <td class="px-5 py-3">
                                    {{ $item['variable'] }}
                                </td>

                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-full bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                        {{ $item['type'] ?: 'AUTO' }}
                                    </span>
                                </td>

                                <td class="max-w-xs break-words px-5 py-3 text-red-700 dark:text-red-300">
                                    {{ $item['before'] === '' ? '(vacío)' : $item['before'] }}
                                </td>

                                <td class="max-w-xs break-words px-5 py-3 font-semibold text-emerald-700 dark:text-emerald-300">
                                    {{ $item['after'] === '' ? '(vacío)' : $item['after'] }}
                                </td>

                                <td class="max-w-md break-words px-5 py-3 text-neutral-600 dark:text-neutral-400">
                                    {{ $item['description'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($pendientes !== [])
        <section class="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm dark:border-amber-900 dark:bg-neutral-900">
            <div class="border-b border-amber-100 px-6 py-5">
                <h2 class="font-bold text-amber-900">
                    Reglas pendientes de revisión
                </h2>
            </div>

            <div class="max-h-96 overflow-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm">
                    <thead class="sticky top-0 bg-neutral-50">
                        <tr>
                            <th class="px-5 py-3 text-left">Fila</th>
                            <th class="px-5 py-3 text-left">Variable</th>
                            <th class="px-5 py-3 text-left">Tipo</th>
                            <th class="px-5 py-3 text-left">Descripción</th>
                            <th class="px-5 py-3 text-left">Motivo</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($pendientes as $item)
                            <tr>
                                <td class="px-5 py-3">{{ $item['record'] }}</td>
                                <td class="px-5 py-3">{{ $item['variable'] }}</td>
                                <td class="px-5 py-3">{{ $item['type'] }}</td>
                                <td class="px-5 py-3">{{ $item['description'] }}</td>
                                <td class="px-5 py-3 text-amber-700">{{ $item['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
