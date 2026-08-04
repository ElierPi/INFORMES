<x-layouts.app>
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8">
        <section class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <div class="px-6 py-8 sm:px-8 lg:px-10">
                <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                    Módulo normativo
                </p>

                <h1 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900 dark:text-white">
                    Resolución 202
                </h1>

                <p class="mt-3 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-400 sm:text-base">
                    Prepara el archivo antes de cargarlo o corrige los errores reportados posteriormente por la EPS.
                </p>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="flex min-h-80 flex-col rounded-3xl border border-blue-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-blue-900 dark:bg-neutral-900">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7.5 3.75h6l3 3V20.25H7.5V3.75Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.5 3.75v3h3M9.75 11.25h4.5M9.75 14.25h4.5"/>
                    </svg>
                </div>

                <div class="mt-6 flex-1">
                    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">
                        Preparar informe
                    </h2>

                    <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        Carga un Excel o TXT, organiza las variables 0–118, valida la estructura y descarga un TXT UTF-8 separado por | listo para cargar.
                    </p>
                </div>

                <a
                    href="{{ route('informes.resolucion-202.preparar') }}"
                    wire:navigate
                    class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-700"
                >
                    Abrir preparación
                </a>
            </article>

            <article class="flex min-h-80 flex-col rounded-3xl border border-emerald-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-emerald-900 dark:bg-neutral-900">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                    <svg class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </div>

                <div class="mt-6 flex-1">
                    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">
                        Corregir errores de la EPS
                    </h2>

                    <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        Carga el Excel original y el reporte de errores recibido de la EPS para aplicar correcciones automáticas y revisar pendientes.
                    </p>
                </div>

                <a
                    href="{{ route('informes.resolucion-202.corregir') }}"
                    wire:navigate
                    class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700"
                >
                    Abrir corrección
                </a>
            </article>
        </section>
    </div>
</x-layouts.app>
