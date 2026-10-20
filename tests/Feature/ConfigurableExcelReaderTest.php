<?php

use App\Services\Reports\Excel\ConfigurableExcelReader;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('local');

    config()->set('reader_test', [
        'preserve_invalid_rows' => true,
        'sheets' => [
            'tipo2' => [
                'name' => 'Tipo 2',
                'record_type' => 2,
                'header_row' => 1,
                'data_start_row' => 2,
                'detect_data_start' => true,
                'required_sheet' => true,
                'columns' => [
                    ['name' => 'Tipo de registro', 'type' => 'N', 'required' => true, 'max' => 1, 'allowed' => ['2']],
                    ['name' => 'Valor', 'type' => 'A', 'required' => true, 'max' => 30],
                    ['name' => 'Hora', 'type' => 'H', 'required' => false],
                ],
            ],
        ],
    ]);
});

/**
 * @param  array<int, array<int, mixed>>  $rows
 * @param  array<string, string>  $formats
 */
function writeConfigurableReaderWorkbook(array $rows, string $sheetName = 'Tipo 2', array $formats = []): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);
    $sheet->fromArray($rows, null, 'A1', true);

    foreach ($formats as $coordinate => $format) {
        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($format);
    }

    $path = Storage::disk('local')->path('configurable-reader.xlsx');

    try {
        (new Xlsx($spreadsheet))->save($path);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }

    return $path;
}

test('reports inaccessible and corrupt workbooks as safe input errors', function () {
    $reader = app(ConfigurableExcelReader::class);

    expect(fn () => $reader->read(Storage::disk('local')->path('missing.xlsx'), 'reader_test'))
        ->toThrow(InvalidArgumentException::class, 'No se encontró el archivo Excel.');

    Storage::disk('local')->put('corrupt.xlsx', 'This is not an Excel workbook.');

    expect(fn () => $reader->read(Storage::disk('local')->path('corrupt.xlsx'), 'reader_test'))
        ->toThrow(InvalidArgumentException::class, 'No fue posible abrir el archivo Excel. Verifique que sea un archivo válido y vuelva a cargarlo.');
});

test('reports a missing required worksheet as an input error', function () {
    $path = writeConfigurableReaderWorkbook([['Encabezado']], 'Otra hoja');

    expect(fn () => app(ConfigurableExcelReader::class)->read($path, 'reader_test'))
        ->toThrow(InvalidArgumentException::class, "No se encontró la hoja obligatoria 'Tipo 2'.");
});

test('keeps invalid record types before and after valid records', function () {
    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora'],
        ['9', 'primero', '08:30'],
        ['2', 'segundo', '09:30'],
        ['7', 'tercero', '10:30'],
    ]);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect(array_column($records, 'excel_row'))->toBe([2, 3, 4])
        ->and(array_column(array_column($records, 'values'), 0))->toBe([9, 2, 7]);
});

test('keeps malformed rows when no expected record type is found', function () {
    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora'],
        ['9', 'valor inválido', '08:30'],
    ]);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect($records)->toHaveCount(1)
        ->and($records[0]['excel_row'])->toBe(2)
        ->and($records[0]['values'][0])->toBe(9);
});

test('preserves data outside configured columns and ignores empty trailing cells', function () {
    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora'],
        ['2', 'con exceso', '08:30', 'dato adicional'],
        ['2', 'sin exceso', '09:30', null, ''],
    ]);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect($records[0]['values'])->toBe([2, 'con exceso', '08:30', 'dato adicional'])
        ->and($records[1]['values'])->toBe([2, 'sin exceso', '09:30']);
});

test('preserves invalid hours instead of normalizing them into a valid day', function () {
    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora'],
        ['2', 'hora inválida', '25:00'],
        ['2', 'hora válida', '13:45:00'],
    ]);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect($records[0]['values'][2])->toBe('25:00')
        ->and($records[1]['values'][2])->toBe('13:45');
});

test('still skips known headers and column numbering when preserving invalid rows', function () {
    config()->set('reader_test.sheets.tipo2.header_row', 2);
    config()->set('reader_test.sheets.tipo2.data_start_row', 2);

    $path = writeConfigurableReaderWorkbook([
        ['Título del reporte'],
        ['Tipo de registro', 'Valor', 'Hora'],
        [1, 2, 3],
        ['2', 'dato', '08:30'],
    ]);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect($records)->toHaveCount(1)
        ->and($records[0]['excel_row'])->toBe(4);
});

test('keeps legacy record detection for configurations without preservation', function () {
    config()->set('reader_test.preserve_invalid_rows', false);

    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora'],
        ['9', 'ajeno', '08:30'],
        ['2', 'esperado', '09:30'],
    ]);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect($records)->toHaveCount(1)
        ->and($records[0]['excel_row'])->toBe(3);
});

test('resolves configured worksheet aliases', function () {
    config()->set('reader_test.sheets.tipo2.aliases', ['Registro 2']);

    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora'],
        ['2', 'válido', '08:30'],
    ], 'REGISTRO_2');

    $sheet = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2'];

    expect($sheet['sheet_name'])->toBe('REGISTRO_2')
        ->and($sheet['records'])->toHaveCount(1);
});

test('normalizes Excel serial dates and supported text dates without repairing invalid dates', function () {
    $columns = config('reader_test.sheets.tipo2.columns');
    $columns[] = ['name' => 'Fecha', 'type' => 'F', 'required' => true];
    config()->set('reader_test.sheets.tipo2.columns', $columns);

    $path = writeConfigurableReaderWorkbook([
        ['Tipo de registro', 'Valor', 'Hora', 'Fecha'],
        ['2', 'fecha Excel', '08:30', Date::PHPToExcel(new DateTimeImmutable('2026-01-05'))],
        ['2', 'fecha texto', '09:30', '06/01/2026'],
        ['2', 'fecha inválida', '10:30', '31/02/2026'],
    ], formats: ['D2' => 'yyyy-mm-dd']);

    $records = app(ConfigurableExcelReader::class)->read($path, 'reader_test')['tipo2']['records'];

    expect($records[0]['values'][3])->toBe('2026-01-05')
        ->and($records[1]['values'][3])->toBe('2026-01-06')
        ->and($records[2]['values'][3])->toBe('31/02/2026');
});

test('reports missing reader configuration as a server configuration error', function () {
    $path = writeConfigurableReaderWorkbook([['2', 'válido', '08:30']]);

    expect(fn () => app(ConfigurableExcelReader::class)->read($path, 'missing_reader_configuration'))
        ->toThrow(RuntimeException::class);
});
