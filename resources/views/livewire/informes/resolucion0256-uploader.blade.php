<div class="mx-auto w-full max-w-7xl space-y-6">
    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="max-w-3xl">
            <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                Resolución 0256
            </p>

            <h1 class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                Validación del archivo
            </h1>

            <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                Cargue el archivo Excel para validar su estructura y las reglas
                definidas en el anexo técnico.
            </p>
        </div>
    </section>

    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <form wire:submit="analizar" class="space-y-5">
            <div>
                <label
                    for="archivo"
                    class="block text-sm font-semibold text-neutral-800 dark:text-neutral-200"
                >
                    Archivo Excel
                </label>

                <input
                    id="archivo"
                    type="file"
                    wire:model="archivo"
                    accept=".xlsx,.xls"
                    class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white"
                >

                @error('archivo')
                    <p class="mt-2 text-sm text-red-600">
                        {{ $message }}
                    </p>
                @enderror

                <div wire:loading wire:target="archivo" class="mt-2 text-sm text-blue-600">
                    Cargando archivo...
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="analizar"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="analizar">
                        Analizar archivo
                    </span>

                    <span wire:loading wire:target="analizar">
                        Analizando...
                    </span>
                </button>

                @if ($archivo || $analizado)
                    <button
                        type="button"
                        wire:click="limpiar"
                        class="rounded-xl border border-neutral-300 px-5 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        Limpiar
                    </button>
                @endif
            </div>
        </form>
    </section>

    @if ($analizado)
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Resultado
                </p>

                <p class="mt-2 text-2xl font-bold {{ empty($errores) ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ empty($errores) ? 'Archivo válido' : 'Con errores' }}
                </p>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Total de errores
                </p>

                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                    {{ count($errores) }}
                </p>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Hojas analizadas
                </p>

                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                    {{ count($resumen) }}
                </p>
            </div>
        </section>

        @if (empty($errores))
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="font-semibold text-emerald-800 dark:text-emerald-300">
                    El archivo no presenta errores de validación.
                </p>
            </section>
        @else
            <section class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">
                        Errores encontrados
                    </h2>

                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        Revise y corrija los siguientes campos.
                    </p>
                </div>

                @foreach ($errores as $error)
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-red-900 dark:text-red-200">
                                    {{ $error['field'] ?? 'Error de validación' }}
                                </p>

                                <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                                    Hoja {{ $error['sheet'] ?? 'No disponible' }}

                                    @if (!empty($error['row']))
                                        · Fila {{ $error['row'] }}
                                    @endif

                                    @if (!empty($error['coordinate']))
                                        · Celda {{ $error['coordinate'] }}
                                    @elseif (!empty($error['column']))
                                        · Columna {{ $error['column'] }}
                                    @endif
                                </p>
                            </div>

                            <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-200">
                                Error
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase text-neutral-500">
                                    Valor encontrado
                                </p>

                                <p class="mt-1 text-sm text-neutral-900 dark:text-white">
                                    {{ $error['display_value'] ?? (($error['value'] ?? '') === '' ? '(vacío)' : $error['value']) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase text-neutral-500">
                                    Mensaje
                                </p>

                                <p class="mt-1 text-sm text-neutral-900 dark:text-white">
                                    {{ $error['message'] ?? 'Error no especificado.' }}
                                </p>
                            </div>
                        </div>

                        @if (!empty($error['help']))
                            <div class="mt-4 rounded-lg bg-white p-3 dark:bg-neutral-900">
                                <p class="text-xs font-semibold uppercase text-neutral-500">
                                    Cómo corregirlo
                                </p>

                                <p class="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
                                    {{ $error['help'] }}
                                </p>
                            </div>
                        @endif

@if (!empty($error['allowed_labels']))
    <div class="mt-4">
        <p class="text-xs font-semibold uppercase text-neutral-500">
            Valores permitidos
        </p>

        <div class="mt-2 grid gap-2 md:grid-cols-2">
            @foreach ($error['allowed_labels'] as $codigo => $descripcion)
                <div class="rounded-lg border border-neutral-200 bg-white px-3 py-3 dark:border-neutral-700 dark:bg-neutral-900">
                    <span class="font-semibold text-blue-700">
                        {{ $codigo }}
                    </span>

                    <span class="mx-2 text-neutral-500">=</span>

                    <span class="text-neutral-700 dark:text-neutral-300">
                        {{ $descripcion }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif
                    </div>
                @endforeach
            </section>
        @endif
    @endif
</div>