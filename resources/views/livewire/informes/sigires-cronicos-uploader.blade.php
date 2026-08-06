<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-cyan-200 bg-white shadow-sm dark:border-cyan-900 dark:bg-neutral-900">
        <div class="relative px-6 py-8 sm:px-8 lg:px-10">
            <div class="absolute inset-y-0 right-0 hidden w-2/5 bg-gradient-to-l from-cyan-100/80 to-transparent dark:from-cyan-950/40 lg:block"></div>

            <div class="relative max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700 dark:border-cyan-900 dark:bg-cyan-950/50 dark:text-cyan-300">
                    Cuenta de Alto Costo · SIGIRES
                </div>

                <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white sm:text-4xl">
                    Pacientes crónicos – ERC PRECURSORAS
                </h1>

                <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-300 sm:text-base">
                    Cruza la base regional con las historias clínicas, extrae datos clínicos, valida la estructura vigente y genera una matriz de revisión antes del TXT definitivo.
                </p>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
        <form wire:submit="preparar" class="space-y-6">
            <div class="grid gap-5 lg:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">Base regional de usuarios</span>
                    <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">Excel con identificación, IPS y marcadores HTA, DM y ERC.</span>
                    <input
                        type="file"
                        wire:model="baseUsuarios"
                        accept=".xlsx,.xls"
                        class="mt-3 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-700 file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:font-semibold file:text-cyan-700 hover:file:bg-cyan-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200 dark:file:bg-cyan-950 dark:file:text-cyan-300"
                    >
                    @error('baseUsuarios')
                        <span class="mt-2 block text-sm font-medium text-red-600">{{ $message }}</span>
                    @enderror
                    <span wire:loading wire:target="baseUsuarios" class="mt-2 block text-xs text-cyan-700 dark:text-cyan-300">Cargando Excel...</span>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">ZIP de historias clínicas</span>
                    <span class="mt-1 block text-xs text-neutral-500 dark:text-neutral-400">Debe contener los PDF nombrados con tipo y número de documento.</span>
                    <input
                        type="file"
                        wire:model="historiasClinicas"
                        accept=".zip"
                        class="mt-3 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-700 file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:font-semibold file:text-cyan-700 hover:file:bg-cyan-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200 dark:file:bg-cyan-950 dark:file:text-cyan-300"
                    >
                    @error('historiasClinicas')
                        <span class="mt-2 block text-sm font-medium text-red-600">{{ $message }}</span>
                    @enderror
                    <span wire:loading wire:target="historiasClinicas" class="mt-2 block text-xs text-cyan-700 dark:text-cyan-300">Cargando ZIP...</span>
                </label>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <label class="block">
                    <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">Fecha de corte</span>
                    <input
                        type="date"
                        wire:model="fechaCorte"
                        class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-800 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200"
                    >
                    @error('fechaCorte')
                        <span class="mt-2 block text-sm font-medium text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">Código EAPB</span>
                    <input
                        type="text"
                        wire:model="codigoEapb"
                        maxlength="6"
                        placeholder="Ej. EPS005"
                        class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm uppercase text-neutral-800 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200"
                    >
                    @error('codigoEapb')
                        <span class="mt-2 block text-sm font-medium text-red-600">{{ $message }}</span>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">Procedimiento</span>
                    <select
                        wire:model="procedimiento"
                        class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-800 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200"
                    >
                        <option value="PRECURSORAS">PRECURSORAS</option>
                        <option value="DIALISIS">DIALISIS</option>
                        <option value="TMND">TMND</option>
                        <option value="NEFRO">NEFRO</option>
                        <option value="TRASPLANTE">TRASPLANTE</option>
                    </select>
                    @error('procedimiento')
                        <span class="mt-2 block text-sm font-medium text-red-600">{{ $message }}</span>
                    @enderror
                </label>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                El módulo no inventa fechas diagnósticas, afiliación, resultados de laboratorio ni datos de BDUA. Cuando una variable obligatoria no puede confirmarse, la deja en la hoja <strong>PENDIENTES</strong> de la matriz.
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="preparar,baseUsuarios,historiasClinicas"
                    class="inline-flex items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="preparar">Cruzar y preparar matriz</span>
                    <span wire:loading wire:target="preparar">Procesando historias clínicas...</span>
                </button>

                @if ($analizado)
                    <button
                        type="button"
                        wire:click="reiniciar"
                        class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        Nuevo proceso
                    </button>
                @endif
            </div>
        </form>
    </section>

    @if ($mensaje)
        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 px-5 py-4 text-sm font-medium text-cyan-900 dark:border-cyan-900 dark:bg-cyan-950/30 dark:text-cyan-200">
            {{ $mensaje }}
        </div>
    @endif

    @if ($error)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            {{ $error }}
        </div>
    @endif

    @if ($analizado)
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            @php
                $cards = [
                    ['label' => 'Historias', 'value' => $resumen['histories_total'] ?? 0],
                    ['label' => 'Coincidencias', 'value' => $resumen['matched_total'] ?? 0],
                    ['label' => 'Sin coincidencia', 'value' => $resumen['unmatched_total'] ?? 0],
                    ['label' => 'Listos', 'value' => $resumen['ready_total'] ?? 0],
                    ['label' => 'Con pendientes', 'value' => $resumen['pending_total'] ?? 0],
                    ['label' => 'Campos pendientes', 'value' => $resumen['pending_fields_total'] ?? 0],
                ];
            @endphp

            @foreach ($cards as $card)
                <article class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">{{ $card['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">Archivos del proceso</h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        Código IPS: <strong>{{ $resumen['provider_code'] ?? 'Por confirmar' }}</strong> · Estructura vigente: 143 campos.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if ($matrizPath)
                        <button
                            type="button"
                            wire:click="descargarMatriz"
                            class="rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800"
                        >
                            Descargar matriz de revisión
                        </button>
                    @endif

                    @if ($txtPath && $zipPath)
                        <button
                            type="button"
                            wire:click="descargarTxt"
                            class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800"
                        >
                            Descargar TXT
                        </button>
                        <button
                            type="button"
                            wire:click="descargarZip"
                            class="rounded-xl bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-black dark:bg-neutral-100 dark:text-neutral-900"
                        >
                            Descargar ZIP
                        </button>
                    @endif
                </div>
            </div>

            @if (! $zipPath)
                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    El TXT no se generó todavía porque existen variables obligatorias pendientes. Descarga la matriz, completa la hoja <strong>ESTRUCTURA_SIGIRES</strong> sin cambiar sus columnas y vuelve a subirla aquí.
                </div>

                <div class="mt-5 rounded-2xl border border-cyan-200 bg-cyan-50/60 p-5 dark:border-cyan-900 dark:bg-cyan-950/20">
                    <h3 class="font-bold text-cyan-950 dark:text-cyan-100">Generar archivo definitivo desde la matriz revisada</h3>
                    <p class="mt-1 text-sm leading-6 text-cyan-800 dark:text-cyan-300">
                        Completa los valores pendientes en la hoja ESTRUCTURA_SIGIRES. El sistema volverá a validar los 143 campos y solo generará el TXT/ZIP cuando todo esté correcto.
                    </p>
                    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="block flex-1">
                            <span class="text-sm font-semibold text-neutral-800 dark:text-neutral-200">Matriz de revisión completada</span>
                            <input
                                type="file"
                                wire:model="matrizCompletada"
                                accept=".xlsx"
                                class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-700 file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-100 file:px-4 file:py-2 file:font-semibold file:text-cyan-800 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200"
                            >
                            <span wire:loading wire:target="matrizCompletada" class="mt-2 block text-xs text-cyan-700">Cargando matriz...</span>
                        </label>
                        <button
                            type="button"
                            wire:click="generarDesdeMatriz"
                            wire:loading.attr="disabled"
                            wire:target="generarDesdeMatriz,matrizCompletada"
                            class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50"
                        >
                            <span wire:loading.remove wire:target="generarDesdeMatriz">Validar y generar TXT/ZIP</span>
                            <span wire:loading wire:target="generarDesdeMatriz">Validando 143 campos...</span>
                        </button>
                    </div>
                </div>
            @endif
        </section>

        @if ($advertencias !== [])
            <section class="overflow-hidden rounded-3xl border border-amber-200 bg-white shadow-sm dark:border-amber-900 dark:bg-neutral-900">
                <div class="border-b border-amber-200 bg-amber-50 px-6 py-4 dark:border-amber-900 dark:bg-amber-950/30">
                    <h2 class="font-bold text-amber-900 dark:text-amber-200">Advertencias del cruce</h2>
                </div>
                <ul class="space-y-2 px-6 py-5 text-sm leading-6 text-neutral-700 dark:text-neutral-300">
                    @foreach ($advertencias as $warning)
                        <li class="flex gap-3">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span>{{ $warning }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="border-b border-neutral-200 px-6 py-5 dark:border-neutral-700">
                <h2 class="text-xl font-bold text-neutral-900 dark:text-white">Pacientes procesados</h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Resumen del cruce entre la base regional y cada PDF.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                    <thead class="bg-neutral-50 dark:bg-neutral-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-neutral-700 dark:text-neutral-200">Documento</th>
                            <th class="px-4 py-3 text-left font-semibold text-neutral-700 dark:text-neutral-200">Paciente</th>
                            <th class="px-4 py-3 text-left font-semibold text-neutral-700 dark:text-neutral-200">Condiciones</th>
                            <th class="px-4 py-3 text-left font-semibold text-neutral-700 dark:text-neutral-200">Pendientes</th>
                            <th class="px-4 py-3 text-left font-semibold text-neutral-700 dark:text-neutral-200">Estado</th>
                            <th class="px-4 py-3 text-left font-semibold text-neutral-700 dark:text-neutral-200">Archivo HC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        @foreach ($pacientes as $patient)
                            <tr wire:key="sigires-patient-{{ $patient['document_number'] }}" class="align-top hover:bg-neutral-50 dark:hover:bg-neutral-800/60">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-neutral-900 dark:text-white">
                                    {{ $patient['document_type'] }} {{ $patient['document_number'] }}
                                </td>
                                <td class="min-w-56 px-4 py-3 text-neutral-700 dark:text-neutral-300">
                                    {{ $patient['name'] ?: 'Sin nombre en la base' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if ($patient['hta'])
                                            <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-950 dark:text-blue-300">HTA</span>
                                        @endif
                                        @if ($patient['dm'])
                                            <span class="rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-950 dark:text-violet-300">DM</span>
                                        @endif
                                        @if ($patient['erc'])
                                            <span class="rounded-full bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-300">ERC</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="min-w-80 px-4 py-3 text-neutral-700 dark:text-neutral-300">
                                    <div class="font-medium">{{ $patient['pending'] }} campos pendientes</div>
                                    @if ($patient['validation_issues'] > 0)
                                        <div class="mt-1 text-xs font-medium text-red-600">{{ $patient['validation_issues'] }} validaciones Tipo A</div>
                                    @endif

                                    @if (($patient['pending_items'] ?? []) !== [] || ($patient['validation_items'] ?? []) !== [])
                                        <details class="mt-2 rounded-lg border border-neutral-200 bg-neutral-50 p-2 dark:border-neutral-700 dark:bg-neutral-800">
                                            <summary class="cursor-pointer text-xs font-semibold text-cyan-700 dark:text-cyan-300">Ver detalle</summary>
                                            <div class="mt-2 space-y-2 text-xs leading-5">
                                                @foreach ($patient['pending_items'] ?? [] as $pending)
                                                    <div>
                                                        <strong>{{ $pending['code'] ?? 'Campo' }} · {{ $pending['field'] ?? '' }}</strong><br>
                                                        <span class="text-neutral-500 dark:text-neutral-400">{{ $pending['reason'] ?? 'Requiere confirmación.' }}</span>
                                                    </div>
                                                @endforeach
                                                @foreach ($patient['validation_items'] ?? [] as $issue)
                                                    <div class="text-red-700 dark:text-red-300">
                                                        <strong>{{ $issue['code'] ?? 'Validación' }} · {{ $issue['field'] ?? '' }}</strong><br>
                                                        {{ $issue['message'] ?? '' }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif

                                    @php($clinical = $patient['clinical_values'] ?? [])
                                    <div class="mt-2 flex flex-wrap gap-1 text-[11px] text-neutral-500 dark:text-neutral-400">
                                        @foreach (['weight' => 'Peso', 'height' => 'Talla', 'pas' => 'TAS', 'pad' => 'TAD', 'creatinine' => 'Creat.', 'hba1c' => 'HbA1c', 'tfg' => 'TFG'] as $key => $label)
                                            @if (($clinical[$key] ?? null) !== null)
                                                <span class="rounded bg-white px-1.5 py-0.5 dark:bg-neutral-900">{{ $label }}: {{ $clinical[$key] }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($patient['ready'])
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Listo</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300">Revisión</span>
                                    @endif
                                    @foreach ($patient['warnings'] as $warning)
                                        <p class="mt-2 max-w-80 text-xs leading-5 text-amber-700 dark:text-amber-300">{{ $warning }}</p>
                                    @endforeach
                                </td>
                                <td class="max-w-64 break-all px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">{{ $patient['file_name'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
