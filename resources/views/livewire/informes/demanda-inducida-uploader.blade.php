<div class="mx-auto w-full max-w-7xl space-y-6">
    <section class="rounded-3xl border border-teal-200 bg-white p-6 shadow-sm dark:border-teal-900 dark:bg-neutral-900">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-600 dark:text-teal-400">
                    SIGIRES · Otros
                </p>
                <h1 class="mt-1 text-2xl font-bold text-neutral-900 dark:text-white">
                    Demanda inducida
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                    Valida y corrige la estructura de 35 campos, genera el XLSX con el nombre oficial y lo comprime en ZIP para SIGIRES.
                    Los pendientes no bloquean la descarga: los errores reales de SIGIRES se podrán automatizar después en el corrector.
                </p>
            </div>

            <a href="{{ route('dashboard') }}" wire:navigate
               class="text-sm font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-300 dark:hover:text-white">
                Volver al panel
            </a>
        </div>
    </section>

    @if ($errorMessage)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
            {{ $errorMessage }}
        </div>
    @endif

    <section class="grid gap-5 lg:grid-cols-3">
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 lg:col-span-2">
            <label class="text-sm font-semibold text-neutral-900 dark:text-white">
                Excel Demanda inducida (.xlsx)
            </label>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Debe contener los 35 campos oficiales, desde TIPO DOC hasta Fecha de asignacion de cita.
            </p>

            <input type="file" wire:model="archivo" accept=".xlsx"
                   class="mt-4 block w-full rounded-xl border border-neutral-300 bg-white text-sm text-neutral-700
                          file:mr-4 file:border-0 file:bg-teal-50 file:px-4 file:py-3 file:font-semibold file:text-teal-700
                          dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-200">

            @error('archivo')
                <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <label class="text-sm font-semibold text-neutral-900 dark:text-white">
                IPS / Prestador
            </label>

            <select wire:model.live="prestadorSeleccionado"
                    class="mt-3 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5 text-sm dark:border-neutral-700 dark:bg-neutral-950">
                @foreach ($prestadores as $prestador)
                    <option value="{{ $prestador['code'] }}">
                        {{ $prestador['name'] }} · {{ $prestador['code'] }}
                    </option>
                @endforeach
                <option value="__nuevo__">+ Agregar nueva IPS</option>
            </select>

            <div class="mt-3 rounded-xl bg-neutral-100 px-3 py-2.5 text-sm font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                Código: {{ $codigoHabilitacion }}
            </div>
        </div>
    </section>

    @if ($mostrarNuevoPrestador)
        <section class="grid gap-4 rounded-2xl border border-teal-200 bg-teal-50/60 p-5 dark:border-teal-900 dark:bg-teal-950/20 md:grid-cols-2">
            <div>
                <label class="text-sm font-semibold">Nombre IPS</label>
                <input type="text" wire:model="nuevoPrestadorNombre"
                       class="mt-2 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5">
                @error('nuevoPrestadorNombre')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="text-sm font-semibold">Código habilitación</label>
                <input type="text" maxlength="12" wire:model="nuevoPrestadorCodigo"
                       class="mt-2 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2.5">
                @error('nuevoPrestadorCodigo')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <button wire:click="guardarPrestador"
                        class="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
                    Guardar IPS
                </button>
            </div>
        </section>
    @endif

    @if ($prestadorMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $prestadorMessage }}
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <button wire:click="procesar" wire:loading.attr="disabled"
                class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="procesar">Validar, corregir y preparar</span>
            <span wire:loading wire:target="procesar">Procesando...</span>
        </button>

        <button wire:click="nuevoProceso"
                class="rounded-xl border border-neutral-300 bg-white px-5 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">
            Nuevo proceso
        </button>
    </div>

    @if ($resultado)
        <section class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Registros</p>
                    <p class="mt-2 text-3xl font-bold">{{ $resultado['records'] }}</p>
                </div>
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Correcciones</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $resultado['correction_count'] }}</p>
                </div>
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <p class="text-xs uppercase tracking-wide text-neutral-500">Pendientes</p>
                    <p class="mt-2 text-3xl font-bold {{ $resultado['warning_count'] ? 'text-amber-600' : 'text-emerald-600' }}">
                        {{ $resultado['warning_count'] }}
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="font-semibold text-emerald-900 dark:text-emerald-300">
                    Archivos preparados para SIGIRES
                </p>
                <p class="mt-1 break-all text-sm text-emerald-700">
                    {{ $resultado['zip_name'] }}
                </p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button wire:click="descargarZip"
                            class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">
                        Descargar ZIP SIGIRES
                    </button>

                    <button wire:click="descargarExcel"
                            class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                        Descargar Excel corregido
                    </button>
                </div>
            </div>

            @if ($resultado['correction_count'])
                <div class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
                    <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4">
                        <h2 class="font-semibold text-emerald-900">
                            Correcciones automáticas
                        </h2>
                    </div>

                    <div class="max-h-80 overflow-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="sticky top-0 bg-neutral-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Fila</th>
                                    <th class="px-4 py-3 text-left">Campo</th>
                                    <th class="px-4 py-3 text-left">Anterior</th>
                                    <th class="px-4 py-3 text-left">Nuevo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @foreach (array_slice($resultado['corrections'], 0, 150) as $item)
                                    <tr>
                                        <td class="px-4 py-3">{{ $item['row'] }}</td>
                                        <td class="px-4 py-3">{{ $item['field'] }} · {{ $item['name'] }}</td>
                                        <td class="max-w-56 break-words px-4 py-3">{{ ($item['old'] ?? '') === '' ? '—' : $item['old'] }}</td>
                                        <td class="max-w-56 break-words px-4 py-3 font-semibold text-emerald-700">{{ ($item['new'] ?? '') === '' ? '—' : $item['new'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($resultado['warning_count'])
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
                    <h2 class="font-semibold text-amber-900 dark:text-amber-300">
                        Pendientes de revisión
                    </h2>
                    <p class="mt-1 text-xs text-amber-700">
                        No bloquean la descarga. Carga el ZIP en SIGIRES y luego usaremos el LOG real para automatizar las reglas faltantes.
                    </p>

                    <div class="mt-4 max-h-80 space-y-2 overflow-auto">
                        @foreach (array_slice($resultado['warnings'], 0, 200) as $warning)
                            <div class="rounded-lg bg-white/80 px-3 py-2 text-xs text-amber-900">
                                {{ $warning }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    <section class="mt-10 space-y-5 rounded-3xl border border-indigo-200 bg-white p-6 shadow-sm dark:border-indigo-900 dark:bg-neutral-900">
        <div>
            <p class="text-sm font-semibold text-indigo-600">Demanda inducida · Corrección SIGIRES</p>
            <h2 class="mt-1 text-xl font-bold">Corregir informe rechazado</h2>
            <p class="mt-2 max-w-3xl text-sm text-neutral-600 dark:text-neutral-400">
                Carga el mismo ZIP enviado a SIGIRES y el LOG de errores. El sistema corrige
                todas las reglas conocidas de una vez y vuelve a generar el ZIP con el mismo nombre oficial.
            </p>
        </div>

        @if ($correccionErrorMessage)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $correccionErrorMessage }}
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div class="rounded-2xl border border-neutral-200 p-5">
                <label class="text-sm font-semibold">ZIP cargado en SIGIRES</label>
                <input type="file" wire:model="zipRechazado" accept=".zip" class="mt-3 block w-full text-sm">
                @error('zipRechazado') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="rounded-2xl border border-neutral-200 p-5">
                <label class="text-sm font-semibold">LOG de errores SIGIRES</label>
                <input type="file" wire:model="archivoErrores" accept=".xls,.xlsx" class="mt-3 block w-full text-sm">
                @error('archivoErrores') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <button wire:click="corregirDevolucion" wire:loading.attr="disabled"
                class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
            <span wire:loading.remove wire:target="corregirDevolucion">Analizar y corregir todo</span>
            <span wire:loading wire:target="corregirDevolucion">Corrigiendo...</span>
        </button>

        @if ($correccionResultado)
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-neutral-200 p-5">
                    <p class="text-xs uppercase text-neutral-500">Errores LOG</p>
                    <p class="mt-2 text-3xl font-bold">{{ $correccionResultado['error_count'] }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-xs uppercase text-emerald-700">Cambios aplicados</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700">{{ $correccionResultado['change_count'] }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-xs uppercase text-amber-700">Sin regla</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ $correccionResultado['pending_count'] }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="font-semibold text-emerald-900">ZIP corregido listo</p>
                <p class="mt-1 text-sm text-emerald-700">{{ $correccionResultado['zip_name'] }}</p>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button wire:click="descargarZipCorregido"
                            class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white">
                        Descargar ZIP corregido
                    </button>
                    <button wire:click="descargarExcelCorregidoDevolucion"
                            class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-800">
                        Descargar Excel corregido
                    </button>
                </div>
            </div>
        @endif
    </section>

</div>
