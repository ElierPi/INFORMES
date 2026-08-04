<?php

use App\Services\Resolucion1552\Resolucion1552Exporter;
use Illuminate\Support\Facades\Route;

Route::get('/test-resolucion-1552-export', function (
    Resolucion1552Exporter $exporter
) {
    $path = storage_path(
        'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
    );

    $result = $exporter->exportFromExcel(
        path: $path,
        originalFilename: 'SIE-QAC-CIDSMA.xlsx',
    );

    if (! $result['success']) {
        return response()->json([
            'success' => false,
            'stage' => $result['stage'],
            'validation' => [
                'statistics' => $result['validation']['statistics'],
                'errors' => array_map(
                    static fn ($issue): array => $issue->toArray(),
                    $result['validation']['errors']
                ),
                'warnings' => array_map(
                    static fn ($issue): array => $issue->toArray(),
                    $result['validation']['warnings']
                ),
            ],
        ], 422);
    }

    return response()->json([
        'success' => true,
        'records_count' => $result['records_count'],
        'provider_code' => $result['provider_code'],
        'encoding' => $result['encoding'],
        'txt_name' => $result['txt_name'],
        'txt_path' => $result['txt_path'],
        'zip_name' => $result['zip_name'],
        'zip_path' => $result['zip_path'],
        'download_url' => url('/test-resolucion-1552-download'),
    ]);
});

Route::get('/test-resolucion-1552-download', function (
    Resolucion1552Exporter $exporter
) {
    $path = storage_path(
        'app/private/pruebas/SIE-QAC-CIDSMA.xlsx'
    );

    $result = $exporter->exportFromExcel(
        path: $path,
        originalFilename: 'SIE-QAC-CIDSMA.xlsx',
    );

    abort_unless($result['success'], 422, 'El archivo contiene errores de validación.');

    return response()->download(
        $result['zip_path'],
        $result['zip_name']
    )->deleteFileAfterSend(false);
});
