<?php

namespace App\Services\OpenApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

class OpenApiGenerator
{
    public const API_PREFIX = 'api/';

    /**
     * Extra 422 descriptions for routes whose action refuses requests beyond validation.
     *
     * @var array<string, string>
     */
    private const array REFUSAL_DESCRIPTIONS = [
        'api.v1.parties.plays.rating.store' => 'Validation failed, or the party has ended so ratings are closed.',
        'api.v1.parties.plays.rating.destroy' => 'The party has ended so ratings are closed.',
        'api.v1.parties.player.poll' => 'The Party is not live, or its Player is not a Polling Player.',
        'api.v1.parties.player.update' => 'Validation failed, or the player is not compatible with the Music Provider (the message lists the compatible options).',
    ];

    public function __construct(private readonly RuleSchemaMapper $mapper) {}

    public static function isApiRoute(Route $route): bool
    {
        return str_starts_with($route->uri(), self::API_PREFIX);
    }

    public static function pathFor(Route $route): string
    {
        return '/'.preg_replace('/\{(\w+)(:\w*)?\??\}/', '{$1}', $route->uri());
    }

    /**
     * @return list<string>
     */
    public static function methodsFor(Route $route): array
    {
        $methods = array_values(array_filter(
            array_map(strtolower(...), $route->methods()),
            fn (string $method): bool => $method !== 'head' && $method !== 'options',
        ));
        sort($methods);

        return $methods;
    }

    /**
     * @param  iterable<Route>|null  $routes
     * @return array<string, mixed>
     */
    public function generate(?iterable $routes = null): array
    {
        $routes ??= RouteFacade::getRoutes()->getRoutes();
        $paths = [];
        $schemas = [];

        foreach ($routes as $route) {
            if (! self::isApiRoute($route)) {
                continue;
            }

            foreach (self::methodsFor($route) as $method) {
                $paths[self::pathFor($route)][$method] = $this->operation($route, $method, $schemas);
            }
        }

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Music Party API',
                'version' => '1.0.0',
            ],
            'servers' => [['url' => '/']],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'sanctumBearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Sanctum bearer token.',
                    ],
                    'sanctumCookie' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => 'laravel_session',
                        'description' => 'Sanctum session cookie with CSRF protection.',
                    ],
                ],
                'schemas' => $schemas,
            ],
        ];

        return $this->sortRecursively($spec);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function toJson(array $spec): string
    {
        return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param  array<string, mixed>  $schemas
     * @return array<string, mixed>
     */
    private function operation(Route $route, string $method, array &$schemas): array
    {
        $operation = [
            'operationId' => $this->operationId($route, $method),
            'summary' => $route->getName() ?? strtoupper($method).' '.self::pathFor($route),
            'parameters' => array_map(fn (string $name): array => [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ], $route->parameterNames()),
            'responses' => ['200' => ['description' => 'Successful response.', 'content' => ['application/json' => ['schema' => $this->responseSchema($route, $schemas)]]]],
        ];

        $rules = $this->rulesFor($route);

        if ($rules !== null) {
            $schema = $this->mapper->map($rules);

            if (in_array($method, ['get', 'delete'], true)) {
                foreach ($schema['properties'] as $name => $property) {
                    $operation['parameters'][] = [
                        'name' => $name,
                        'in' => 'query',
                        'required' => in_array($name, $schema['required'], true),
                        'schema' => $property,
                    ];
                }
            } else {
                $body = ['type' => 'object', 'properties' => $schema['properties']];
                if ($schema['required'] !== []) {
                    $body['required'] = $schema['required'];
                }
                $operation['requestBody'] = [
                    'required' => $schema['required'] !== [],
                    'content' => ['application/json' => ['schema' => $body]],
                ];
            }

            $operation['responses']['422'] = ['description' => 'Validation failed.'];
        }

        $refusal = self::REFUSAL_DESCRIPTIONS[$route->getName() ?? ''] ?? null;

        if ($refusal !== null) {
            $operation['responses']['422'] = ['description' => $refusal];
        }

        if ($this->requiresSanctum($route)) {
            $operation['security'] = [['sanctumBearer' => []], ['sanctumCookie' => []]];
            $operation['responses']['401'] = ['description' => 'Unauthenticated.'];
        }

        if ($this->hasMiddleware($route, 'Authorize') || $this->hasMiddleware($route, 'can:') || $this->hasMiddleware($route, 'player.token')) {
            $operation['responses']['403'] = ['description' => 'Forbidden.'];
        }

        if ($operation['parameters'] === []) {
            unset($operation['parameters']);
        }

        return $operation;
    }

    private function operationId(Route $route, string $method): string
    {
        $segments = preg_split('/[^A-Za-z0-9]+/', self::pathFor($route), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $method.implode('', array_map(ucfirst(...), $segments));
    }

    private function requiresSanctum(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(auth|.*\\\\Authenticate):.*sanctum/', $middleware) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasMiddleware(Route $route, string $needle): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_contains($middleware, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function reflectAction(Route $route): ?ReflectionMethod
    {
        $action = $route->getAction('uses');

        if (! is_string($action) || ! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action, 2);

        try {
            return new ReflectionMethod($class, $method);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rulesFor(Route $route): ?array
    {
        $reflection = $this->reflectAction($route);

        if ($reflection === null) {
            return null;
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if (! is_subclass_of($class, FormRequest::class)) {
                continue;
            }

            try {
                $request = new $class;
                /** @var array<string, mixed> $rules */
                $rules = $request->rules(); // @phpstan-ignore method.notFound

                return $rules;
            } catch (Throwable) {
                return [];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schemas
     * @return array<string, mixed>
     */
    private function responseSchema(Route $route, array &$schemas): array
    {
        $class = $this->resourceFor($route);

        if ($class === null) {
            return ['type' => 'object'];
        }

        $name = class_basename($class);
        $schemas[$name] = ['type' => 'object', 'additionalProperties' => true];

        if (is_subclass_of($class, ResourceCollection::class)) {
            return [
                'type' => 'object',
                'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object']]],
            ];
        }

        return [
            'type' => 'object',
            'properties' => ['data' => ['$ref' => '#/components/schemas/'.$name]],
        ];
    }

    /**
     * @return class-string<JsonResource>|null
     */
    private function resourceFor(Route $route): ?string
    {
        $reflection = $this->reflectAction($route);
        $file = $reflection?->getFileName();

        if ($reflection === null || $file === false || $file === null) {
            return null;
        }

        $lines = file($file) ?: [];
        $source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        if (preg_match('/new\s+\\\\?([\w\\\\]+)\s*\(/', $source, $match) !== 1) {
            return null;
        }

        $short = $match[1];
        $contents = implode('', $lines);
        $candidates = [$short];

        if (preg_match('/^use\s+([\w\\\\]+\\\\'.preg_quote($short, '/').');/m', $contents, $use) === 1) {
            array_unshift($candidates, $use[1]);
        }

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, JsonResource::class)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sortRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
