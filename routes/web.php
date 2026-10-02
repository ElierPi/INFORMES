<?php

use App\Livewire\Informes\GestantesCorreccionUploader;
use App\Services\Gestantes\GestantesAutoCorrector;
use App\Services\Importing\ExcelAnalyzer;
use App\Services\Importing\ImportPipeline;
use App\Services\Reports\Excel\ConfigurableExcelDebugger;
use App\Services\Reports\Excel\ConfigurableExcelReader;
use App\Services\Reports\Excel\UniversalExcelImporter;
use App\Services\Resolucion0256\Resolucion0256ValidationService;
use App\Services\Resolucion1552\Resolucion1552DatasetTransformer;
use App\Services\Resolucion1552\Resolucion1552FileConsistencyService;
use App\Services\Resolucion1552\Resolucion1552Validator;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])
    ->prefix('informes')
    ->name('informes.')
    ->group(function () {
        Route::view('/resolucion-202', 'informes.resolucion-202')
            ->name('resolucion-202');

        Route::view(
            '/resolucion-202/corregir-dusakawi',
            'informes.resolucion-202-corregir-dusakawi'
        )->name('resolucion-202.corregir-dusakawi');

        Route::view('/resolucion-202/preparar', 'informes.resolucion-202-preparar')
            ->name('resolucion-202.preparar');

        Route::view('/resolucion-202/corregir', 'informes.resolucion-202-corregir')
            ->name('resolucion-202.corregir');

Route::view(
    '/resolucion-202/corregir-familiar',
    'informes.resolucion-202-corregir-familiar'
)->name('resolucion-202.corregir-familiar');


        Route::view('/rips', 'informes.rips')
            ->name('rips');

        Route::view('/gestantes', 'informes.gestantes')
            ->name('gestantes');

        Route::view('/gestantes/validar-excel', 'informes.gestantes-validar')
            ->name('gestantes.validar-excel');

        Route::get(
            '/gestantes/correccion-sigires',
            GestantesCorreccionUploader::class
        )->name('gestantes.correccion-sigires');

        Route::view('/resolucion-0256', 'informes.resolucion-0256')
            ->name('resolucion-0256');

        Route::view('/resolucion-1552', 'informes.resolucion-1552')
            ->name('resolucion-1552');

        Route::view('/resolucion-1552/dusakawi/corregir', 'informes.resolucion-1552-dusakawi-corregir')
            ->name('resolucion-1552.dusakawi.corregir');

        Route::view('/resolucion-1552/familiar-colombia', 'informes.resolucion-1552-familiar')
            ->name('resolucion-1552.familiar-colombia');

        Route::view('/resolucion-1552/familiar-colombia/corregir', 'informes.resolucion-1552-familiar-corregir')
            ->name('resolucion-1552.familiar-colombia.corregir');

        Route::view('/resolucion-1552/proteger', 'informes.resolucion-1552-proteger')
            ->name('resolucion-1552.proteger');

        Route::view('/resolucion-1552/sanitas', 'informes.resolucion-1552-sanitas')
            ->name('resolucion-1552.sanitas');

        Route::view('/sigires-cronicos', 'informes.sigires-cronicos')
            ->name('sigires-cronicos');

        Route::view('/cronicos-dusakawi', 'informes.cronicos-dusakawi')
            ->name('cronicos-dusakawi');

        Route::view('/resolucion-1604/familiar-colombia', 'informes.resolucion-1604-familiar')
            ->name('resolucion-1604.familiar-colombia');

        Route::view('/resolucion-1604/familiar-colombia/corregir', 'informes.resolucion-1604-familiar-corregir')
            ->name('resolucion-1604.familiar-colombia.corregir');
    });

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')
        ->name('settings.profile');

    Volt::route('settings/password', 'settings.password')
        ->name('settings.password');

    Volt::route('settings/appearance', 'settings.appearance')
        ->name('settings.appearance');
});

Route::get('/test-gestantes-corrector', function (
    GestantesAutoCorrector $corrector
) {
    $archivo = storage_path('app/private/pruebas/gestantes.xlsx');

    return $corrector->correct($archivo);
})->middleware(['auth', 'verified']);

Route::get('/test-0256', function (
    ConfigurableExcelReader $reader,
    Resolucion0256ValidationService $validator
) {
    $path = storage_path('app/private/pruebas/0256.xlsx');
    $data = $reader->read($path, 'resolucion0256');

    return response()->json(
        $validator->validate($data)
    );
});

