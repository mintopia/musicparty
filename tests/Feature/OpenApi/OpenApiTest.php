<?php

use App\Services\OpenApi\OpenApiCoverage;
use App\Services\OpenApi\OpenApiGenerator;
use Illuminate\Support\Facades\Route;

function committedOpenApi(): array
{
    return json_decode((string) file_get_contents(base_path('openapi/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('has a committed spec identical to the generated output', function () {
    $generator = app(OpenApiGenerator::class);

    expect((string) file_get_contents(base_path('openapi/openapi.json')))
        ->toBe($generator->toJson($generator->generate()));
});

it('passes the openapi:generate --check command', function () {
    $this->artisan('openapi:generate', ['--check' => true])->assertSuccessful();
});

it('documents every api route', function () {
    $missing = OpenApiCoverage::missing(committedOpenApi(), Route::getRoutes()->getRoutes());

    expect($missing)->toBe([], 'Undocumented API routes: '.implode(', ', $missing));
});

it('fails coverage naming an undocumented fixture route', function () {
    Route::get('api/v1/__fixture', fn () => response()->json([]))->name('fixture.undocumented');

    $routes = Route::getRoutes();
    $routes->refreshNameLookups();

    $missing = OpenApiCoverage::missing(committedOpenApi(), $routes->getRoutes());

    expect($missing)->toBe(['GET /api/v1/__fixture (fixture.undocumented)']);
});

it('reports drift when the generated output differs from a stale spec', function () {
    $generator = app(OpenApiGenerator::class);
    $stale = committedOpenApi();
    unset($stale['paths']['/api/v1/ping']);

    expect($generator->toJson($stale))->not->toBe($generator->toJson($generator->generate()));
});

it('documents sanctum security on authenticated routes only', function () {
    $paths = committedOpenApi()['paths'];

    expect($paths['/api/v1/parties/{party}/control']['post']['security'])->toBe([['sanctumBearer' => []], ['sanctumCookie' => []]])
        ->and($paths['/api/v1/ping']['get']['security'])->toBe([])
        ->and($paths['/api/v1/integration/ping']['get']['security'])->toBe([['integrationBearer' => []]]);
});

it('derives a request body from the form request', function () {
    $body = committedOpenApi()['paths']['/api/v1/parties/{party}/control']['post']['requestBody']['content']['application/json']['schema'];

    expect($body['required'])->toBe(['action'])
        ->and($body['properties']['action']['enum'])->toBe(['play', 'pause', 'next', 'previous']);
});

it('documents the create party request body', function () {
    $body = committedOpenApi()['paths']['/api/v1/parties']['post']['requestBody']['content']['application/json']['schema'];

    expect($body['required'])->toEqualCanonicalizing(['name', 'music_provider', 'player_kind'])
        ->and($body['properties']['music_provider']['enum'])->toBe(['fake'])
        ->and($body['properties']['player_kind']['enum'])->toBe(['fake', 'polling', 'browser', 'soloist']);
});
