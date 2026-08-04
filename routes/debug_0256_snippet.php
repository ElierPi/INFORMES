<?php

use App\Services\Reports\Excel\ConfigurableExcelDebugger;
use Illuminate\Support\Facades\Route;

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
})->whereNumber('row');
