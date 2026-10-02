<div class="mx-auto w-full max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="border-b border-neutral-200 px-6 py-6 dark:border-neutral-700 sm:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                        Resolución 202
                    </p>

                    <h1 class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">
                        Preparar informe para cargar
                    </h1>

                    <p class="mt-2 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        Organiza un Excel o TXT y genera el formato requerido por
                        PROTEGER o DUSAKAWI en UTF-8, separado por <strong>|</strong>.
                    </p>
                </div>

                <a
                    href="{{ route('informes.resolucion-202') }}"
                    wire:navigate
                    class="inline-flex items-center justify-center rounded-xl border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 dark:border-neutral-600 dark:text-neutral-200 dark:hover:bg-neutral-800"
                >
                    Volver al módulo
                </a>
            </div>
        </div>

        <div class="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1.2fr_.8fr]">
            <div class="space-y-5">
                <fieldset>
                    <legend class="mb-3 text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                        Entidad destino
                    </legend>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="cursor-pointer rounded-2xl border p-4 transition {{ $destino === 'proteger' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100 dark:bg-blue-950/30' : 'border-neutral-200 dark:border-neutral-700' }}">
                            <input
                                type="radio"
                                wire:model.live="destino"
                                value="proteger"
                                class="sr-only"
                            >

                            <span class="block font-bold text-neutral-900 dark:text-white">
                                PROTEGER
                            </span>

                            <span class="mt-1 block text-xs leading-5 text-neutral-600 dark:text-neutral-400">
                                Genera únicamente registros tipo 2 de 119 campos.
                            </span>
                        </label>

                        <label class="cursor-pointer rounded-2xl border p-4 transition {{ $destino === 'dusakawi' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-100 dark:bg-emerald-950/30' : 'border-neutral-200 dark:border-neutral-700' }}">
                            <input
                                type="radio"
                                wire:model.live="destino"
                                value="dusakawi"
                                class="sr-only"
                            >

                            <span class="block font-bold text-neutral-900 dark:text-white">
                                DUSAKAWI
                            </span>

                            <span class="mt-1 block text-xs leading-5 text-neutral-600 dark:text-neutral-400">
                                Genera registro tipo 1 y después los registros tipo 2.
                            </span>
                        </label>

                        <label class="cursor-pointer rounded-2xl border p-4 transition {{ $destino === 'familiar_colombia' ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100 dark:bg-violet-950/30' : 'border-neutral-200 dark:border-neutral-700' }}">
                            <input
                                type="radio"
                                wire:model.live="destino"
                                value="familiar_colombia"
                                class="sr-only"
                            >

                            <span class="block font-bold text-neutral-900 dark:text-white">
                                FAMILIAR DE COLOMBIA
                            </span>

                            <span class="mt-1 block text-xs leading-5 text-neutral-600 dark:text-neutral-400">
                                Genera TXT ANSI y lo comprime en ZIP para SIGIRES.
                            </span>
                        </label>
                    </div>

                    @error('destino')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                        Excel o TXT del informe
                    </label>

                    <input
                        type="file"
                        wire:model="archivo"
                        accept=".xlsx,.xls,.txt"
                        class="block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-700 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:border-neutral-600 dark:bg-neutral-950 dark:text-neutral-200"
                    >

                    @error('archivo')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <div wire:loading wire:target="archivo" class="mt-2 text-sm font-semibold text-blue-600">
                        Cargando archivo...
                    </div>

                    @if ($archivo)
                        <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                            Archivo cargado correctamente:
                            <strong>{{ $archivo->getClientOriginalName() }}</strong>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-neutral-800 dark:text-neutral-200">
                        Fecha de corte para leer Excel
                    </label>

                    <input
                        type="date"
                        wire:model="fechaCorte"
                        class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-800 dark:border-neutral-600 dark:bg-neutral-950 dark:text-neutral-200"
                    >

                    <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                        Solo es obligatoria cuando el archivo de entrada es Excel.
                    </p>

                    @error('fechaCorte')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @if ($destino === 'proteger')
                    <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-5 dark:border-blue-900 dark:bg-blue-950/20">
                        <h2 class="font-bold text-blue-900 dark:text-blue-200">
                            Datos para PROTEGER
                        </h2>

                        <div class="mt-4">
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">
                                NIT para el nombre del TXT
                            </label>

                            <input
                                type="text"
                                inputmode="numeric"
                                maxlength="12"
                                wire:model="nitProteger"
                                placeholder="Ej. 900144397"
                                class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-600 dark:bg-neutral-950"
                            >

                            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                El archivo se generará como NIT_MMYYYY.txt. Escríbelo sin puntos ni guiones.
                            </p>

                            @error('nitProteger')
                                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                @if ($destino === 'dusakawi')
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <h2 class="font-bold text-emerald-900 dark:text-emerald-200">
                            Registro tipo 1 de DUSAKAWI
                        </h2>

                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">
                                    Código EPS
                                </label>

                                <input
                                    type="text"
                                    wire:model="codigoEps"
                                    placeholder="EPSI01"
                                    class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-600 dark:bg-neutral-950"
                                >

                                @error('codigoEps')
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">
                                    Fecha inicial
                                </label>

                                <input
                                    type="date"
                                    wire:model="fechaInicial"
                                    class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-600 dark:bg-neutral-950"
                                >

                                @error('fechaInicial')
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">
                                    Fecha final
                                </label>

                                <input
                                    type="date"
                                    wire:model="fechaFinal"
                                    class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-600 dark:bg-neutral-950"
                                >

                                @error('fechaFinal')
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-emerald-800 dark:text-emerald-300">
                            Salida: 1|Código EPS|Fecha inicial|Fecha final|Total de registros
                        </p>
                    </div>
                @endif

                @if ($destino === 'familiar_colombia')
                    <div class="rounded-2xl border border-violet-200 bg-violet-50/60 p-5 dark:border-violet-900 dark:bg-violet-950/20">
                        <h2 class="font-bold text-violet-900 dark:text-violet-200">
                            Datos para Familiar de Colombia
                        </h2>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">
                                    IPS que reporta
                                </label>

                                <select
                                    wire:model="ipsSeleccionada"
                                    class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-600 dark:bg-neutral-950"
                                >
                                    <option value="cidsma">
                                        CIDSMA — 444300120001
                                    </option>

                                    <option value="wayuu_anashii">
                                        WAYUU ANASHII — 444300063502
                                    </option>
                                </select>

                                @error('ipsSeleccionada')
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">
                                    Fecha final del periodo
                                </label>

                                <input
                                    type="date"
                                    wire:model="fechaCorte"
                                    class="w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-sm dark:border-neutral-600 dark:bg-neutral-950"
                                >

                                @error('fechaCorte')
                                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-violet-800 dark:text-violet-300">
                            El sistema generará CODIGOHABILITACIONIPS_DDMMAAAA.zip y dentro incluirá el TXT con el mismo nombre.
                        </p>
                    </div>
                @endif

                <button
                    type="button"
                    wire:click="analizar"
                    wire:loading.attr="disabled"
                    wire:target="analizar"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="analizar">
                        Organizar y generar TXT
                    </span>

                    <span wire:loading wire:target="analizar">
                        Procesando informe...
                    </span>
                </button>
            </div>

            <aside class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
                <h2 class="font-bold text-blue-900 dark:text-blue-200">
                    Estructura de salida
                </h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Destino</dt>
                        <dd class="font-semibold uppercase text-blue-950 dark:text-blue-100">
                            {{ $destino }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Registro tipo 1</dt>
                        <dd class="font-semibold text-blue-950 dark:text-blue-100">
                            {{ $destino === 'dusakawi' ? 'Sí' : 'No' }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Campos tipo 2</dt>
                        <dd class="font-semibold text-blue-950 dark:text-blue-100">119</dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Separador</dt>
                        <dd class="font-semibold text-blue-950 dark:text-blue-100">|</dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Codificación</dt>
                        <dd class="font-semibold text-blue-950 dark:text-blue-100">
                            {{ $destino === 'familiar_colombia' ? 'ANSI / Windows-1252' : 'UTF-8 sin BOM' }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Salida</dt>
                        <dd class="font-semibold text-blue-950 dark:text-blue-100">
                            {{ $destino === 'familiar_colombia' ? 'ZIP con TXT' : 'TXT' }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-blue-700 dark:text-blue-300">Salto final</dt>
                        <dd class="font-semibold text-blue-950 dark:text-blue-100">No</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </section>

    @if ($errorProceso)
        <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            {{ $errorProceso }}
        </div>
    @endif

    @if ($mensaje)
        <div class="rounded-2xl border {{ $errores === [] ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200' }} p-5 text-sm">
            {{ $mensaje }}
        </div>
    @endif

    @if ($analizado)
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['label' => 'Registros', 'value' => $resumen['records_count'] ?? 0],
                ['label' => 'Válidos', 'value' => $resumen['valid_records_count'] ?? 0],
                ['label' => 'Inválidos', 'value' => $resumen['invalid_records_count'] ?? 0],
                ['label' => 'Errores estructurales', 'value' => $resumen['errors_count'] ?? 0],
                ['label' => 'Ajustes', 'value' => $resumen['warnings_count'] ?? 0],
            ] as $item)
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ $item['label'] }}
                    </p>

                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                        {{ $item['value'] }}
                    </p>
                </div>
            @endforeach
        </section>
    @endif

    @if ($generado)
        <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-neutral-900 dark:text-white">
                        TXT listo para cargar
                    </h2>

                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ $destino === 'familiar_colombia' ? $zipName : $txtName }}
                    </p>
                </div>

                @if ($destino === 'familiar_colombia')
                    <button
                        type="button"
                        wire:click="descargarZip"
                        class="inline-flex items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-violet-700"
                    >
                        Descargar ZIP
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="descargarTxt"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        Descargar TXT
                    </button>
                @endif
            </div>
        </section>
    @endif

    @if ($errores !== [])
        <section class="overflow-hidden rounded-3xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-neutral-900">
            <div class="border-b border-red-100 px-6 py-5 dark:border-red-900">
                <h2 class="text-lg font-bold text-red-900 dark:text-red-200">
                    Errores estructurales que bloquean la salida
                </h2>
            </div>

            <div class="max-h-[34rem] overflow-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="sticky top-0 bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">Registro</th>
                            <th class="px-5 py-3 text-left font-semibold">Línea/Fila</th>
                            <th class="px-5 py-3 text-left font-semibold">Campo</th>
                            <th class="px-5 py-3 text-left font-semibold">Valor</th>
                            <th class="px-5 py-3 text-left font-semibold">Detalle</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($errores as $error)
                            <tr>
                                <td class="px-5 py-3">{{ $error['record'] }}</td>
                                <td class="px-5 py-3">{{ $error['source_line'] }}</td>
                                <td class="px-5 py-3">{{ $error['field'] }}</td>
                                <td class="px-5 py-3">{{ $error['value'] === null || $error['value'] === '' ? '(vacío)' : $error['value'] }}</td>
                                <td class="px-5 py-3 text-red-700 dark:text-red-300">{{ $error['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($advertencias !== [])
        <section class="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm dark:border-amber-900 dark:bg-neutral-900">
            <div class="border-b border-amber-100 px-6 py-5 dark:border-amber-900">
                <h2 class="text-lg font-bold text-amber-900 dark:text-amber-200">
                    Ajustes automáticos realizados
                </h2>
            </div>

            <div class="max-h-80 overflow-auto divide-y divide-neutral-100 dark:divide-neutral-800">
                @foreach ($advertencias as $warning)
                    <div class="px-6 py-4 text-sm">
                        <p class="font-semibold text-neutral-900 dark:text-white">
                            Registro {{ $warning['record'] }}
                            · Línea/Fila {{ $warning['source_line'] }}
                            · Variable {{ $warning['variable'] }}
                        </p>

                        <p class="mt-1 text-neutral-600 dark:text-neutral-400">
                            {{ $warning['message'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
