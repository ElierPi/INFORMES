<div class="space-y-6">
    {{-- Carga de archivos --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">
                Corrección de errores SIGIRES
            </h1>

            <p class="mt-2 text-sm text-gray-600">
                Carga el Excel original del informe y el archivo de errores
                descargado desde SIGIRES.
            </p>
        </div>

        @if (session()->has('success'))
            <div
                class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
                {{ session('success') }}
            </div>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Excel original --}}
            <div>
                <label
                    for="archivoInforme"
                    class="mb-2 block text-sm font-semibold text-gray-700"
                >
                    Excel original del informe
                </label>

                <input
                    id="archivoInforme"
                    type="file"
                    wire:model="archivoInforme"
                    accept=".xlsx,.xls"
                    class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm"
                >

                @error('archivoInforme')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                <div
                    wire:loading
                    wire:target="archivoInforme"
                    class="mt-2 text-sm text-blue-600"
                >
                    Cargando informe...
                </div>
            </div>

            {{-- Archivo de errores --}}
            <div>
                <label
                    for="archivoErrores"
                    class="mb-2 block text-sm font-semibold text-gray-700"
                >
                    Archivo de errores SIGIRES
                </label>

                <input
                    id="archivoErrores"
                    type="file"
                    wire:model="archivoErrores"
                    accept=".xls,.xlsx"
                    class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm"
                >

                @error('archivoErrores')
                    <p class="mt-2 text-sm text-red-600">
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
                class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
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
        {{-- Resumen --}}
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

        {{-- Información del procesamiento --}}
        @if ($metadata !== [])
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-bold text-gray-900">
                    Información del procesamiento
                </h2>

                <div class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @if (! empty($metadata['radicado']))
                        <div>
                            <p class="font-medium text-gray-500">
                                Radicado
                            </p>

                            <p class="mt-1 text-gray-900">
                                {{ $metadata['radicado'] }}
                            </p>
                        </div>
                    @endif

                    @if (! empty($metadata['processed_file']))
                        <div>
                            <p class="font-medium text-gray-500">
                                Archivo procesado
                            </p>

                            <p class="mt-1 break-all text-gray-900">
                                {{ $metadata['processed_file'] }}
                            </p>
                        </div>
                    @endif

                    @if (! empty($metadata['processed_at']))
                        <div>
                            <p class="font-medium text-gray-500">
                                Fecha del proceso
                            </p>

                            <p class="mt-1 text-gray-900">
                                {{ $metadata['processed_at'] }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Tabla de errores --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-bold text-gray-900">
                    Errores encontrados
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                Fila
                            </th>

                            <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                Columna
                            </th>

                            <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                Valor anterior
                            </th>

                            <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                Descripción
                            </th>

                            <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                Regla
                            </th>

                            <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                Clasificación
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($parsedErrors as $error)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                    {{ $error['row'] ?? '—' }}
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                    {{ $error['column'] ?? '—' }}
                                </td>

                                <td class="max-w-xs px-4 py-3 text-gray-700">
                                    {{ ($error['old_value'] ?? '') !== ''
                                        ? $error['old_value']
                                        : '—' }}
                                </td>

                                <td class="min-w-96 px-4 py-3 text-gray-700">
                                    {{ $error['description'] ?? '—' }}
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">
                                    {{ $error['rule'] ?? 'Sin regla' }}
                                </td>

                                <td class="whitespace-nowrap px-4 py-3">
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
                                <td
                                    colspan="6"
                                    class="px-6 py-10 text-center text-gray-500"
                                >
                                    No se encontraron errores procesables.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Acción de corrección --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">
                        Corrección automática
                    </h2>

                    <p class="mt-1 text-sm text-gray-600">
                        Se creará una copia del Excel. El archivo original no será modificado.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="correct"
                    wire:loading.attr="disabled"
                    wire:target="correct"
                    class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
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

        {{-- Resultado de la corrección --}}
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
                        Pendientes de revisión
                    </p>

                    <p class="mt-2 text-3xl font-bold text-amber-800">
                        {{ $pendingCount }}
                    </p>
                </div>
            </div>

            {{-- Descarga --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">
                            Excel corregido
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Descarga la copia corregida para revisar los cambios.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="downloadCorrectedExcel"
                        wire:loading.attr="disabled"
                        wire:target="downloadCorrectedExcel"
                        class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="downloadCorrectedExcel">
                            Descargar Excel corregido
                        </span>

                        <span wire:loading wire:target="downloadCorrectedExcel">
                            Preparando descarga...
                        </span>
                    </button>
                </div>
            </div>

            
            {{-- Generación de archivos SIGIRES --}}
            <div class="rounded-2xl border border-indigo-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">
                            Generar archivos oficiales
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Valida el Excel corregido y genera el TXT y el ZIP oficiales para SIGIRES.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="generateTxtAndZip"
                        wire:loading.attr="disabled"
                        wire:target="generateTxtAndZip"
                        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="generateTxtAndZip">
                            Generar TXT y ZIP
                        </span>

                        <span wire:loading wire:target="generateTxtAndZip">
                            Generando...
                        </span>
                    </button>
                </div>

                @error('generation')
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700 text-sm">
                        {{ $message }}
                    </div>
                @enderror

                @if(session()->has('generation_success'))
                    <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-700 text-sm">
                        {{ session('generation_success') }}
                    </div>
                @endif
            </div>

            @if($artifactsGenerated)
                <div class="rounded-2xl border border-green-200 bg-green-50 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-bold text-green-900">
                                Archivos generados correctamente
                            </h2>

                            <p class="mt-1 text-sm text-green-700">
                                El ZIP contiene el TXT listo para cargar en SIGIRES.
                            </p>
                        </div>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                wire:click="downloadGeneratedTxt"
                                class="rounded-xl border border-green-300 bg-white px-5 py-2.5 text-sm font-semibold text-green-700 hover:bg-green-100"
                            >
                                Descargar TXT
                            </button>

                            <button
                                type="button"
                                wire:click="downloadGeneratedZip"
                                class="rounded-xl bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800"
                            >
                                Descargar ZIP
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if(!empty($finalValidationErrors))
                <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                    <div class="border-b border-amber-200 bg-amber-50 px-6 py-4">
                        <h2 class="text-lg font-bold text-amber-900">
                            Validaciones pendientes
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Hoja</th>
                                    <th class="px-4 py-3 text-left">Fila</th>
                                    <th class="px-4 py-3 text-left">Campo</th>
                                    <th class="px-4 py-3 text-left">Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($finalValidationErrors as $error)
                                    <tr>
                                        <td class="px-4 py-3">{{ $error['sheet'] ?? $error['hoja'] ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $error['row'] ?? $error['fila'] ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $error['field'] ?? $error['campo'] ?? '—' }}</td>
                                        <td class="px-4 py-3 text-red-700">{{ $error['message'] ?? $error['mensaje'] ?? 'Error de validación' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif


            {{-- Cambios realizados --}}
            @if ($changes !== [])
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h2 class="text-lg font-bold text-gray-900">
                            Cambios realizados
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Hoja
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Celda
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Valor anterior
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Valor nuevo
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Regla
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Motivo
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($changes as $change)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $change['sheet'] ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            {{ $change['cell'] ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ ($change['old_value'] ?? '') !== ''
                                                ? $change['old_value']
                                                : 'Vacío' }}
                                        </td>

                                        <td class="px-4 py-3 font-medium text-green-700">
                                            {{ $change['new_value'] ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $change['rule'] ?? '—' }}
                                        </td>

                                        <td class="min-w-80 px-4 py-3 text-gray-700">
                                            {{ $change['reason'] ?? 'Corrección automática aplicada.' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Pendientes manuales --}}
            @if ($manualCorrections !== [])
                <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                    <div class="border-b border-amber-200 bg-amber-50 px-6 py-4">
                        <h2 class="text-lg font-bold text-amber-900">
                            Pendientes de revisión manual
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Fila
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Columna
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Valor
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Regla
                                    </th>

                                    <th class="px-4 py-3 text-left font-semibold text-gray-600">
                                        Motivo
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($manualCorrections as $pending)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $pending['report_row'] ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $pending['report_column'] ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ ($pending['old_value'] ?? '') !== ''
                                                ? $pending['old_value']
                                                : 'Vacío' }}
                                        </td>

                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $pending['rule'] ?? 'Sin regla' }}
                                        </td>

                                        <td class="min-w-80 px-4 py-3 text-amber-800">
                                            {{ $pending['reason'] ?? 'Requiere revisión.' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    @endif
</div>