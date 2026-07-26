<x-layouts.app>
    <div class="mx-auto w-full max-w-7xl">
        <nav class="mb-6 flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
            <a href="{{ route('dashboard') }}" wire:navigate class="transition hover:text-neutral-900 dark:hover:text-white">
                Inicio
            </a>
            <span>/</span>
            <a href="{{ route('informes.gestantes') }}" wire:navigate class="transition hover:text-neutral-900 dark:hover:text-white">
                Gestantes
            </a>
            <span>/</span>
            <span class="font-medium text-neutral-900 dark:text-white">
                Validar Excel
            </span>
        </nav>

        <livewire:informes.gestantes-uploader />
    </div>
</x-layouts.app>