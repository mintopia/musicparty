<?php

namespace App\Providers;

use App\Domain\Admin\IntegrationAbility;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ApiDocumentationServiceProvider extends ServiceProvider
{
    public const string SESSION_COOKIE = 'sanctumCookie';

    public const string PLAYER_BEARER = 'playerBearer';

    public const string INTEGRATION_BEARER = 'integrationBearer';

    public function boot(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->components->addSecurityScheme(self::SESSION_COOKIE, $this->sessionCookieScheme());
                $openApi->components->addSecurityScheme(self::PLAYER_BEARER, $this->bearerScheme('Player Token, a Sanctum bearer token issued to a Party Player.'));
                $openApi->components->addSecurityScheme(self::INTEGRATION_BEARER, $this->bearerScheme('Integration Token, a Sanctum bearer token issued to an Integration, carrying the required ability.'));
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo): void {
                $this->documentSummary($operation, $routeInfo->route);
                $this->documentSecurity($operation, $routeInfo->route);
            });
    }

    private function sessionCookieScheme(): SecurityScheme
    {
        return SecurityScheme::apiKey('cookie', 'laravel_session')
            ->setDescription('Sanctum session cookie. State-changing requests must also send the X-XSRF-TOKEN header from the XSRF-TOKEN cookie (CSRF protection).');
    }

    private function bearerScheme(string $description): SecurityScheme
    {
        return SecurityScheme::http('bearer')->setDescription($description);
    }

    private function documentSummary(Operation $operation, Route $route): void
    {
        if ($operation->summary !== '') {
            return;
        }

        $operation->summary = Str::of((string) $route->getName())
            ->after('api.v1.')
            ->replace(['.', '-'], ' ')
            ->ucfirst()
            ->toString();
    }

    private function documentSecurity(Operation $operation, Route $route): void
    {
        $middleware = collect($route->gatherMiddleware())->filter(fn ($item): bool => is_string($item));

        $abilities = $middleware
            ->filter(fn (string $item): bool => str_starts_with($item, 'integration.ability:') || str_starts_with($item, 'abilities:') || str_starts_with($item, 'ability:'))
            ->flatMap(fn (string $item): array => explode(',', explode(':', $item, 2)[1]))
            ->values()
            ->all();

        $schemes = match (true) {
            $middleware->contains('export.access') => [self::SESSION_COOKIE, self::INTEGRATION_BEARER],
            $middleware->contains('player.token') => [self::PLAYER_BEARER],
            $abilities !== [] => [self::INTEGRATION_BEARER],
            $middleware->contains(fn (string $item): bool => $item === 'auth' || str_starts_with($item, 'auth:')) => [self::SESSION_COOKIE],
            default => [],
        };

        if ($middleware->contains('export.access')) {
            $abilities = [IntegrationAbility::Export->value];
        }

        $operation->security = array_map(
            fn (string $scheme): SecurityRequirement => new SecurityRequirement([$scheme => []]),
            $schemes,
        );

        if ($abilities !== [] && in_array(self::INTEGRATION_BEARER, $schemes, true)) {
            $operation->setExtensionProperty('abilities', $abilities);
            $operation->description = trim($operation->description."\n\nRequired Integration Token abilities: ".implode(', ', $abilities).'.');
        }
    }
}
