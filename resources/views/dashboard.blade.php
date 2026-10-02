<x-layouts.app>
    @php
        $modules = [
            [
                'name' => 'Resolución 202',
                'group' => '202',
                'description' => 'Carga, analiza y corrige los archivos de la Resolución 202 antes de su entrega.',
                'route' => 'informes.resolucion-202',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'blue',
                'icon' => 'document',
            ],
            [
    'name' => 'Resolución 0256',
                'group' => '0256',
    'description' => 'Validación del reporte de indicadores de calidad en salud.',
    'route' => 'informes.resolucion-0256',
    'status' => 'Activo',
    'enabled' => true,
    'accent' => 'blue',
    'icon' => 'document',
],

[
    'name' => 'Resolución 1552',
                'group' => '1552',
    'description' => 'Carga un Excel, valida la información y genera el TXT y ZIP oficial de la Resolución 1552.',
    'route' => 'informes.resolucion-1552',
    'status' => 'Activo',
    'enabled' => true,
    'accent' => 'emerald',
    'icon' => 'document',
],

[
    'name' => '1552 · Familiar de Colombia',
                'group' => '1552',
    'description' => 'Convierte el formato QAC-FO26 en TXT ANSI de 14 campos separado por punto y coma.',
    'route' => 'informes.resolucion-1552.familiar-colombia',
    'status' => 'Activo',
    'enabled' => true,
    'accent' => 'cyan',
    'icon' => 'document',
],
[
    'name' => '1604 · Familiar Colombia · CIDSMA',
                'group' => '1604',
    'description' => 'Valida gestión farmacéutica y genera TXT ANSI de 39 campos para el portal SIE de Familiar de Colombia.',
    'route' => 'informes.resolucion-1604.familiar-colombia',
    'status' => 'Activo',
    'enabled' => true,
    'accent' => 'emerald',
    'icon' => 'document',
],
[
    'name' => '1552 · Proteger',
                'group' => '1552',
    'description' => 'Convierte el Excel institucional en TXT UTF-8 de 14 campos y genera el ZIP oficial.',
    'route' => 'informes.resolucion-1552.proteger',
    'status' => 'Activo',
    'enabled' => true,
    'accent' => 'violet',
    'icon' => 'document',
],
[
    'name' => '1552 · Sanitas SIGIRES',
                'group' => '1552',
    'description' => 'Valida el Excel de Sanitas y genera TXT ANSI tabulado dentro de ZIP.',
    'route' => 'informes.resolucion-1552.sanitas',
    'status' => 'Activo',
    'enabled' => true,
    'accent' => 'sky',
    'icon' => 'document',
],
            [
                'name' => 'Gestantes SIGIRES',
                'group' => 'gestantes',
                'description' => 'Accede al módulo de gestantes para corregir, validar y generar archivos oficiales.',
                'route' => 'informes.gestantes',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'emerald',
                'icon' => 'heart',
            ],
            [
                'name' => 'Gestante mensual · SIGIRES',
                'group' => 'gestantes',
                'description' => 'Valida la estructura mensual de 148 campos y genera el XLSX comprimido en ZIP para SIGIRES.',
                'route' => 'informes.gestante-mensual',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'rose',
                'icon' => 'heart',
            ],
            [
                'name' => 'RIPS · Familiar Colombia',
                'group' => 'rips',
                'description' => 'Genera los RIPS mensuales US, AC, AP y AM desde la base anual, conservando la plantilla oficial.',
                'route' => 'informes.rips',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'violet',
                'icon' => 'database',
            ],
            [
                'name' => 'Resolución 4505',
                'group' => 'proximamente',
                'description' => 'Validación y control de calidad de reportes de promoción y prevención.',
                'route' => null,
                'status' => 'Próximamente',
                'enabled' => false,
                'accent' => 'amber',
                'icon' => 'chart',
            ],
            [
                'name' => 'PAIWEB',
                'group' => 'proximamente',
                'description' => 'Procesamiento y validación de información de vacunación.',
                'route' => null,
                'status' => 'Próximamente',
                'enabled' => false,
                'accent' => 'rose',
                'icon' => 'shield',
            ],
            [
                'name' => 'Cuenta de Alto Costo · SIGIRES',
                'group' => 'cronicos',
                'description' => 'Cruza la base regional con historias clínicas y prepara la estructura ERC PRECURSORAS para SIGIRES.',
                'route' => 'informes.sigires-cronicos',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'cyan',
                'icon' => 'clipboard',
            ],
            [
                'name' => 'Crónicos · DUSAKAWI',
                'group' => 'cronicos',
                'description' => 'Corrige el Excel de crónicos a partir del archivo de errores devuelto por DUSAKAWI.',
                'route' => 'informes.cronicos-dusakawi',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'blue',
                'icon' => 'clipboard',
            ],
            [
                'name' => '1604 · DUSAKAWI',
                'group' => '1604',
                'description' => 'Prepara el TXT de medicamentos para cargue en Aryuwi y permite corregir los errores reportados por la EPS.',
                'route' => 'informes.resolucion-1604.dusakawi',
                'status' => 'Activo',
                'enabled' => true,
                'accent' => 'sky',
                'icon' => 'document',
            ],
        ];

        $groupOrder = ['202', '0256', '1604', '1552', 'cronicos', 'gestantes', 'rips', 'proximamente'];

        $groupLabels = [
            '202' => [
                'title' => 'Resolución 202',
                'description' => 'Preparación, validación y corrección de reportes de la Resolución 202.',
            ],
            '0256' => [
                'title' => 'Resolución 0256',
                'description' => 'Validación de indicadores de calidad en salud.',
            ],
            '1604' => [
                'title' => 'Resolución 1604',
                'description' => 'Módulos de medicamentos y gestión farmacéutica por EPS.',
            ],
            '1552' => [
                'title' => 'Resolución 1552',
                'description' => 'Preparación, validación y corrección de archivos para las distintas EPS.',
            ],
            'cronicos' => [
                'title' => 'Crónicos',
                'description' => 'Procesamiento de reportes de pacientes crónicos y Cuenta de Alto Costo.',
            ],
            'gestantes' => [
                'title' => 'Gestantes',
                'description' => 'Procesamiento y validación de reportes de gestantes.',
            ],
            'rips' => [
                'title' => 'RIPS',
                'description' => 'Generación mensual de archivos RIPS a partir de las bases consolidadas.',
            ],
            'proximamente' => [
                'title' => 'Próximamente',
                'description' => 'Módulos que se integrarán en las siguientes etapas.',
            ],
        ];

        $accentClasses = [
            'blue' => [
                'border' => 'border-blue-200 dark:border-blue-900',
                'icon' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300',
                'button' => 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
            ],
            'emerald' => [
                'border' => 'border-emerald-200 dark:border-emerald-900',
                'icon' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
                'button' => 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500',
            ],
            'violet' => [
                'border' => 'border-violet-200 dark:border-violet-900',
                'icon' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/60 dark:text-violet-300',
                'button' => 'bg-violet-600 hover:bg-violet-700 focus:ring-violet-500',
            ],
            'amber' => [
                'border' => 'border-amber-200 dark:border-amber-900',
                'icon' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300',
                'button' => 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
            ],
            'rose' => [
                'border' => 'border-rose-200 dark:border-rose-900',
                'icon' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300',
                'button' => 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500',
            ],
            'cyan' => [
                'border' => 'border-cyan-200 dark:border-cyan-900',
                'icon' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/60 dark:text-cyan-300',
                'button' => 'bg-cyan-600 hover:bg-cyan-700 focus:ring-cyan-500',
            ],
            'sky' => [
                'border' => 'border-sky-200 dark:border-sky-900',
                'icon' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300',
                'button' => 'bg-sky-600 hover:bg-sky-700 focus:ring-sky-500',
            ],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8">
        <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="relative px-6 py-8 sm:px-8 lg:px-10">
                <div class="absolute inset-y-0 right-0 hidden w-1/3 bg-gradient-to-l from-blue-100/70 to-transparent dark:from-blue-950/40 lg:block"></div>
                <div class="relative max-w-3xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300">
                        Centro de procesamiento de informes
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-neutral-900 dark:text-white sm:text-4xl">
                        Sistema de Informes de Salud
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-neutral-600 dark:text-neutral-300 sm:text-base">
                        Selecciona un módulo para acceder a sus herramientas de análisis, validación, corrección y generación.
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Módulos disponibles</p>
                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                    {{ collect($modules)->where('enabled', true)->count() }}
                </p>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Próximos módulos</p>
                <p class="mt-2 text-3xl font-bold text-neutral-900 dark:text-white">
                    {{ collect($modules)->where('enabled', false)->count() }}
                </p>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 sm:col-span-2 lg:col-span-1">
                <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">Estado del sistema</p>
                <div class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    Operativo
                </div>
            </div>
        </section>

        <section>
            <div class="mb-5">
                <h2 class="text-xl font-bold text-neutral-900 dark:text-white">Módulos por grupo</h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    Selecciona el grupo y abre directamente la herramienta que necesites.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($groupOrder as $groupKey)
                    @php
                        $groupModules = collect($modules)->where('group', $groupKey);
                        $groupInfo = $groupLabels[$groupKey];
                        $enabledCount = $groupModules->where('enabled', true)->count();
                    @endphp

                    @if ($groupModules->isNotEmpty())
                        <article class="flex min-h-80 flex-col rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex size-12 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300">
                                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.75 5.75A2 2 0 0 1 6.75 3.75h10.5a2 2 0 0 1 2 2v12.5a2 2 0 0 1-2 2H6.75a2 2 0 0 1-2-2V5.75Z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 8h8M8 12h8M8 16h5"/>
                                    </svg>
                                </div>

                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ $enabledCount }} {{ $enabledCount === 1 ? 'activo' : 'activos' }}
                                </span>
                            </div>

                            <div class="mt-5">
                                <h3 class="text-xl font-bold text-neutral-900 dark:text-white">
                                    {{ $groupInfo['title'] }}
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                                    {{ $groupInfo['description'] }}
                                </p>
                            </div>

                            <div class="mt-5 flex-1 space-y-2">
                                @foreach ($groupModules as $module)
                                    @php $colors = $accentClasses[$module['accent']] ?? $accentClasses['blue']; @endphp

                                    @if ($module['enabled'] && $module['route'])
                                        <a
                                            href="{{ route($module['route']) }}"
                                            wire:navigate
                                            class="flex items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm font-semibold text-neutral-800 transition hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800/70 dark:text-neutral-100 dark:hover:bg-neutral-800"
                                        >
                                            <span>{{ $module['name'] }}</span>
                                            <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                                            </svg>
                                        </a>
                                    @else
                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm font-semibold text-neutral-400 dark:border-neutral-700 dark:bg-neutral-800/40 dark:text-neutral-500">
                                            <span>{{ $module['name'] }}</span>
                                            <span class="text-[11px] uppercase tracking-wide">Próximamente</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
        </section>

    </div>
</x-layouts.app>