Route::get('/debug-0256/workbook', function (
    ConfigurableExcelDebugger $debugger
) {
    $path = storage_path('app/private/pruebas/0256.xlsx');

    return response()->json(
        $debugger->inspectWorkbook($path),
        200,
        [],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
});

Route::get('/debug-0256/{sheet}/{row}', function (
    string $sheet,
    int $row,
    ConfigurableExcelDebugger $debugger
) {
    $path = storage_path('app/private/pruebas/0256.xlsx');

    return response()->json(
        $debugger->inspectRow(
            path: $path,
            sheetName: $sheet,
            row: $row,
            before: 1,
            after: 1
        ),
        200,
        [],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
});

Route::get('/test-resolucion-1552', function (
    Resolucion1552FileConsistencyService $validator
) {
    $path = storage_path(
        'app/private/pruebas/resolucion1552.zip'
    );

    return response()->json(
        $validator->validate(
            zipPath: $path,
            originalName:
                'RESOLUCION_1552_123456789012_30062026.zip'
        )
    );
});

Route::get('/test-importador-excel', function (
    UniversalExcelImporter $importer
) {
    $path = storage_path(
        'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
    );

    return response()->json(
        $importer->inspect($path)
    );
});

Route::get('/test-motor-homologacion', function (
    ExcelAnalyzer $analyzer
) {
    $path = storage_path(
        'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
    );

    return response()->json(
        $analyzer->analyze(
            path: $path,
            fieldDefinitions: config(
                'report_importer.aliases',
                []
            )
        )
    );
});

Route::get('/test-import-dataset', function (
    ImportPipeline $pipeline
) {
    $path = storage_path(
        'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
    );

    $result = $pipeline->process(
        path: $path,
        originalFilename: 'SIE-QAC-CIDSMA.xlsx',
        reportType: 'resolucion_1552',
        fieldDefinitions: config(
            'report_importer.aliases',
            []
        )
    );

    $dataset = $result['dataset'];
    $history = $result['history'];

    return response()->json([
        'history' => [
            'id' => $history->id,
            'status' => $history->status,
            'records_count' => $history->records_count,
            'missing_fields' => $history->missing_fields,
        ],
        'dataset' => $dataset->toArray(),
    ]);
});

Route::get(
    '/test-resolucion-1552-transform',
    function (
        ImportPipeline $pipeline,
        Resolucion1552DatasetTransformer $transformer
    ) {
        $path = storage_path(
            'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
        );

        if (! is_file($path)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el archivo de prueba.',
                'path' => $path,
            ], 404);
        }

        $result = $pipeline->process(
            path: $path,
            originalFilename: 'SIE-QAC-CIDSMA.xlsx',
            reportType: 'resolucion_1552',
            fieldDefinitions: config(
                'report_importer.aliases',
                []
            )
        );

        $dataset = $result['dataset'];
        $history = $result['history'];

        return response()->json([
            'success' => true,
            'history' => [
                'id' => $history->id,
                'status' => $history->status,
            ],
            'profile' => $dataset->profile === null
                ? null
                : [
                    'id' => $dataset->profile->id,
                    'name' => $dataset->profile->name,
                    'slug' => $dataset->profile->slug,
                ],
            'transformation' => $transformer->toArray($dataset),
        ]);
    }
);

Route::get(
    '/test-resolucion-1552-validation',
    function (
        ImportPipeline $pipeline,
        Resolucion1552DatasetTransformer $transformer,
        Resolucion1552Validator $validator
    ) {
        $path = storage_path(
            'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
        );

        if (! is_file($path)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el archivo de prueba.',
                'path' => $path,
            ], 404);
        }

        $pipelineResult = $pipeline->process(
            path: $path,
            originalFilename: 'SIE-QAC-CIDSMA.xlsx',
            reportType: 'resolucion_1552',
            fieldDefinitions: config(
                'report_importer.aliases',
                []
            )
        );

        $transformation = $transformer->transform(
            $pipelineResult['dataset']
        );

        return response()->json([
            'success' => true,
            'history' => [
                'id' => $pipelineResult['history']->id,
                'status' => $pipelineResult['history']->status,
            ],
            'transformation' => [
                'statistics' => $transformation['statistics'],
            ],
            'validation' => $validator->toArray(
                $transformation['records']
            ),
        ]);
    }
);
Route::view('/informes/resolucion-1604/dusakawi', 'informes.resolucion-1604-dusakawi')
    ->middleware(['auth', 'verified'])
    ->name('informes.resolucion-1604.dusakawi');

Route::view('/informes/resolucion-1604/dusakawi/corregir', 'informes.resolucion-1604-dusakawi-corregir')
    ->middleware(['auth', 'verified'])
    ->name('informes.resolucion-1604.dusakawi.corregir');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/informes/gestantes/mensual', 'informes.gestante-mensual')
        ->name('informes.gestante-mensual');
});

require __DIR__ . '/test-resolucion-1552-export.php';
require __DIR__ . '/auth.php';