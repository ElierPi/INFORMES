<div class="space-y-6 p-6">

    {{-- Encabezado --}}
    <div>
        <p class="text-sm font-medium text-blue-600">
            Automatización de informes
        </p>

        <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">
            Corrección de informe Resolución 202
        </h1>

        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            Carga el archivo original y el reporte de errores entregado por la EPS.
        </p>
    </div>

    {{-- Formulario --}}
    <form
        wire:submit.prevent="analizar"
        class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm
               dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">

            {{-- EPS --}}
            <div>
                <label
                    for="eps"
                    class="mb-2 block text-sm font-medium text-zinc-700
                           dark:text-zinc-300"
                >
                    EPS
                </label>

                <select
                    id="eps"
                    wire:model="eps"
                    class="w-full rounded-lg border border-zinc-300
                           bg-white px-3 py-2 text-sm
                           dark:border-zinc-600 dark:bg-zinc-800
                           dark:text-white"
                >
                    <option value="proteger">
                        Proteger
                    </option>
                    <option value="dusakawi">
                        Dusakawi
                    </option>
                </select>

                @error('eps')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Fecha de corte --}}
            <div>
                <label
                    for="fechaCorte"
                    class="mb-2 block text-sm font-medium text-zinc-700
                           dark:text-zinc-300"
                >
                    Fecha de corte del informe
                </label>

                <input
                    id="fechaCorte"
                    type="date"
                    wire:model="fechaCorte"
                    class="w-full rounded-lg border border-zinc-300
                           bg-white px-3 py-2 text-sm
                           dark:border-zinc-600 dark:bg-zinc-800
                           dark:text-white"
                >

                <p class="mt-1 text-xs text-zinc-500">
                    Se utiliza para calcular la edad de cada usuario.
                </p>

                @error('fechaCorte')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Archivo original --}}
            <div>
                <label
                    for="archivoInforme"
                    class="mb-2 block text-sm font-medium text-zinc-700
                           dark:text-zinc-300"
                >
                    Archivo original de la 202
                </label>

                <input
                    id="archivoInforme"
                    type="file"
                    wire:model="archivoInforme"
                    accept=".xlsx,.xls"
                    class="block w-full rounded-lg border border-zinc-300
                           bg-white text-sm dark:border-zinc-600
                           dark:bg-zinc-800 dark:text-zinc-300"
                >

                <div
                    wire:loading
                    wire:target="archivoInforme"
                    class="mt-2 text-sm text-blue-600"
                >
                    Subiendo archivo original...
                </div>

                @error('archivoInforme')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            {{-- Reporte de errores --}}
            <div>
                <label
                    for="archivoErrores"
                    class="mb-2 block text-sm font-medium text-zinc-700
                           dark:text-zinc-300"
                >
                    Reporte de errores de la EPS
                </label>

                <input
                    id="archivoErrores"
                    type="file"
                    wire:model="archivoErrores"
                    accept=".xls,.xlsx,.html,.htm,.txt,text/plain"
                    class="block w-full rounded-lg border border-zinc-300
                           bg-white text-sm dark:border-zinc-600
                           dark:bg-zinc-800 dark:text-zinc-300"
                >

                <div
                    wire:loading
                    wire:target="archivoErrores"
                    class="mt-2 text-sm text-blue-600"
                >
                    Subiendo reporte de errores...
                </div>

                @error('archivoErrores')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

        </div>

        {{-- Acciones --}}
 {{-- Acciones --}}
<div class="mt-6 flex flex-wrap items-center gap-3">

    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="analizar"
        style="
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        "
    >
        <span wire:loading.remove wire:target="analizar">
            Analizar y corregir
        </span>

        <span wire:loading wire:target="analizar">
            Analizando informe...
        </span>
    </button>

    @if ($analizado)
        <button
            type="button"
            wire:click="limpiar"
            style="
                background-color: white;
                color: #27272a;
                border: 1px solid #d4d4d8;
                border-radius: 8px;
                padding: 10px 20px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
            "
        >
            Limpiar
        </button>
    @endif

</div>
    </form>
    @if ($procesando)
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
        <p class="font-medium text-blue-800">
            Procesando informe
        </p>

        <p class="mt-1 text-sm text-blue-700">
            {{ $mensajeProceso ?? 'Analizando los archivos...' }}
        </p>
    </div>
@endif

@if ($errorProceso)
    <div class="rounded-lg border border-red-300 bg-red-50 p-4">
        <p class="font-medium text-red-800">
            Ocurrió un error
        </p>

        <p class="mt-1 text-sm text-red-700">
            {{ $errorProceso }}
        </p>
    </div>
@endif

@if ($mensajeProceso && ! $procesando && ! $errorProceso)
    <div class="rounded-lg border border-green-300 bg-green-50 p-4">
        <p class="font-medium text-green-800">
            Proceso terminado
        </p>

        <p class="mt-1 text-sm text-green-700">
            {{ $mensajeProceso }}
        </p>
    </div>
