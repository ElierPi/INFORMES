<?php

use App\Services\Reports\Validation\ConfigurableValidator;
use App\Services\Reports\Validation\Resolucion0256ValidationService;

beforeEach(function () {
    config()->set('validator_test', [
        'validation' => [
            'continuous_consecutives' => true,
            'validate_control_total' => false,
        ],
        'sheet_order' => ['tipo2', 'tipo6'],
        'sheets' => [
            'tipo2' => [
                'name' => 'Tipo 2',
                'required_sheet' => true,
                'min_records' => 0,
                'has_consecutive' => true,
                'is_control' => false,
                'columns' => [
                    ['name' => 'Tipo de registro', 'type' => 'N', 'required' => true, 'max' => 1, 'allowed' => ['2']],
                    ['name' => 'Consecutivo', 'type' => 'N', 'required' => true],
                    ['name' => 'Valor', 'type' => 'A', 'required' => true, 'max' => 30],
                ],
            ],
            'tipo6' => [
                'name' => 'Tipo 6',
                'required_sheet' => false,
                'min_records' => 0,
                'has_consecutive' => true,
                'is_control' => false,
                'columns' => [
                    ['name' => 'Tipo de registro', 'type' => 'N', 'required' => true, 'max' => 1, 'allowed' => ['6']],
                    ['name' => 'Consecutivo', 'type' => 'N', 'required' => true],
                    ['name' => 'Valor', 'type' => 'A', 'required' => true, 'max' => 30],
                ],
            ],
        ],
    ]);
});

test('rejects missing validator configuration as a server configuration error', function () {
    expect(fn () => app(ConfigurableValidator::class)->validate([], 'missing_validator_configuration'))
        ->toThrow(RuntimeException::class, 'No se encontró una configuración válida para el validador.');
});

test('allows an absent optional worksheet without inventing a processing error', function () {
    $data = [
        'tipo2' => ['records' => [['excel_row' => 3, 'values' => ['2', '1', 'válido']]]],
    ];

    $result = app(ConfigurableValidator::class)->validate($data, 'validator_test');

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and($result['valid_records']['tipo2'])->toBe($data['tipo2']['records']);
});

test('excludes records with additional business errors from valid records', function () {
    $data = [
        'tipo2' => ['records' => [
            ['excel_row' => 3, 'values' => ['2', '1', 'primero']],
            ['excel_row' => 4, 'values' => ['2', '2', 'segundo']],
        ]],
    ];
    $additionalErrors = [[
        'sheet' => 'Tipo 2',
        'row' => 3,
        'column' => 3,
        'field' => 'Valor',
        'value' => 'primero',
        'message' => 'El registro tiene un error de negocio.',
    ]];

    $result = app(ConfigurableValidator::class)->validate($data, 'validator_test', $additionalErrors);

    expect($result['valid'])->toBeFalse()
        ->and($result['valid_records']['tipo2'])->toBe([$data['tipo2']['records'][1]])
        ->and($result['total_errors'])->toBe(1);
});

test('excludes an entire worksheet when a sheet level business error has no row', function () {
    $data = [
        'tipo2' => ['records' => [['excel_row' => 3, 'values' => ['2', '1', 'válido']]]],
    ];
    $additionalErrors = [[
        'sheet' => 'Tipo 2',
        'row' => null,
        'column' => null,
        'field' => null,
        'value' => null,
        'message' => 'La hoja contiene un error global.',
    ]];

    $result = app(ConfigurableValidator::class)->validate($data, 'validator_test', $additionalErrors);

    expect($result['valid'])->toBeFalse()
        ->and($result['valid_records']['tipo2'])->toBe([]);
});

test('excludes records with consecutive errors after structural validation', function () {
    $data = [
        'tipo2' => ['records' => [
            ['excel_row' => 3, 'values' => ['2', '1', 'primero']],
            ['excel_row' => 4, 'values' => ['2', '3', 'segundo']],
        ]],
    ];

    $result = app(ConfigurableValidator::class)->validate($data, 'validator_test');

    expect($result['valid'])->toBeFalse()
        ->and($result['valid_records']['tipo2'])->toBe([$data['tipo2']['records'][0]])
        ->and(array_column($result['errors'], 'row'))->toContain(4);
});

test('rejects records with extra columns instead of silently validating a truncated record', function () {
    $data = [
        'tipo2' => ['records' => [['excel_row' => 3, 'values' => ['2', '1', 'válido', 'exceso']]]],
    ];

    $result = app(ConfigurableValidator::class)->validate($data, 'validator_test');

    expect($result['valid'])->toBeFalse()
        ->and($result['valid_records']['tipo2'] ?? [])->toBe([])
        ->and(array_column($result['errors'], 'row'))->toContain(3);
});

/**
 * @return array<string, array{records: array<int, array{excel_row: int, values: array<int, string>}>}>
 */
