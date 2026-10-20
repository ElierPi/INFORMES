<?php

use App\Livewire\Informes\Resolucion0256Uploader;
use App\Models\User;
use App\Services\Reports\Excel\ConfigurableExcelReader;
use App\Services\Resolucion0256\Resolucion0256ValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

function create0256Upload(array $rows = [], array $missingSheets = [], string $format = 'xlsx'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach (config('resolucion0256.sheets') as $key => $definition) {
        if (in_array($key, $missingSheets, true)) {
            continue;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($definition['name']);
        $sheet->fromArray(array_column($definition['columns'], 'name'), null, 'A'.$definition['header_row']);
        $records = $rows[$key] ?? ($key === 'tipo1'
            ? [['1', '000000000001', 'NI', '900000001', '2026-01-01', '2026-01-31', '0']]
            : []);

        foreach ($records as $index => $record) {
            $sheet->fromArray($record, null, 'A'.($definition['data_start_row'] + $index), true);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'test0256');
    $writer = $format === 'xls' ? new Xls($spreadsheet) : new Xlsx($spreadsheet);
    $writer->save($path);
    $spreadsheet->disconnectWorksheets();

    try {
        return UploadedFile::fake()->createWithContent('sintetico.'.$format, file_get_contents($path));
    } finally {
        unlink($path);
    }
}

test('0256 page and component render with their own Excel form', function () {
    $this->get(route('informes.resolucion-0256'))
        ->assertSuccessful()
        ->assertSeeLivewire(Resolucion0256Uploader::class);

    Livewire::test(Resolucion0256Uploader::class)
        ->assertSee('Resolución 0256')
        ->assertSee('F = Femenino; M = Masculino')
        ->assertSeeHtml('wire:submit="analizar"')
        ->assertSeeHtml('wire:model="archivo"')
        ->assertDontSee('TXT enviado a Familiar');
});

test('0256 requires an Excel file', function () {
    Livewire::test(Resolucion0256Uploader::class)
        ->call('analizar')
        ->assertHasErrors(['archivo' => 'required'])
        ->assertSet('analizado', false);
});

test('0256 rejects non Excel content', function () {
    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', UploadedFile::fake()->createWithContent('incorrecto.txt', 'contenido de texto'))
        ->call('analizar')
        ->assertHasErrors(['archivo' => 'mimes'])
        ->assertSet('analizado', false);
});

test('0256 rejects a damaged workbook without crashing', function () {
    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', UploadedFile::fake()->createWithContent('danado.xlsx', 'contenido de texto'))
        ->call('analizar')
        ->assertHasErrors('archivo')
        ->assertSet('analizado', false);
});

test('0256 rejects files larger than 30 MB', function () {
    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', UploadedFile::fake()->create('grande.xlsx', 30721, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
        ->call('analizar')
        ->assertHasErrors(['archivo' => 'max'])
        ->assertSet('analizado', false);
});

test('0256 analyzes valid Excel and accepts the optional type 6 sheet being absent', function (string $format) {
    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload(missingSheets: ['tipo6'], format: $format))
        ->call('analizar')
        ->assertHasNoErrors()
        ->assertSet('analizado', true)
        ->assertSet('errores', [])
        ->assertSet('resumen.tipo1.records', 1)
        ->assertSee('Análisis completado sin errores');
})->with(['xlsx', 'xls']);

test('0256 reports a missing required sheet without crashing', function () {
    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload(missingSheets: ['tipo2']))
        ->call('analizar')
        ->assertHasErrors('archivo')
        ->assertSee('Tipo 2')
        ->assertSet('analizado', false);
});

test('0256 processes records from all six configured types', function (string $sex) {
    $person = ['CC', '10000001', '1990-01-01', $sex, 'PRUEBA', '', 'SINTETICO', '', 'EPS001'];
    $rows = [
        'tipo1' => [['1', '000000000001', 'NI', '900000001', '2026-01-01', '2026-01-31', '5']],
        'tipo2' => [['2', '1', ...$person, '1', '2026-01-05', '1', '2026-01-06', '2026-01-06']],
        'tipo3' => [['3', '1', 'NI', '900000001', ...array_fill(0, 11, '0')]],
        'tipo4' => [['4', '1', ...$person, '11001', '123456', '2026-01-05', '2026-01-06', '1', '', '2']],
        'tipo5' => [['5', '1', 'NI', '900000001', ...array_fill(0, 9, '0')]],
        'tipo6' => [['6', '1', ...$person, '2026-01-05', '10:00', '2026-01-05', '10:30']],
    ];

    $component = Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload($rows))
        ->call('analizar')
        ->assertHasNoErrors()
        ->assertSet('errores', [])
        ->assertSet('analizado', true);

    foreach (array_keys($rows) as $key) {
        $component->assertSet('resumen.'.$key.'.records', 1);
    }
})->with(['F', 'M']);

test('0256 renders incomplete data and existing period errors with their location', function () {
    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload(['tipo1' => [['1', '', 'NI', '900000001', '2026-02-01', '2026-01-31', '0']]]))
        ->call('analizar')
        ->assertHasNoErrors()
        ->assertSet('analizado', true)
        ->assertSee('El campo es obligatorio.')
        ->assertSee('Código de habilitación de la IPS')
        ->assertSee('La fecha final no puede ser anterior a la fecha inicial.')
        ->assertSee('Tipo 1');
});

test('0256 clears results when selecting another file and can analyze again after clearing', function () {
    $component = Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload())
        ->call('analizar')
        ->assertSet('analizado', true)
        ->set('archivo', UploadedFile::fake()->createWithContent('invalido.txt', 'texto'))
        ->assertSet('analizado', false)
        ->assertSet('resumen', [])
        ->call('analizar')
        ->assertHasErrors('archivo')
        ->call('limpiar')
        ->assertHasNoErrors()
        ->assertSet('archivo', null)
        ->assertSet('errores', [])
        ->assertSet('resumen', [])
        ->assertSet('analizado', false);

    $component->set('archivo', create0256Upload())
        ->call('analizar')
        ->assertHasNoErrors()
        ->assertSet('analizado', true);
});

test('0256 handles an expired temporary upload with a useful error', function () {
    $component = Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload());

    $component->get('archivo')->delete();

    $this->assertFileDoesNotExist($component->get('archivo')->getRealPath());

    $instance = $component->instance();
    $instance->analizar(app(ConfigurableExcelReader::class), app(Resolucion0256ValidationService::class));

    expect($instance->getErrorBag()->first('archivo'))
        ->toContain('Selecciona el archivo nuevamente');
    expect($instance->analizado)->toBeFalse();
});

test('0256 hides internal exception details', function () {
    $this->mock(ConfigurableExcelReader::class)
        ->shouldReceive('read')
        ->once()
        ->andThrow(new RuntimeException('ruta-interna-secreta'));

    Livewire::test(Resolucion0256Uploader::class)
        ->set('archivo', create0256Upload())
        ->call('analizar')
        ->assertHasErrors('archivo')
        ->assertDontSee('ruta-interna-secreta')
        ->assertSet('analizado', false);
});

test('0256 prevents guests from invoking analysis', function () {
    auth()->logout();

    Livewire::test(Resolucion0256Uploader::class)
        ->call('analizar')
        ->assertForbidden();
});
