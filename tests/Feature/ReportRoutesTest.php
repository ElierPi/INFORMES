<?php

use App\Http\Middleware\EnsureDevelopmentEnvironment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

dataset('development report endpoints', [
    '/test-gestantes-corrector',
    '/test-0256',
    '/debug-0256/workbook',
    '/debug-0256/Tipo1/2',
    '/test-resolucion-1552',
    '/test-importador-excel',
    '/test-motor-homologacion',
    '/test-import-dataset',
    '/test-resolucion-1552-transform',
    '/test-resolucion-1552-validation',
    '/test-resolucion-1552-export',
    '/test-resolucion-1552-download',
]);

test('development report endpoints require authentication', function (string $path) {
    $this->get($path)->assertRedirectToRoute('login');
})->with('development report endpoints');

test('development report endpoints are unavailable in production', function (string $path) {
    $this->actingAs(User::factory()->create());
    $this->app['env'] = 'production';

    $this->get($path)->assertNotFound();
})->with('development report endpoints');

test('development middleware permits local and testing environments', function (string $environment) {
    $this->app['env'] = $environment;

    $response = (new EnsureDevelopmentEnvironment)->handle(
        Request::create('/development-tool'),
        fn (Request $request) => response('available'),
    );

    expect($response->getContent())->toBe('available');
})->with(['local', 'testing']);

test('report pages require authentication', function () {
    $reportRoutes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), 'informes/'));

    expect($reportRoutes)->not->toBeEmpty();

    foreach ($reportRoutes as $route) {
        $this->get('/'.$route->uri())->assertRedirectToRoute('login');
    }
});

test('all report pages render for verified users', function () {
    $this->actingAs(User::factory()->create());

    $reportRoutes = collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), 'informes/'));

    expect($reportRoutes)->not->toBeEmpty();

    foreach ($reportRoutes as $route) {
        expect($this->get('/'.$route->uri())->status(), $route->uri())->toBe(200);
    }
});
