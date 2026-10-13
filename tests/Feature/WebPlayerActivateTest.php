<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\User;
use App\Services\Music\Data\PlaybackSource;
use App\Services\PlaybackControl;
use App\Services\Spotify\SpotifyRelinkRequiredException;
use Illuminate\Support\Facades\Route;

function activateParty(): array
{
    Route::getRoutes()->refreshNameLookups();
    $host = User::factory()->create();
    $party = new Party;
    $party->code = 'ABCD';
    $party->name = 'Test Party';
    $party->user_id = $host->id;
    Party::withoutEvents(fn () => $party->save());

    $owner = User::factory()->create();
    $role = PartyMemberRole::query()->forceCreate(['code' => 'owner', 'name' => 'Owner']);
    $member = new PartyMember;
    $member->party_id = $party->id;
    $member->user_id = $owner->id;
    $member->role_id = $role->id;
    $member->save();

    return [$party, $owner];
}

beforeEach(function () {
    $this->withoutVite();
});

it('registers the device and starts playback when the owner activates the web player', function () {
    [$party, $owner] = activateParty();
    $this->mock(PlaybackControl::class)
        ->shouldReceive('perform')->once()
        ->with(Mockery::type(Party::class), 'play', 'dev-xyz');

    $this->actingAs($owner)
        ->postJson(route('parties.player.activate', $party), ['device_id' => 'dev-xyz'])
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Cache-Control', 'no-store, private');

    $fresh = $party->fresh();
    expect($fresh->device_id)->toBe('dev-xyz')
        ->and($fresh->device_name)->toBe('Music Party ABCD')
        ->and($fresh->playback_source)->toBe(PlaybackSource::WebPlayer)
        ->and($fresh->playback_received_at)->not->toBeNull();
});

it('returns 409 and leaves the device unregistered when Spotify needs re-linking', function () {
    [$party, $owner] = activateParty();
    $this->mock(PlaybackControl::class)
        ->shouldReceive('perform')->once()->andThrow(new SpotifyRelinkRequiredException);

    $this->actingAs($owner)
        ->postJson(route('parties.player.activate', $party), ['device_id' => 'dev-xyz'])
        ->assertStatus(409);

    expect($party->fresh()->device_id)->toBeNull();
});

it('rejects an activate request without a device id', function () {
    [$party, $owner] = activateParty();
    $this->mock(PlaybackControl::class)->shouldReceive('perform')->never();

    $this->actingAs($owner)
        ->postJson(route('parties.player.activate', $party), [])
        ->assertStatus(422);
});
