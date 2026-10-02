<div class="mx-auto w-full max-w-7xl space-y-6">
<section class="rounded-3xl border border-violet-200 bg-white p-6 shadow-sm">
<p class="text-sm font-semibold text-violet-600">RIPS · Familiar Colombia</p>
<h1 class="mt-1 text-2xl font-bold">Tres archivos por mes</h1>
<p class="mt-2 text-sm text-neutral-600">Morbilidad y PYM se generan desde AC; Procedimientos se genera desde Hoja2.</p>
</section>
@if ($errorMessage)<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errorMessage }}</div>@endif
<section class="grid gap-5 lg:grid-cols-3">
<div class="lg:col-span-2 rounded-2xl border p-5"><label class="font-semibold">Excel fuente</label><input type="file" wire:model="archivoFuente" accept=".xlsx,.xls" class="mt-3 block w-full"></div>
<div class="rounded-2xl border p-5"><label class="font-semibold">Mes</label><input type="month" wire:model="periodo" min="2025-06" max="2025-12" class="mt-3 w-full rounded-xl border px-3 py-2"></div>
</section>
<section class="grid gap-4 md:grid-cols-3">
<div class="rounded-2xl border border-rose-200 bg-rose-50 p-5"><b>MORBILIDAD</b><p class="mt-2 text-sm">AC cuyo CUPS termina exactamente en <code>MOR</code>. Llena US + AC.</p></div>
<div class="rounded-2xl border border-sky-200 bg-sky-50 p-5"><b>PYM</b><p class="mt-2 text-sm">AC cuyo CUPS termina en cualquier palabra distinta de <code>MOR</code>. Llena US + AC.</p></div>
<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><b>PROCEDIMIENTOS</b><p class="mt-2 text-sm">Todos los registros mensuales de Hoja2. Llena solo AP.</p></div>
</section>
<div class="flex flex-wrap gap-3"><button wire:click="generarMes" class="rounded-xl bg-violet-600 px-5 py-2.5 font-semibold text-white">Generar 3 archivos del mes</button><button wire:click="generarJunioDiciembre" class="rounded-xl bg-neutral-900 px-5 py-2.5 font-semibold text-white">Generar junio → diciembre</button></div>
@if ($resultado)
<section class="rounded-3xl border border-violet-200 bg-white p-6"><h2 class="text-xl font-bold">{{ $resultado['period'] }}</h2><div class="mt-4 grid gap-4 md:grid-cols-3">@foreach (['MORBILIDAD','PYM','PROCEDIMIENTOS'] as $tipo)<div class="rounded-xl border p-4"><b>{{ $tipo }}</b><p class="mt-2 text-3xl font-bold">{{ $resultado['counts'][$tipo] ?? 0 }}</p><p class="mt-1 text-xs">{{ $resultado['files'][$tipo]['name'] ?? '' }}</p><button wire:click="descargarArchivoMes('{{ $tipo }}')" class="mt-3 rounded-lg border px-3 py-2 text-xs font-semibold">Descargar {{ $tipo }}</button></div>@endforeach</div><button wire:click="descargarMes" class="mt-5 rounded-xl bg-violet-700 px-5 py-2.5 font-semibold text-white">Descargar ZIP con los 3</button></section>
@endif
@if ($resultadoLote)<section class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6"><h2 class="text-xl font-bold">{{ $resultadoLote['file_count'] }} archivos generados</h2><p>{{ $resultadoLote['month_count'] }} meses × 3 archivos.</p><button wire:click="descargarLote" class="mt-4 rounded-xl bg-emerald-700 px-5 py-2.5 font-semibold text-white">Descargar ZIP junio → diciembre</button></section>@endif
</div>