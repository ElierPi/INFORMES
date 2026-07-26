<div class="space-y-6">

    {{-- Encabezado --}}
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Reporte semanal de gestantes
        </h1>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Cargue el archivo Excel, valide su contenido, corrija automáticamente
            los errores permitidos y genere los archivos requeridos para SIGIRES.
        </p>
    </div>

    {{-- Mensajes generales --}}
    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @error('correction')
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            {{ $message }}
        </div>
    @enderror

    {{-- Carga del archivo --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <label for="archivoGestantes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Archivo Excel de gestantes
        </label>

        <input
            id="archivoGestantes"
            type="file"
            wire:model="archivo"
            accept=".xlsx,.xls"
            class="mt-2 block w-full rounded-lg border border-gray-300 bg-white p-2 text-sm text-gray-900 file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-200"
        >

        @error('archivo')
            <p class="mt-2 text-sm font-medium text-red-600">
                {{ $message }}
            </p>
        @enderror

        <div wire:loading wire:target="archivo" class="mt-3 text-sm font-medium text-blue-600">
            Cargando archivo...
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            <button
                type="button"
                wire:click="analyze"
                wire:loading.attr="disabled"
                wire:target="analyze,archivo"
                class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="analyze">Analizar archivo</span>
                <span wire:loading wire:target="analyze">Procesando archivo...</span>
            </button>

            @if ($analyzed && $errorsReport !== [])
                <button
                    type="button"
                    wire:click="autoCorrect"
                    wire:loading.attr="disabled"
                    wire:target="autoCorrect"
                    class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="autoCorrect">Corregir automáticamente</span>
                    <span wire:loading wire:target="autoCorrect">Corrigiendo archivo...</span>
                </button>
            @endif

            @if (! empty($correctedFile))
                <button
                    type="button"
                    wire:click="downloadCorrectedExcel"
                    wire:loading.attr="disabled"
                    wire:target="downloadCorrectedExcel"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="downloadCorrectedExcel">Descargar Excel corregido</span>
                    <span wire:loading wire:target="downloadCorrectedExcel">Preparando descarga...</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Correcciones automáticas realizadas --}}
    @if (! empty($corrections))
        <div class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                <h2 class="font-semibold text-emerald-800 dark:text-emerald-300">
                    Correcciones automáticas realizadas
                </h2>

                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                    Se realizaron {{ $totalCorrections }}
                    {{ $totalCorrections === 1 ? 'corrección' : 'correcciones' }}.
                </p>
            </div>

            <div class="max-h-[500px] overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 bg-gray-50 dark:bg-neutral-800">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Hoja</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Fila</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Columna</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Campo</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Valor anterior</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Valor nuevo</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-neutral-800">
                        @foreach ($corrections as $correction)
                            @php
                                $oldValue = $correction['old_value'] ?? $correction['valor_anterior'] ?? null;
                                $newValue = $correction['new_value'] ?? $correction['valor_nuevo'] ?? null;
                            @endphp

                            <tr wire:key="gestantes-correction-{{ $loop->index }}" class="hover:bg-gray-50 dark:hover:bg-neutral-800/60">
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $correction['sheet'] ?? $correction['hoja'] ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $correction['row'] ?? $correction['fila'] ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $correction['column'] ?? $correction['columna'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $correction['field'] ?? $correction['campo'] ?? '—' }}
                                </td>
                                <td class="max-w-52 break-words px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $oldValue === null || $oldValue === '' ? '—' : $oldValue }}
                                </td>
                                <td class="max-w-52 break-words px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ $newValue === null || $newValue === '' ? '—' : $newValue }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Resultados --}}
    @if ($analyzed)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($summary as $item)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $item['sheet'] }}
                    </p>

                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $item['records'] }}
                    </p>

                    @if ($item['errors'] > 0)
                        <p class="mt-1 text-sm font-medium text-red-600">
                            {{ $item['errors'] }} {{ $item['errors'] === 1 ? 'error' : 'errores' }}
                        </p>
                    @else
                        <p class="mt-1 text-sm font-medium text-green-600">Sin errores</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($errorsReport !== [])
            <div class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-neutral-900">
                <div class="border-b border-red-100 bg-red-50 px-5 py-4 dark:border-red-900 dark:bg-red-950/30">
                    <h2 class="font-semibold text-red-800 dark:text-red-300">Errores encontrados</h2>

                    <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                        Se encontraron {{ count($errorsReport) }}
                        {{ count($errorsReport) === 1 ? 'error' : 'errores' }}
                        que deben corregirse antes de generar el archivo.
                    </p>

                    @if (! empty($correctedFile))
                        <p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">
                            El archivo corregido ya está disponible para descargar.
                            Los errores restantes requieren revisión manual.
                        </p>
                    @endif
                </div>

                <div class="max-h-[600px] overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="sticky top-0 bg-gray-50 dark:bg-neutral-800">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Hoja</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Fila</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Columna</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Campo</th>
                                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Valor</th>
                                <th class="min-w-80 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Detalle</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-neutral-800">
                            @foreach ($errorsReport as $error)
                                <tr wire:key="gestantes-error-{{ $loop->index }}" class="hover:bg-gray-50 dark:hover:bg-neutral-800/60">
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $error['sheet'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $error['row'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $error['column'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $error['field'] ?? '—' }}</td>
                                    <td class="max-w-52 break-words px-4 py-3 text-gray-700 dark:text-gray-300">
                                        @if (array_key_exists('value', $error) && $error['value'] !== null && $error['value'] !== '')
                                            {{ $error['value'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-red-700 dark:text-red-400">{{ $error['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950/30">
                <h2 class="text-lg font-semibold text-green-800 dark:text-green-300">
                    Archivo validado correctamente
                </h2>

                <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                    No se encontraron errores. El archivo TXT y el ZIP fueron generados correctamente.
                </p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        wire:click="downloadTxt"
                        wire:loading.attr="disabled"
                        wire:target="downloadTxt"
                        class="rounded-lg bg-green-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-800 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="downloadTxt">Descargar TXT</span>
                        <span wire:loading wire:target="downloadTxt">Descargando...</span>
                    </button>

                    <button
                        type="button"
                        wire:click="downloadZip"
                        wire:loading.attr="disabled"
                        wire:target="downloadZip"
                        class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-black disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                    >
                        <span wire:loading.remove wire:target="downloadZip">Descargar ZIP</span>
                        <span wire:loading wire:target="downloadZip">Descargando...</span>
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>