<?php

use App\Services\Informe202\DusakawiCodeRule;
use App\Services\Informe202\Informe202PreparationService;
use App\Services\Resolucion0256\Resolucion0256ValidationService;

test('generated regulatory reports are not stored in the public web root', function () {
    expect(glob(public_path('RESOLUCION_*.zip')))->toBeEmpty();
    expect(glob(public_path('RESOLUCION_*.txt')))->toBeEmpty();
});

test('local report storage is private', function () {
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'));
});

test('report service classes can be autoloaded from their declared namespaces', function () {
    foreach ([
        Informe202PreparationService::class,
        App\Livewire\Informes\Informe202PreparationService::class,
        DusakawiCodeRule::class,
        App\Services\Informe202\Rules\DusakawiCodeRule::class,
        Resolucion0256ValidationService::class,
        App\Services\Reports\Validation\Resolucion0256ValidationService::class,
    ] as $class) {
        expect(class_exists($class))->toBeTrue();
    }
});
