<?php

use App\Jobs\PartyUpdate;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\Role;
use App\Models\User;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeSpotify;

function webhookParty(): Party
{
    $party = new Party;
    $party->code = 'WHKS';
    $party->name = 'Webhook Party';
    $party->user_id = User::factory()->create()->getKey();
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

function queuedParty(PartyUpdate $job): int
{
    return new ReflectionProperty($job, 'party')->getValue($job)->id;
}

function webhookMember(Party $party, string $roleCode): User
{
    $user = User::factory()->create();
    $role = PartyMemberRole::query()->where('code', $roleCode)->first() ?? PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $row = new PartyMember;
    $row->party_id = $party->id;
    $row->user_id = $user->id;
    $row->role_id = $role->id;
    $row->save();

    return $user;
}

beforeEach(function () {
    Cache::flush();
    Queue::fake();
});

dataset('authenticated webhook routes', [
    'librespot' => ['webhooks.parties.update', ['event' => 'playing']],
    'simple' => ['webhooks.parties.simple', []],
]);

it('returns 401 to a guest on the authenticated webhook and dispatches nothing', function (string $routeName, array $body) {
    $party = webhookParty();

    $this->postJson(route($routeName, $party), $body)->assertUnauthorized();

    Queue::assertNothingPushed();
})->with('authenticated webhook routes');

it('returns 403 to a user who cannot manage the party and dispatches nothing', function (string $routeName, array $body) {
    $party = webhookParty();
    Sanctum::actingAs(webhookMember($party, 'user'));

    $this->postJson(route($routeName, $party), $body)->assertForbidden();

    Queue::assertNothingPushed();
})->with('authenticated webhook routes');

it('returns 404 for an unknown party code on the authenticated webhook', function (string $routeName, array $body) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route($routeName, 'NOPE'), $body)->assertNotFound();

    Queue::assertNothingPushed();
})->with('authenticated webhook routes');

it('accepts the party owner, a party owner-role member and an admin', function (string $actor) {
    $party = webhookParty();
    $user = match ($actor) {
        'owner' => $party->user,
        'owner-role member' => webhookMember($party, 'owner'),
        'admin' => tap(User::factory()->create(), function (User $user) {
            $user->roles()->attach(Role::query()->where('code', 'admin')->first() ?? Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']));
        }),
    };
    Sanctum::actingAs($user);

    $this->postJson(route('webhooks.parties.simple', $party))->assertNoContent();
})->with(['owner', 'owner-role member', 'admin']);

it('dispatches PartyUpdate for the party on a librespot playback event', function (string $event) {
    $party = webhookParty();
    Sanctum::actingAs($party->user);

    $this->postJson(route('webhooks.parties.update', $party), ['event' => $event])->assertNoContent();

    Queue::assertPushed(PartyUpdate::class, 1);
    Queue::assertPushed(PartyUpdate::class, fn (PartyUpdate $job) => queuedParty($job) === $party->id);
})->with(['started', 'playing', 'paused', 'stopped']);

it('accepts but ignores librespot events that do not change playback', function (string $event) {
    $party = webhookParty();
    Sanctum::actingAs($party->user);

    $this->postJson(route('webhooks.parties.update', $party), ['event' => $event])->assertNoContent();

    Queue::assertNothingPushed();
})->with(['changed', 'preloading', 'volume_set']);

it('rejects a librespot request with a missing or unknown event', function (array $body) {
    $party = webhookParty();
    Sanctum::actingAs($party->user);

    $this->postJson(route('webhooks.parties.update', $party), $body)->assertUnprocessable();

    Queue::assertNothingPushed();
})->with([
    'missing' => [[]],
    'unknown' => [['event' => 'exploded']],
]);

it('dispatches PartyUpdate on the simple webhook when queueing is enabled', function (bool $afterRequest) {
    config(['musicparty.webhook_should_queue' => true, 'musicparty.webhook_dispatch_after_request' => $afterRequest]);
    $party = webhookParty();
    Sanctum::actingAs($party->user);

    $this->postJson(route('webhooks.parties.simple', $party))->assertNoContent();

    Queue::assertPushed(PartyUpdate::class, 1);
    Queue::assertPushed(PartyUpdate::class, fn (PartyUpdate $job) => queuedParty($job) === $party->id);
})->with([true, false]);

it('does not queue on the simple webhook when queueing is disabled and updates inline', function () {
    config(['musicparty.webhook_should_queue' => false]);
    $party = Party::factory()->create(['code' => 'INLN']);
    Sanctum::actingAs($party->user);

    $this->postJson(route('webhooks.parties.simple', $party))->assertNoContent();

    Queue::assertNotPushed(PartyUpdate::class);
});

it('accepts an unauthenticated soloist webhook and dispatches PartyUpdate (no token auth today)', function () {
    $party = webhookParty();

    $this->postJson(route('webhooks.parties.soloist', $party))->assertNoContent();

    Queue::assertPushed(PartyUpdate::class, 1);
    Queue::assertPushed(PartyUpdate::class, fn (PartyUpdate $job) => queuedParty($job) === $party->id);
});

it('returns 404 for an unknown party code on the soloist webhook', function () {
    $this->postJson(route('webhooks.parties.soloist', 'NOPE'))->assertNotFound();

    Queue::assertNothingPushed();
});

it('serves the webhooks at their literal paths', function () {
    expect(route('webhooks.parties.update', 'ABCD', false))->toBe('/webhooks/parties/ABCD/librespot')
        ->and(route('webhooks.parties.simple', 'ABCD', false))->toBe('/webhooks/parties/ABCD/simple')
        ->and(route('webhooks.parties.soloist', 'ABCD', false))->toBe('/webhooks/parties/ABCD/soloist');
});

it('queues webhook-triggered updates with the Webhook source', function (string $routeName, array $body, bool $auth) {
    $party = webhookParty();
    if ($auth) {
        Sanctum::actingAs($party->user);
    }

    $this->postJson(route($routeName, $party), $body)->assertNoContent();

    Queue::assertPushed(PartyUpdate::class, fn (PartyUpdate $job) => new ReflectionProperty($job, 'source')->getValue($job) === PlaybackSource::Webhook);
})->with([
    'librespot' => ['webhooks.parties.update', ['event' => 'playing'], true],
    'simple' => ['webhooks.parties.simple', [], true],
    'soloist' => ['webhooks.parties.soloist', [], false],
]);

it('records the Webhook source when a webhook update syncs and Polling for the poll chain', function () {
    FakeSpotify::install();
    $party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'poll' => false, 'backup_playlist_id' => 'BACKUP']);
    Cache::flush();
    $fake = app(FakeMusicProvider::class);
    $fake->withTrack(new Track('fake', 'W', 'W', 200000, false, true, new AlbumRef('album-w', 'Album', null), [new ArtistRef('artist1', 'Artist')]));
    $fake->withPlayback(new PlaybackState(true, 'W', null, 200000, 1000, CarbonImmutable::now(), false, 'off', 'dev', 'Dev', PlaybackSource::Polling));

    (new PartyUpdate($party->fresh(), PlaybackSource::Webhook))->handle();
    expect($party->fresh()->playback_source)->toBe(PlaybackSource::Webhook);

    (new PartyUpdate($party->fresh()))->handle();
    expect($party->fresh()->playback_source)->toBe(PlaybackSource::Polling);
});
