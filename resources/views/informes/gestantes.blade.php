<x-layouts.app>
    @php
        $tools = [
            [
                'name' => 'Validar Excel',
                'description' => 'Carga el archivo de gestantes, valida su estructura y contenido, muestra los errores encontrados y genera TXT y ZIP cuando el archivo es válido.',
                'route' => 'informes.gestantes.validar-excel',
                'status' => 'Activo',
                'enabled' => true,
                'icon' => 'validation',
            ],
            [
                'name' => 'Corrección SIGIRES',
                'description' => 'Carga el Excel original y el archivo de errores SIGIRES, aplica las correcciones y genera los archivos finales.',
                'route' => 'informes.gestantes.correccion-sigires',
                'status' => 'Activo',
                'enabled' => true,
                'icon' => 'correction',
            ],
            [
                'name' => 'Generar TXT y ZIP',
                'description' => 'Genera directamente los archivos de entrega a partir de un Excel previamente validado.',
                'route' => null,
                'status' => 'Próximamente',
                'enabled' => false,
                'icon' => 'generate',
            ],
            [
                'name' => 'Historial de procesos',
                'description' => 'Consulta los archivos procesados, resultados obtenidos y fechas de generación.',
                'route' => null,
                'status' => 'Próximamente',
                'enabled' => false,
                'icon' => 'history',
            ],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7">
        <nav class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <a href="{{ route('dashboard') }}" wire:navigate class="transition hover:text-neutral-900 dark:hover:text-white">
                Inicio
            </a>
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
            </svg>
            <span class="font-medium text-neutral-900 dark:text-white">Gestantes SIGIRES</span>
        </nav>

        <section class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-neutral-900">
            <div class="relative px-6 py-8 sm:px-8 lg:px-10">
                <div class="absolute inset-y-0 right-0 hidden w-2/5 bg-gradient-to-l from-emerald-100/80 to-transparent dark:from-emerald-950/40 lg:block"></div>

                <div class="relative flex max-w-3xl items-start gap-5">
                    <div class="hidden size-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 sm:flex">
                        <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20.25S4.5 15.75 4.5 9.75A4.5 4.5 0 0 1 12 6.4a4.5 4.5 0 0 1 7.5 3.35c0 6-7.5 10.5-7.5 10.5Z"/>
                        </svg>
                    </div>

                    <div>
                        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                            Módulo activo
                        </div>

                        <h1 class="text-3xl font-bold tracking-tight text-neutral-900 dark:text-white sm:text-4xl">
                            Gestantes SIGIRES
                        </h1>

                        <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-300 sm:text-base">
                            Selecciona la herramienta que necesites para validar, corregir y generar los archivos del reporte de gestantes.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="mb-5">
                <h2 class="text-xl font-bold text-neutral-900 dark:text-white">Herramientas del módulo</h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    La validación del Excel y la corrección SIGIRES están disponibles.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                @foreach ($tools as $tool)
                    <article class="flex min-h-64 flex-col rounded-2xl border bg-white p-6 shadow-sm transition dark:bg-neutral-900 {{ $tool['enabled'] ? 'border-emerald-200 hover:-translate-y-1 hover:shadow-lg dark:border-emerald-900' : 'border-neutral-200 opacity-80 dark:border-neutral-700' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex size-12 items-center justify-center rounded-2xl {{ $tool['enabled'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-neutral-100 text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400' }}">
                                @switch($tool['icon'])
                                    @case('validation')
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3.75 19.5 6v5.25c0 4.8-3.15 8.1-7.5 9.75-4.35-1.65-7.5-4.95-7.5-9.75V6L12 3.75Z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 12 2 2 4-4"/>
                                        </svg>
                                        @break
                                    @case('correction')
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.5 19.5h15M6.75 16.5 15.9 7.35a1.6 1.6 0 0 1 2.25 0l.5.5a1.6 1.6 0 0 1 0 2.25L9.5 19.25l-3 .75.75-3.5Z"/>
                                        </svg>
                                        @break
                                    @case('generate')
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7.5 3.75h6l3 3V20.25H7.5V3.75Z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.5 3.75v3h3M12 10.5v6M9.75 14.25 12 16.5l2.25-2.25"/>
                                        </svg>
                                        @break
                                    @default
                                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.75v5.25l3.75 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                        </svg>
                                @endswitch
                            </div>

                            <span class="{{ $tool['enabled'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300' }} rounded-full px-2.5 py-1 text-xs font-semibold">
                                {{ $tool['status'] }}
                            </span>
                        </div>

                        <div class="mt-5 flex-1">
                            <h3 class="text-lg font-bold text-neutral-900 dark:text-white">{{ $tool['name'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-400">{{ $tool['description'] }}</p>
                        </div>

                        <div class="mt-6">
                            @if ($tool['enabled'] && $tool['route'])
                                <a href="{{ route($tool['route']) }}" wire:navigate class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                                    Entrar
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                                    </svg>
                                </a>
                            @else
                                <button disabled class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-xl bg-neutral-100 px-4 py-2.5 text-sm font-semibold text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500">
                                    Disponible próximamente
                                </button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>