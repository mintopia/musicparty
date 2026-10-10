<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->user = User::factory()->create();
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
    Sanctum::actingAs($this->user);
});

it('links queue entries in the snapshot to the provider page', function () {
    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Playing, 'provider_track_id' => 'track-1', 'party_member_id' => $this->member->id]);
    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued, 'provider_track_id' => 'a b/c', 'party_member_id' => $this->member->id]);

    $snapshot = app(PartyQueueSnapshot::class)->build($this->party);

    expect($snapshot['now_playing']['track']['provider_url'] ?? null)->toBe('https://fake.test/track/track-1')
        ->and($snapshot['queue'][0]['track']['provider_url'])->toBe('https://fake.test/track/a%20b%2Fc');
});

it('has a null provider_url when the party provider is unknown', function () {
    $party = Party::factory()->live()->create(['music_provider' => 'gone']);
    TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued]);

    $snapshot = app(PartyQueueSnapshot::class)->build($party);

    expect($snapshot['queue'][0]['track'])->toHaveKey('provider_url', null);
});

it('exposes provider_url on the API queue', function () {
    TrackRequest::factory()->for($this->party)->create(['status' => RequestStatus::Queued, 'provider_track_id' => 'track-2', 'party_member_id' => $this->member->id]);

    $this->getJson('/api/v1/parties/ABCD/queue')->assertOk()
        ->assertJsonPath('data.0.track.provider_url', 'https://fake.test/track/track-2');
});

it('exposes provider_url on played history', function () {
    Play::factory()->for($this->party)->create(['provider_track_id' => 'track-1']);

    $this->getJson('/api/v1/parties/ABCD/history')->assertOk()
        ->assertJsonPath('data.0.track.provider_url', 'https://fake.test/track/track-1');
});

it('exposes provider_url on search results', function () {
    $this->getJson('/api/v1/parties/ABCD/search?q=alpha')->assertOk()
        ->assertJsonPath('data.0.provider_track_id', 'track-1')
        ->assertJsonPath('data.0.provider_url', 'https://fake.test/track/track-1');
});
