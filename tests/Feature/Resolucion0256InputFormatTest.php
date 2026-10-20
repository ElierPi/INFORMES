<?php

use App\Services\Reports\Excel\ConfigurableExcelReader;
use App\Services\Resolucion0256\Resolucion0256ValidationService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('local');
});

function write0256ControlWorkbook(mixed $total, bool $withDetail = false, string $format = 'xlsx', ?string $numberFormat = null, ?string $mergedRange = null): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach (config('resolucion0256.sheets') as $key => $definition) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($definition['name']);
        $sheet->fromArray(array_column($definition['columns'], 'name'), null, 'A'.$definition['header_row'], true);

        if ($key === 'tipo1') {
            $sheet->fromArray(['1', '000000000001', 'NI', '900000001', '2026-01-01', '2026-01-31', $total], null, 'A2', true);

            if ($numberFormat !== null) {
                $sheet->getStyle('G2')->getNumberFormat()->setFormatCode($numberFormat);
            }

            if ($mergedRange !== null) {
                $sheet->mergeCells($mergedRange);
            }
        }

        if ($key === 'tipo2' && $withDetail) {
            $sheet->fromArray(['2', '1', 'CC', '10000001', '1990-01-01', 'F', 'PRUEBA', '', 'SINTETICO', '', 'EPS001', '1', '2026-01-05', '1', '2026-01-06', '2026-01-06'], null, 'A3', true);
        }
    }

    $path = Storage::disk('local')->path('control-0256.'.$format);

    try {
        $writer = $format === 'xls' ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }

    return $path;
}

test('0256 reads numeric text and formula totals in G2 without changing the workbook', function (mixed $total, int $expected, bool $withDetail, string $format) {
    $path = write0256ControlWorkbook($total, $withDetail, $format);
    $hash = hash_file('sha256', $path);
    $data = app(ConfigurableExcelReader::class)->read($path, 'resolucion0256');
    $result = app(Resolucion0256ValidationService::class)->validate($data);

    expect($data['tipo1']['records'][0]['excel_row'])->toBe(2)
        ->and((string) $data['tipo1']['records'][0]['values'][6])->toBe((string) $expected)
        ->and($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and(hash_file('sha256', $path))->toBe($hash);
})->with([
    'numeric zero' => [0, 0, false],
    'text zero' => ['0', 0, false],
    'numeric detail total' => [1, 1, true],
    'text detail total' => ['1', 1, true],
    'zero formula' => ['=SUM(0,0)', 0, false],
    'detail count formula' => ["=COUNTA('Tipo 2'!A3:A3)", 1, true],
])->with(['xlsx', 'xls']);

test('0256 preserves an explicit zero even when Excel formatting hides it', function () {
    $path = write0256ControlWorkbook(0, numberFormat: '0;-0;;@');
    $data = app(ConfigurableExcelReader::class)->read($path, 'resolucion0256');

    expect($data['tipo1']['records'][0]['values'][6])->toBe(0)
        ->and(app(Resolucion0256ValidationService::class)->validate($data)['valid'])->toBeTrue();
});

test('0256 keeps an absent G2 total empty and reports the exact required coordinate', function (mixed $total, bool $withDetail) {
    $path = write0256ControlWorkbook($total, $withDetail);
    $hash = hash_file('sha256', $path);
    $data = app(ConfigurableExcelReader::class)->read($path, 'resolucion0256');
    $result = app(Resolucion0256ValidationService::class)->validate($data);
    $requiredErrors = array_values(array_filter($result['errors'], fn (array $error): bool => ($error['coordinate'] ?? null) === 'G2' && ($error['rule'] ?? null) === 'required'
    ));

    expect($data['tipo1']['records'][0]['values'][6])->toBeNull()
        ->and($result['valid'])->toBeFalse()
        ->and($requiredErrors)->toHaveCount(1)
        ->and($requiredErrors[0]['field'])->toBe('Número total de registros de detalle')
        ->and(hash_file('sha256', $path))->toBe($hash);
})->with([
    'missing cell' => [null],
    'empty text' => [''],
    'empty formula result' => ['=""'],
])->with([false, true]);

test('0256 does not replace a mismatching G2 total with the counted records', function () {
    $path = write0256ControlWorkbook(9, withDetail: true);
    $data = app(ConfigurableExcelReader::class)->read($path, 'resolucion0256');
    $result = app(Resolucion0256ValidationService::class)->validate($data);

    expect($data['tipo1']['records'][0]['values'][6])->toBe(9)
        ->and($result['valid'])->toBeFalse()
        ->and(array_column($result['errors'], 'message'))->toContain('El total reportado es 9, pero existen 1 registros de detalle.');
});

test('0256 reads G2 as the anchor of merged cells without inventing missing values elsewhere', function (string $mergedRange, bool $valid) {
    $path = write0256ControlWorkbook(0, mergedRange: $mergedRange);
    $data = app(ConfigurableExcelReader::class)->read($path, 'resolucion0256');
    $result = app(Resolucion0256ValidationService::class)->validate($data);

    expect($data['tipo1']['records'][0]['values'][6])->toBe($valid ? 0 : null)
        ->and($result['valid'])->toBe($valid);
})->with([
    'G2 is the anchor' => ['G2:H2', true],
    'G2 is empty inside a range anchored at F2' => ['F2:G2', false],
]);