@endif

    {{-- Resultados --}}
    @if ($analizado)

        {{-- Resumen --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">

            <div class="rounded-xl border border-zinc-200 bg-white p-5
                        dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">
                    Registros leídos
                </p>

                <p class="mt-2 text-3xl font-semibold text-zinc-900
                          dark:text-white">
                    {{ $resumen['registros_leidos'] ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5
                        dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">
                    Errores reportados
                </p>

                <p class="mt-2 text-3xl font-semibold text-zinc-900
                          dark:text-white">
                    {{ $resumen['errores_reportados']
                        ?? $resumen['total_errores']
                        ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5
                        dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">
                    Registros afectados
                </p>

                <p class="mt-2 text-3xl font-semibold text-zinc-900
                          dark:text-white">
                    {{ $resumen['registros_afectados'] ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5
                        dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">
                    Celdas corregidas
                </p>

                <p class="mt-2 text-3xl font-semibold text-green-600">
                    {{ $resumen['celdas_corregidas'] ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5
                        dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">
                    Pendientes manuales
                </p>

                <p class="mt-2 text-3xl font-semibold text-amber-600">
                    {{ $resumen['pendientes']
                        ?? $resumen['errores_pendientes']
                        ?? 0 }}
                </p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5
                        dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm text-zinc-500">
                    Errores al calcular edad
                </p>

                <p class="mt-2 text-3xl font-semibold
                          {{ ($resumen['errores_edad'] ?? 0) > 0
                              ? 'text-red-600'
                              : 'text-green-600' }}">
                    {{ $resumen['errores_edad'] ?? 0 }}
                </p>
            </div>

        </div>

        {{-- Botón de descarga --}}
@if ($archivoCorregido)
    <div class="rounded-xl border border-green-300 bg-green-50 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">

            <div>
                <p class="font-semibold text-green-800">
                    Excel corregido disponible
                </p>

                <p class="mt-1 text-sm text-green-700">
                    El archivo fue generado y ya puede descargarse.
                </p>
            </div>

            <button
                type="button"
                wire:click="descargarCorregido"
                wire:loading.attr="disabled"
                wire:target="descargarCorregido"
                style="
                    background-color: #16a34a;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    padding: 10px 20px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                "
            >
                <span
                    wire:loading.remove
                    wire:target="descargarCorregido"
                >
                    Descargar Excel corregido
                </span>

                <span
                    wire:loading
                    wire:target="descargarCorregido"
                >
                    Preparando descarga...
                </span>
            </button>

        </div>
    </div>
@endif

        {{-- Errores de edad --}}
        @if (count($erroresEdad) > 0)
            <div class="overflow-hidden rounded-xl border border-red-300
                        bg-white dark:bg-zinc-900">

                <div class="border-b border-red-200 bg-red-50 px-6 py-4
                            dark:bg-red-950">
                    <h2 class="font-semibold text-red-800 dark:text-red-200">
                        Registros con problemas en la edad
                    </h2>

                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                        Estos registros no pueden usar reglas dependientes
                        de edad hasta corregir la fecha de nacimiento.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200
                                  dark:divide-zinc-700">

                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs">
                                    Registro
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Fila Excel
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Fecha de nacimiento
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Problema
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200
                                      dark:divide-zinc-700">
                            @foreach ($erroresEdad as $errorEdad)
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $errorEdad['registro'] }}
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        {{ $errorEdad['fila_excel'] }}
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        {{ $errorEdad['fecha_nacimiento'] }}
                                    </td>

                                    <td class="px-4 py-3 text-sm text-red-700
                                               dark:text-red-300">
                                        {{ $errorEdad['detalle'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            </div>
        @endif

        {{-- Correcciones automáticas --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200
                    bg-white dark:border-zinc-700 dark:bg-zinc-900">

            <div class="border-b border-zinc-200 px-6 py-4
                        dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">
                    Correcciones automáticas
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200
                              dark:divide-zinc-700">

                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs">
                                Registro
                            </th>

                            <th class="px-4 py-3 text-left text-xs">
                                Variable
                            </th>

                            <th class="px-4 py-3 text-left text-xs">
                                Celda
                            </th>

                            <th class="px-4 py-3 text-left text-xs">
                                Anterior
                            </th>

                            <th class="px-4 py-3 text-left text-xs">
                                Nuevo
                            </th>

                            <th class="px-4 py-3 text-left text-xs">
                                Motivo
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200
                                  dark:divide-zinc-700">

                        @forelse ($correcciones as $correccion)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    {{ $correccion['registro'] }}
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    {{ $correccion['variable'] }}
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    {{ $correccion['celda'] }}
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    {{ $correccion['valor_anterior'] }}
                                </td>

                                <td class="px-4 py-3 text-sm font-semibold
                                           text-green-600">
                                    {{ $correccion['valor_nuevo'] }}
                                </td>

                                <td class="px-4 py-3 text-sm text-zinc-600
                                           dark:text-zinc-400">
                                    {{ $correccion['motivo'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-sm
                                           text-zinc-500"
                                >
                                    No se aplicaron correcciones automáticas.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pendientes --}}
        @if (count($pendientes) > 0)
            <div class="overflow-hidden rounded-xl border border-amber-300
                        bg-white dark:bg-zinc-900">

                <div class="border-b border-amber-200 bg-amber-50 px-6 py-4
                            dark:bg-amber-950">
                    <h2 class="font-semibold text-amber-800
                               dark:text-amber-200">
                        Errores que requieren revisión manual
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200
                                  dark:divide-zinc-700">

                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs">
                                    Código
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Registro
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Variable
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Campo
                                </th>

                                <th class="px-4 py-3 text-left text-xs">
                                    Detalle
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200
                                      dark:divide-zinc-700">

                            @foreach ($pendientes as $pendiente)
                                <tr>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $pendiente['codigo'] ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        {{ $pendiente['fila']
                                            ?? $pendiente['registro']
                                            ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        {{ $pendiente['variable']
                                            ?? 'No identificada' }}
                                    </td>

                                    <td class="px-4 py-3 text-sm">
                                        {{ $pendiente['campo'] ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3 text-sm text-amber-700
                                               dark:text-amber-300">
                                        {{ $pendiente['detalle']
                                            ?? $pendiente['motivo']
                                            ?? 'Revisión requerida.' }}
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @endif

</div>