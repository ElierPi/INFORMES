<div class="space-y-7">
    <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="px-6 py-7 sm:px-8">
            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">Validar informe</span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">Resolución 0256</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-300">Carga el Excel para revisar su estructura y aplicar las reglas de validación configuradas. Consulta los resultados por tipo de registro y ubica los campos que requieren corrección.</p>
        </div>
    </section>

    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:p-8">
        <form wire:submit="analizar" class="space-y-6">
            <div class="space-y-3">
                <label for="archivo-0256-{{ $inputVersion }}" class="block text-sm font-semibold text-neutral-900 dark:text-white">Archivo Excel de la Resolución 0256</label>
                <input id="archivo-0256-{{ $inputVersion }}" wire:key="archivo-0256-{{ $inputVersion }}" type="file" wire:model="archivo" accept=".xlsx,.xls" aria-describedby="archivo-0256-ayuda" wire:loading.attr="disabled" wire:target="analizar,limpiar" class="block w-full rounded-xl border border-neutral-300 bg-neutral-50 p-4 text-sm text-neutral-700 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-100 file:px-4 file:py-2 file:font-semibold file:text-blue-800 focus-visible:outline-2 focus-visible:outline-blue-600 dark:border-neutral-700 dark:bg-neutral-950/40 dark:text-neutral-200 dark:file:bg-blue-950 dark:file:text-blue-200">
                <p id="archivo-0256-ayuda" class="text-xs leading-5 text-neutral-500 dark:text-neutral-400">XLSX o XLS, máximo 30 MB. Incluye las hojas Tipo 1 a Tipo 5 o sus nombres equivalentes configurados. La hoja Tipo 6 es opcional.</p>
                <p class="text-xs leading-5 text-neutral-500 dark:text-neutral-400">Sexo en este Excel: F = Femenino; M = Masculino. Se conservan los valores originales. Esta codificación de entrada es distinta de H/M/I del anexo técnico.</p>
                @error('archivo')
                    <p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
                <p wire:loading wire:target="archivo" role="status" class="text-sm text-blue-700 dark:text-blue-300">Cargando archivo...</p>
            </div>

            <div class="flex flex-wrap gap-3 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                <button type="submit" wire:loading.attr="disabled" wire:target="archivo,analizar,limpiar" @disabled(! $archivo) class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-50">
                    <span wire:loading.remove wire:target="analizar">Analizar Excel</span>
                    <span wire:loading wire:target="analizar">Analizando...</span>
                </button>
                <button type="button" wire:click="limpiar" wire:loading.attr="disabled" wire:target="archivo,analizar,limpiar" class="rounded-xl border border-neutral-300 px-5 py-3 text-sm font-semibold text-neutral-800 disabled:opacity-50 dark:border-neutral-700 dark:text-neutral-200">Limpiar</button>
            </div>
        </form>
    </section>

    @if ($analizado)
        <div role="status" @class([
            'rounded-2xl border p-4 text-sm font-medium',
            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' => $errores === [],
            'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200' => $errores !== [],
        ])>
            @if ($errores === [])
                Análisis completado sin errores según las reglas configuradas.
            @else
                Análisis completado: {{ count($errores) }} error(es). Revisa el detalle y corrige el Excel antes de volver a analizarlo.
            @endif
        </div>

        <section aria-label="Resumen por tipo de registro" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($resumen as $key => $item)
                <div wire:key="resumen-0256-{{ $key }}" class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <h2 class="font-semibold text-neutral-900 dark:text-white">{{ $item['sheet'] }}</h2>
                    <p class="mt-2 text-2xl font-bold text-neutral-900 dark:text-white">{{ $item['records'] }} <span class="text-sm font-normal text-neutral-500 dark:text-neutral-400">registro(s)</span></p>
                    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $item['errors'] }} error(es)</p>
                </div>
            @endforeach
        </section>

        @if ($errores !== [])
            <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="border-b border-neutral-200 px-6 py-4 font-bold text-neutral-900 dark:border-neutral-800 dark:text-white">Detalle de errores</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-700">
                        <caption class="sr-only">Ubicación y descripción de los errores encontrados en el Excel</caption>
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-950/40 dark:text-neutral-300">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">Hoja</th>
                                <th scope="col" class="px-4 py-3 text-left">Fila</th>
                                <th scope="col" class="px-4 py-3 text-left">Columna</th>
                                <th scope="col" class="px-4 py-3 text-left">Campo</th>
                                <th scope="col" class="px-4 py-3 text-left">Valor</th>
                                <th scope="col" class="px-4 py-3 text-left">Error y orientación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 text-neutral-800 dark:divide-neutral-800 dark:text-neutral-200">
                            @foreach ($errores as $index => $item)
                                <tr wire:key="error-0256-{{ $index }}">
                                    <td class="whitespace-nowrap px-4 py-3">{{ $item['sheet'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['row'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['column_letter'] ?? $item['column'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $item['field'] ?? 'Estructura de la hoja' }}</td>
                                    <td class="max-w-xs break-words px-4 py-3">{{ isset($item['value']) && (string) $item['value'] !== '' ? $item['value'] : '(vacío)' }}</td>
                                    <td class="min-w-64 px-4 py-3">
                                        <p>{{ $item['message'] }}</p>
                                        @if (! empty($item['help']))
                                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $item['help'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif
</div>
