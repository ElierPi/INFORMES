<div class="space-y-6">
    @if (session()->has('success'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('correction_success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-800">
            {{ session('correction_success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm">
        <div class="mb-5">
            <p class="text-sm font-semibold text-blue-600">
                Gestantes SIGIRES · Corrector
            </p>

            <h1 class="mt-1 text-2xl font-bold text-gray-900">
                Corregir informe rechazado
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">
                Carga directamente el ZIP generado por Gestante semanal y el LOG de errores devuelto por SIGIRES.
                El corrector modifica el TXT dentro del ZIP usando las filas y variables que reportó la plataforma.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="text-sm font-semibold text-gray-900">
                    ZIP del informe enviado a SIGIRES
                </label>

                <p class="mt-1 text-xs text-gray-500">
                    Usa exactamente el ZIP que descargaste desde Gestante semanal.
                </p>

                <input
                    type="file"
                    wire:model="archivoInforme"
                    accept=".zip"
                    class="mt-3 block w-full rounded-xl border border-gray-300 bg-white text-sm"
                >

                @error('archivoInforme')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                <div
                    wire:loading
                    wire:target="archivoInforme"
                    class="mt-2 text-sm text-blue-600"
                >
                    Cargando ZIP...
                </div>
            </div>

            <div>
                <label class="text-sm font-semibold text-gray-900">
                    LOG de errores SIGIRES
                </label>

                <p class="mt-1 text-xs text-gray-500">
                    Archivo .xls o .xlsx descargado desde SIGIRES.
                </p>

                <input
                    type="file"
                    wire:model="archivoErrores"
                    accept=".xls,.xlsx"
                    class="mt-3 block w-full rounded-xl border border-gray-300 bg-white text-sm"
                >

                @error('archivoErrores')
                    <p class="mt-2 text-sm font-medium text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                <div
                    wire:loading
                    wire:target="archivoErrores"
                    class="mt-2 text-sm text-blue-600"
                >
                    Cargando errores...
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button
                type="button"
                wire:click="analyze"
                wire:loading.attr="disabled"
                wire:target="analyze,archivoInforme,archivoErrores"
                class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="analyze">
                    Analizar errores
                </span>

                <span wire:loading wire:target="analyze">
                    Analizando...
                </span>
            </button>
        </div>
    </div>

    @if ($analyzed)
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-gray-500">
                    Total de errores
                </p>
                <p class="mt-2 text-3xl font-bold text-gray-900">
                    {{ $totalErrors }}
                </p>
            </div>

            <div class="rounded-2xl border border-green-200 bg-green-50 p-5">
                <p class="text-sm font-medium text-green-700">
                    Potencialmente automáticos
                </p>
                <p class="mt-2 text-3xl font-bold text-green-800">
                    {{ $automaticErrors }}
                </p>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <p class="text-sm font-medium text-amber-700">
                    Requieren revisión
                </p>
                <p class="mt-2 text-3xl font-bold text-amber-800">
                    {{ $manualErrors }}
                </p>
            </div>
        </div>

        @if ($metadata !== [])
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-gray-900">
                    Información del procesamiento
                </h2>

                <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @if (! empty($metadata['radicado']))
                        <div>
                            <p class="font-medium text-gray-500">Radicado</p>
                            <p class="mt-1 text-gray-900">{{ $metadata['radicado'] }}</p>
                        </div>
                    @endif

                    @if (! empty($metadata['processed_file']))
                        <div>
                            <p class="font-medium text-gray-500">Archivo procesado</p>
                            <p class="mt-1 break-all text-gray-900">{{ $metadata['processed_file'] }}</p>
                        </div>
                    @endif

                    @if (! empty($metadata['processed_at']))
                        <div>
                            <p class="font-medium text-gray-500">Fecha del proceso</p>
                            <p class="mt-1 text-gray-900">{{ $metadata['processed_at'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-bold text-gray-900">
                    Errores encontrados
                </h2>
            </div>

            <div class="max-h-[520px] overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="sticky top-0 bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Fila</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Variable</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Valor anterior</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Descripción</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">Clasificación</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @forelse ($parsedErrors as $error)
                            <tr>
                                <td class="px-4 py-3">{{ $error['row'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $error['column'] ?? '—' }}</td>
                                <td class="max-w-xs break-words px-4 py-3">
                                    {{ ($error['old_value'] ?? '') !== '' ? $error['old_value'] : '—' }}
                                </td>
                                <td class="min-w-96 px-4 py-3">
                                    {{ $error['description'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if (($error['classification'] ?? '') === 'automatic')
                                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                                            Automático
                                        </span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                            Manual
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                    No se encontraron errores procesables.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">
                        Corrección directa del ZIP
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Se extrae el TXT, se aplican las reglas automáticas y se vuelve a comprimir.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="correct"
                    wire:loading.attr="disabled"
                    wire:target="correct"
                    class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="correct">
                        Corregir automáticamente
                    </span>

                    <span wire:loading wire:target="correct">
                        Corrigiendo...
                    </span>
                </button>
            </div>
        </div>

        @if ($corrected)
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-green-200 bg-green-50 p-5">
                    <p class="text-sm font-medium text-green-700">
                        Correcciones aplicadas
                    </p>
                    <p class="mt-2 text-3xl font-bold text-green-800">
                        {{ $correctedCount }}
                    </p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm font-medium text-amber-700">
                        Pendientes
                    </p>
                    <p class="mt-2 text-3xl font-bold text-amber-800">
                        {{ $pendingCount }}
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                <h2 class="text-lg font-bold text-emerald-900">
                    Archivos corregidos
                </h2>

                <p class="mt-1 text-sm text-emerald-800">
                    El ZIP contiene el TXT corregido y puede volver a cargarse en SIGIRES.
                </p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        wire:click="downloadGeneratedZip"
                        class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
                    >
                        Descargar ZIP corregido
                    </button>

                    <button
                        type="button"
                        wire:click="downloadGeneratedTxt"
                        class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100"
                    >
                        Descargar TXT corregido
                    </button>
                </div>
            </div>

            @if ($changes !== [])
                <div class="overflow-hidden rounded-2xl border border-green-200 bg-white shadow-sm">
                    <div class="border-b border-green-100 bg-green-50 px-6 py-4">
                        <h2 class="text-lg font-bold text-green-900">
                            Cambios realizados
                        </h2>
                    </div>

                    <div class="max-h-[420px] overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="sticky top-0 bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Fila</th>
                                    <th class="px-4 py-3 text-left">Variable</th>
                                    <th class="px-4 py-3 text-left">Campo</th>
                                    <th class="px-4 py-3 text-left">Anterior</th>
                                    <th class="px-4 py-3 text-left">Nuevo</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                @foreach ($changes as $change)
                                    <tr>
                                        <td class="px-4 py-3">{{ $change['report_row'] ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $change['report_column'] ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $change['field'] ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ ($change['old_value'] ?? '') === '' ? '—' : $change['old_value'] }}</td>
                                        <td class="px-4 py-3 font-semibold text-green-700">{{ ($change['new_value'] ?? '') === '' ? '—' : $change['new_value'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($manualCorrections !== [])
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="text-lg font-bold text-amber-900">
                        Pendientes sin regla automática
                    </h2>

                    <div class="mt-4 max-h-72 space-y-2 overflow-auto">
                        @foreach ($manualCorrections as $item)
                            <div class="rounded-xl bg-white/80 px-4 py-3 text-sm text-amber-900">
                                Fila {{ $item['report_row'] ?? '—' }},
                                variable {{ $item['report_column'] ?? '—' }}:
                                {{ $item['reason'] ?? 'Revisión manual.' }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    @endif
</div>
