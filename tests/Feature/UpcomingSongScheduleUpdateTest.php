<?php

use App\Enums\Purpose;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMapper;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\Spotify\SpotifyGateway;
use Database\Seeders\PartyMemberRoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeSpotify;

require_once __DIR__.'/../Support/PartyPageHelpers.php';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PartyMemberRoleSeeder::class);
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $this->fake = FakeSpotify::install();
    $this->owner = User::factory()->create();
    pageSearchLinkAccount($this->owner, 'spotify', 'control-token');
    $this->party = pageSearchParty($this->owner);
    $this->party->forceFill(['timezone' => 'America/New_York'])->save();
    $this->member = pageSearchMember($this->party);
    $upcoming = new UpcomingSong;
    $upcoming->party()->associate($this->party);
    $upcoming->song()->associate(Song::fromTrack(SpotifyMapper::track($this->fake->getTrack('t1'))));
    $upcoming->user()->associate($this->member->user);
    $upcoming->save();
    $this->upcoming = $upcoming;
    $this->url = route('parties.songs.update', [$this->party->code, $upcoming->id]);
});

it('sets the schedule converting party time to UTC and exposes it on the detail page', function () {
    $this->actingAs($this->owner)->put($this->url, ['scheduled_for' => '2030-06-01T20:30'])
        ->assertRedirect(route('parties.songs.show', [$this->party->code, $this->upcoming->id]))
        ->assertSessionHas('successMessage');

    $fresh = $this->upcoming->fresh();
    expect($fresh->scheduled_for->utc()->format('Y-m-d H:i'))->toBe('2030-06-02 00:30')
        ->and($fresh->not_before->utc()->format('Y-m-d H:i'))->toBe('2030-06-02 00:30');

    $this->actingAs($this->owner)->get(route('parties.songs.show', [$this->party->code, $this->upcoming->id]))
        ->assertInertia(fn (Assert $page) => $page->where('song.scheduledForInput', '2030-06-01T20:30')->has('song.scheduledFor.iso'));
});

it('clears the schedule', function () {
    $this->upcoming->forceFill(['scheduled_for' => now()->addDay(), 'not_before' => now()->addDay()])->save();

    $this->actingAs($this->owner)->put($this->url, ['scheduled_for' => ''])->assertRedirect();

    $fresh = $this->upcoming->fresh();
    expect($fresh->scheduled_for)->toBeNull()->and($fresh->not_before)->toBeNull();
});

it('rejects an invalid date', function () {
    $this->actingAs($this->owner)->put($this->url, ['scheduled_for' => 'not-a-date'])
        ->assertSessionHasErrors('scheduled_for');
    expect($this->upcoming->fresh()->scheduled_for)->toBeNull();
});

it('forbids non-moderator members', function () {
    $this->actingAs($this->member->user)->put($this->url, ['scheduled_for' => '2030-06-01T20:30'])->assertForbidden();
    expect($this->upcoming->fresh()->scheduled_for)->toBeNull();
});
