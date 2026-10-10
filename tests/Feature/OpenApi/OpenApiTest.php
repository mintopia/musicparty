<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function freshExport(): array
{
    config(['musicparty' => require config_path('musicparty.php')]);

    $path = tempnam(sys_get_temp_dir(), 'openapi');

    try {
        Artisan::call('scramble:export', ['--path' => $path]);

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        File::delete($path);
    }
}

/**
 * @return array<string, mixed>
 */
function committedOpenApi(): array
{
    return json_decode((string) file_get_contents(base_path('openapi/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $spec
 * @return list<string>
 */
function documentedOperations(array $spec): array
{
    $prefix = rtrim((string) parse_url((string) $spec['servers'][0]['url'], PHP_URL_PATH), '/');
    $operations = [];

    foreach ($spec['paths'] as $path => $methods) {
        foreach (array_keys($methods) as $method) {
            $operations[] = strtoupper((string) $method).' '.$prefix.$path;
        }
    }

    return $operations;
}

/**
 * @return list<RoutingRoute>
 */
function apiRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/'),
    ));
}

/**
 * @return list<string>
 */
function documentedMethods(RoutingRoute $route): array
{
    return array_values(array_slice($route->methods(), 0, 1));
}

function normalisedUri(RoutingRoute $route): string
{
    return '/'.preg_replace('/\{(\w+)[?:][^}]*\}/', '{$1}', $route->uri());
}

/**
 * @param  list<RoutingRoute>  $routes
 * @param  array<string, mixed>  $spec
 * @return list<string>
 */
function undocumentedRoutes(array $routes, array $spec): array
{
    $documented = documentedOperations($spec);
    $missing = [];

    foreach ($routes as $route) {
        foreach (documentedMethods($route) as $method) {
            $operation = $method.' '.normalisedUri($route);

            if (! in_array($operation, $documented, true)) {
                $missing[] = $operation.' ('.($route->getName() ?? 'unnamed').')';
            }
        }
    }

    return $missing;
}

/**
 * @return list<string>
 */
function resourceClassNames(): array
{
    $names = [];

    foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (class_exists($class) && is_subclass_of($class, JsonResource::class)) {
            $names[] = class_basename($class);
        }
    }

    return $names;
}

function requiresAuthentication(RoutingRoute $route): bool
{
    return collect($route->gatherMiddleware())
        ->filter(fn ($middleware): bool => is_string($middleware))
        ->contains(fn (string $middleware): bool => $middleware === 'export.access' || $middleware === 'auth' || str_starts_with($middleware, 'auth:'));
}

/**
 * @param  array<string, mixed>  $spec
 * @param  array<string, mixed>  $schema
 * @return array<string, mixed>
 */
function resolveSchema(array $spec, array $schema): array
{
    return isset($schema['$ref'])
        ? $spec['components']['schemas'][basename($schema['$ref'])]
        : $schema;
}

it('documents every api route in a fresh export', function () {
    expect(apiRoutes())->not->toBeEmpty();

    $missing = undocumentedRoutes(apiRoutes(), freshExport());

    expect($missing)->toBe([], 'Undocumented API routes: '.implode(', ', $missing));
});

it('fails coverage naming a fixture route added after the export was generated', function () {
    $spec = freshExport();

    Route::get('api/v1/__fixture/{thing?}', fn () => response()->json([]))->name('fixture.undocumented');
    Route::getRoutes()->refreshNameLookups();

    expect(undocumentedRoutes(apiRoutes(), $spec))->toBe(['GET /api/v1/__fixture/{thing} (fixture.undocumented)']);
});

it('references every api resource class from a schema', function () {
    $spec = freshExport();
    $resources = resourceClassNames();

    expect($resources)->not->toBeEmpty();

    $unreferenced = array_values(array_filter(
        $resources,
        fn (string $name): bool => ! str_contains((string) json_encode($spec, JSON_UNESCAPED_SLASHES), '#/components/schemas/'.$name.'"'),
    ));

    expect($unreferenced)->toBe([], 'Resources missing from the export: '.implode(', ', $unreferenced));
});

it('states security on authenticated routes and none on public routes', function () {
    $spec = freshExport();

    foreach (apiRoutes() as $route) {
        foreach (documentedMethods($route) as $method) {
            $security = $spec['paths'][substr(normalisedUri($route), 4)][strtolower($method)]['security'] ?? [];
            $label = $method.' '.normalisedUri($route);

            if (requiresAuthentication($route)) {
                expect($security)->not->toBeEmpty($label);
            } else {
                expect($security)->toBe([], $label);
            }
        }
    }
});

it('documents the security scheme for each principal', function () {
    $spec = freshExport();
    $paths = $spec['paths'];

    expect(array_keys($spec['components']['securitySchemes']))->toEqualCanonicalizing(['sanctumCookie', 'playerBearer', 'integrationBearer'])
        ->and($spec['components']['securitySchemes']['sanctumCookie']['in'])->toBe('cookie')
        ->and($paths['/v1/parties/{party}/player']['put']['security'])->toBe([['sanctumCookie' => []]])
        ->and($paths['/v1/parties/{party}/player/poll']['post']['security'])->toBe([['playerBearer' => []]])
        ->and($paths['/v1/ping']['get']['security'])->toBe([])
        ->and($paths['/v1/integration/ping']['get']['security'])->toBe([['integrationBearer' => []]])
        ->and($paths['/v1/integration/ping']['get']['x-abilities'])->toBe(['read'])
        ->and($paths['/v1/integration/ping']['get']['description'])->toContain('read');
});

it('has a committed spec identical to a fresh export', function () {
    expect(committedOpenApi())->toEqual(freshExport());
});

it('derives the player request body from the form request', function () {
    $spec = freshExport();
    $body = resolveSchema($spec, $spec['paths']['/v1/parties/{party}/player']['put']['requestBody']['content']['application/json']['schema']);

    expect($body['required'])->toBe(['player_kind'])
        ->and($body['properties']['player_kind']['type'])->toBe('string');
});

it('documents the create party request body', function () {
    $spec = freshExport();
    $body = resolveSchema($spec, $spec['paths']['/v1/parties']['post']['requestBody']['content']['application/json']['schema']);

    expect($body['required'])->toEqualCanonicalizing(['name', 'music_provider', 'player_kind'])
        ->and($body['properties']['music_provider']['enum'])->toBe(array_keys(config('musicparty.music_providers')))
        ->and($body['properties']['player_kind']['enum'])->toBe(array_keys(config('musicparty.players')));
});

it('serves the docs only to admins outside local', function () {
    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();
});
