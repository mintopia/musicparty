<?php

use App\Events\User\SpotifyAccessTokenUpdatedEvent;
use App\Events\WebPlayerTokenRefreshed;
use App\Http\Controllers\WebPlayerController;
use App\Listeners\BroadcastWebPlayerToken;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Spotify\SpotifyGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use SpotifyWebAPI\SpotifyWebAPIException;

beforeEach(function () {
    $this->freezeTime();
});

function cascadeAccount(): LinkedAccount
{
    $provider = SocialProvider::where('code', 'spotify')->first() ?? SocialProvider::factory()->create(['code' => 'spotify']);

    return LinkedAccount::factory()->create([
        'user_id' => User::factory()->create()->id,
        'social_provider_id' => $provider->id,
        'access_token_expires_at' => now()->addMinute(),
    ]);
}

it('keeps refreshing the remaining accounts when one account fails', function () {
    $bad = cascadeAccount();
    $good = cascadeAccount();
    $calls = [];
    $gateway = Mockery::mock(SpotifyGateway::class);
    $gateway->shouldReceive('client')->twice()->andReturnUsing(function (User $user) use ($bad, &$calls) {
        $calls[] = $user->id;
        if ($user->id === $bad->user_id) {
            throw new SpotifyWebAPIException('boom', 500);
        }

        return Mockery::mock(SpotifyWebAPI\SpotifyWebAPI::class);
    });
    app()->instance(SpotifyGateway::class, $gateway);

    $this->artisan('party:refreshaccesstokens')->assertSuccessful();

    expect($calls)->toContain($bad->user_id, $good->user_id);
});

it('returns 503 from the web player endpoints when Spotify fails', function () {
    $party = Party::factory()->create();
    $gateway = Mockery::mock(SpotifyGateway::class);
    $gateway->shouldReceive('accessToken')->andThrow(new SpotifyWebAPIException('boom', 500));
    app()->instance(SpotifyGateway::class, $gateway);
    $controller = app(WebPlayerController::class);

    $response = $controller->token($party, $gateway);

    expect($response->getStatusCode())->toBe(503);
});

it('logs a broadcast failure instead of failing the caller', function () {
    $party = Party::factory()->create();
    Log::spy();
    Event::listen(WebPlayerTokenRefreshed::class, fn () => throw new RuntimeException('reverb down'));
    $event = new SpotifyAccessTokenUpdatedEvent($party->user, 'tok');

    (new BroadcastWebPlayerToken)->handle($event);

    Log::shouldHaveReceived('warning')->once();
});
