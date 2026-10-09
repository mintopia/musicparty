<?php

namespace App\Services\OpenApi;

use Illuminate\Routing\Route;

class OpenApiCoverage
{
    /**
     * @param  array<string, mixed>  $spec
     * @param  iterable<Route>  $routes
     * @return list<string>
     */
    public static function missing(array $spec, iterable $routes): array
    {
        $paths = is_array($spec['paths'] ?? null) ? $spec['paths'] : [];
        $missing = [];

        foreach ($routes as $route) {
            if (! OpenApiGenerator::isApiRoute($route)) {
                continue;
            }

            $path = OpenApiGenerator::pathFor($route);

            foreach (OpenApiGenerator::methodsFor($route) as $method) {
                if (! isset($paths[$path][$method])) {
                    $label = strtoupper($method).' '.$path;
                    $missing[] = $route->getName() !== null ? $label.' ('.$route->getName().')' : $label;
                }
            }
        }

        sort($missing);

        return array_values(array_unique($missing));
    }
}
