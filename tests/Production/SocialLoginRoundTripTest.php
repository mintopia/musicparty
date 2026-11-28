<?php

use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\SocialProviders\DiscordProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Testing\TestResponse;

/**
 * Models a browser's cookie jar: a Secure cookie is never accepted over, or sent back on, plain HTTP.
 */
class BrowserJar
{
    /** @var array<string, string> */
    public array $cookies = [];

    /** @var array<string, bool> */
    public array $secureByName = [];

    public function __construct(private readonly bool $https) {}

    public function store(TestResponse $response): void
    {
        foreach ($response->baseResponse->headers->getCookies() as $cookie) {
            $this->secureByName[$cookie->getName()] = $cookie->isSecure();
            if ($cookie->isSecure() && ! $this->https) {
                continue;
            }
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
    }
}

it('keeps the session through a social login round trip under the configured APP_URL scheme', function () {
    (new RolesSeeder)->run();
    $https = parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https';
    $root = rtrim((string) config('app.url'), '/');
    $provider = providerFor(DiscordProvider::class, 'discord');
    $account = LinkedAccount::factory()->create(['social_provider_id' => $provider->id, 'external_id' => 'd-prod', 'email' => 'prod@example.test']);
    $account->user->forceFill(['first_login' => false])->save();
    fakeRemoteUser('d-prod', 'prod@example.test', ['verified' => true]);
    $this->withoutVite();
    $jar = new BrowserJar($https);

    $visit = function (string $path) use ($jar, $root) {
        $this->app['auth']->forgetGuards();
        $response = $this->withUnencryptedCookies($jar->cookies)->get($root.$path);
        $jar->store($response);

        return $response;
    };

    $visit('/login')->assertOk();
    $visit('/login/discord/return')->assertRedirect();
    $profile = $visit('/profile');

    expect($jar->secureByName[config('session.cookie')])->toBe($https)
        ->and($profile->status())->toBe(200)
        ->and(User::find($account->user_id))->not->toBeNull();
});

it('rejects the profile page for a browser that lost its session cookie', function () {
    $root = rtrim((string) config('app.url'), '/');

    $this->get($root.'/profile')->assertRedirect();
});