function makeResolucion0256ValidationData(): array
{
    $person = ['CC', '10000001', '1990-01-01', 'M', 'PRUEBA', '', 'SINTETICO', '', 'EPS001'];
    $rows = [
        'tipo1' => [['1', '000000000001', 'NI', '900000001', '2026-01-01', '2026-01-31', '5']],
        'tipo2' => [['2', '1', ...$person, '1', '2026-01-05', '1', '2026-01-06', '2026-01-06']],
        'tipo3' => [['3', '1', 'NI', '900000001', ...array_fill(0, 11, '0')]],
        'tipo4' => [['4', '1', ...$person, '11001', '123456', '2026-01-05', '2026-01-06', '1', '', '2']],
        'tipo5' => [['5', '1', 'NI', '900000001', ...array_fill(0, 9, '0')]],
        'tipo6' => [['6', '1', ...$person, '2026-01-05', '10:00', '2026-01-05', '10:30']],
    ];

    return array_map(
        static fn (array $records): array => [
            'records' => array_map(static fn (array $values): array => ['excel_row' => 3, 'values' => $values], $records),
        ],
        $rows
    );
}

test('validates a synthetic workbook against the existing resolution rules', function () {
    $result = app(Resolucion0256ValidationService::class)->validate(makeResolucion0256ValidationData());

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and($result['total_errors'])->toBe(0);
});

test('preserves the existing resolution business validations and excludes failing records', function (string $sheetKey, int $column, string $value) {
    $data = makeResolucion0256ValidationData();
    $data[$sheetKey]['records'][0]['values'][$column] = $value;

    $result = app(Resolucion0256ValidationService::class)->validate($data);
    $sheetName = config('resolucion0256.sheets.'.$sheetKey.'.name');

    expect($result['valid'])->toBeFalse()
        ->and(array_column($result['errors'], 'sheet'))->toContain($sheetName)
        ->and($result['valid_records'][$sheetKey] ?? [])->toBe([]);
})->with([
    'type 2 assigned consultation requires its date' => ['tipo2', 14, ''],
    'type 2 unassigned consultation cannot retain its date' => ['tipo2', 13, '2'],
    'type 2 assignment cannot precede the request' => ['tipo2', 14, '2026-01-04'],
    'type 4 unperformed surgery requires its cause' => ['tipo4', 15, '2'],
    'type 4 performed surgery cannot retain a nonperformance cause' => ['tipo4', 16, '1'],
    'type 4 performed surgery cannot be rescheduled' => ['tipo4', 17, '1'],
    'type 4 performance cannot precede the request' => ['tipo4', 14, '2026-01-04'],
    'type 5 totals must reconcile' => ['tipo5', 4, '1'],
    'type 6 attention cannot precede classification on the same day' => ['tipo6', 14, '09:59'],
    'type 6 attention cannot precede the classification day' => ['tipo6', 13, '2026-01-04'],
    'control total must match detail records' => ['tipo1', 6, '4'],
    'control period end cannot precede its start' => ['tipo1', 4, '2026-02-01'],
]);

test('accepts the optional type 6 sheet being absent with a matching control total', function () {
    $data = makeResolucion0256ValidationData();
    unset($data['tipo6']);
    $data['tipo1']['records'][0]['values'][6] = '4';

    $result = app(Resolucion0256ValidationService::class)->validate($data);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([]);
});

test('accepts the input Excel sex codes without changing values in any personal record type', function (string $sex) {
    $data = makeResolucion0256ValidationData();

    foreach (['tipo2', 'tipo4', 'tipo6'] as $key) {
        $data[$key]['records'][0]['values'][5] = $sex;
    }

    $originalData = $data;
    $result = app(Resolucion0256ValidationService::class)->validate($data);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBe([])
        ->and($data)->toBe($originalData);

    foreach (['tipo2', 'tipo4', 'tipo6'] as $key) {
        expect($result['valid_records'][$key][0]['values'][5])->toBe($sex);
    }
})->with(['F', 'M']);

test('rejects unsupported sex codes in each input Excel record type', function (string $sheetKey, string $sex) {
    $data = makeResolucion0256ValidationData();
    $data[$sheetKey]['records'][0]['values'][5] = $sex;

    $result = app(Resolucion0256ValidationService::class)->validate($data);
    $errors = array_values(array_filter($result['errors'], fn (array $error): bool => $error['sheet'] === config('resolucion0256.sheets.'.$sheetKey.'.name')
        && $error['column'] === 6
        && $error['field'] === 'Sexo del usuario'
    ));

    expect($result['valid'])->toBeFalse()
        ->and($result['valid_records'][$sheetKey] ?? [])->toBe([])
        ->and($errors)->not->toBeEmpty()
        ->and($errors[0]['value'])->toBe($sex);
})->with(['tipo2', 'tipo4', 'tipo6'])->with(['X', '1', 'FEMENINO', '', 'f', 'm', 'H', 'I']);
