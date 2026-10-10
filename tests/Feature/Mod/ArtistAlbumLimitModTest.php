<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\ArtistAlbumLimit\ArtistAlbumLimitMod;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\ModSettings;
use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Fixtures\Mods\ModFixtures;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $settings
 */
function enableArtistAlbumLimit(Party $party, array $settings = []): void
{
    $mod = app(ModRegistry::class)->find(ArtistAlbumLimitMod::ID);
    $defaults = app(ModSettings::class)->defaults($mod);

    ModFixtures::enable($party, $mod, [...$defaults, 'max_per_artist' => 0, 'max_per_album' => 0, 'cooldown_seconds' => 0, ...$settings]);
}

beforeEach(function () {
    Bus::fake([StartPlayback::class]);
    app()->instance(FakeMusicProvider::class, FakeMusicProvider::withDefaultCatalogue());
    $this->party = Party::factory()->live()->create(['hold_requests' => false]);
    $this->member = PartyMember::factory()->for($this->party)->create();
    $this->request = fn (string $trackId = 'track-1') => app(RequestTrack::class)($this->party, $this->member, $trackId);
    $this->existing = fn (array $attributes = [], ?Party $party = null) => TrackRequest::factory()->for($party ?? $this->party)->create([
        'artists' => ['Test Artist'],
        'album' => 'Other Album',
        ...$attributes,
    ]);
});

it('registers with harmless defaults', function () {
    $mod = app(ModRegistry::class)->find('artist-album-limit');

    expect(app(ModSettings::class)->defaults($mod))->toBe(['max_per_artist' => 2, 'max_per_album' => 0, 'cooldown_seconds' => 0]);
});

it('refuses an artist over the cap with a reason', function () {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 2]);
    ($this->existing)();
    ($this->existing)(['status' => RequestStatus::Pending]);

    expect(fn () => ($this->request)('track-1'))->toThrow(RequestRefusedException::class, 'Artist & Album Limit rejected this request: The Queue already holds 2 track(s) by Test Artist.');
    expect(TrackRequest::query()->where('provider_track_id', 'track-1')->exists())->toBeFalse();
});

it('accepts an artist under the cap', function () {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 2]);
    ($this->existing)();

    expect(($this->request)('track-1')->request->status)->toBe(RequestStatus::Queued);
});

it('refuses an album over the cap', function () {
    enableArtistAlbumLimit($this->party, ['max_per_album' => 1]);
    ($this->existing)(['artists' => ['Someone Else'], 'album' => 'Test Album']);

    expect(fn () => ($this->request)('track-3'))->toThrow(RequestRefusedException::class, 'The Queue already holds 1 track(s) from Test Album.');
});

it('refuses an artist that played within the cooldown and allows it afterwards', function () {
    enableArtistAlbumLimit($this->party, ['cooldown_seconds' => 600]);
    Play::factory()->for($this->party)->create(['artists' => ['Test Artist'], 'album' => 'Elsewhere', 'played_at' => now()->subMinutes(5)]);

    expect(fn () => ($this->request)('track-1'))->toThrow(RequestRefusedException::class, 'Test Artist played recently');

    $this->travel(6)->minutes();

    expect(($this->request)('track-1')->request->status)->toBe(RequestStatus::Queued);
});

it('refuses an album that played within the cooldown', function () {
    enableArtistAlbumLimit($this->party, ['cooldown_seconds' => 600]);
    Play::factory()->for($this->party)->create(['artists' => ['Unrelated'], 'album' => 'Test Album', 'played_at' => now()->subMinute()]);

    expect(fn () => ($this->request)('track-3'))->toThrow(RequestRefusedException::class, 'Test Album played recently');
});

it('applies no limit when settings are 0', function () {
    enableArtistAlbumLimit($this->party);
    TrackRequest::factory()->for($this->party)->count(5)->create(['artists' => ['Test Artist'], 'album' => 'Test Album']);
    Play::factory()->for($this->party)->create(['artists' => ['Test Artist'], 'album' => 'Test Album']);

    expect(($this->request)('track-1')->request->status)->toBe(RequestStatus::Queued);
});

it('matches names ignoring case and whitespace', function () {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 1, 'max_per_album' => 1]);
    ($this->existing)(['artists' => ['  TEST   artist '], 'album' => 'x']);

    expect(fn () => ($this->request)('track-1'))->toThrow(RequestRefusedException::class);
});

it('matches albums ignoring case and whitespace', function () {
    enableArtistAlbumLimit($this->party, ['max_per_album' => 1]);
    ($this->existing)(['artists' => ['Nobody'], 'album' => ' test ALBUM ']);

    expect(fn () => ($this->request)('track-3'))->toThrow(RequestRefusedException::class);
});

it('refuses a multi-artist track when any artist is over the cap', function () {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 1]);
    ($this->existing)(['artists' => ['Guest', 'Other Band']]);

    expect(fn () => ($this->request)('track-3'))->toThrow(RequestRefusedException::class, 'Other Band');
});

it('ignores other parties', function () {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 1, 'cooldown_seconds' => 600]);
    $other = Party::factory()->live()->create();
    ($this->existing)([], $other);
    Play::factory()->for($other)->create(['artists' => ['Test Artist']]);

    expect(($this->request)('track-1')->request->status)->toBe(RequestStatus::Queued);
});

it('counts requests from other members', function () {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 1]);
    $other = PartyMember::factory()->for($this->party)->create();
    ($this->existing)(['party_member_id' => $other->id]);

    expect(fn () => ($this->request)('track-1'))->toThrow(RequestRefusedException::class);
});

it('does not count finished requests', function (RequestStatus $status) {
    enableArtistAlbumLimit($this->party, ['max_per_artist' => 1]);
    ($this->existing)(['status' => $status]);

    expect(($this->request)('track-1')->request->status)->toBe(RequestStatus::Queued);
})->with([RequestStatus::Played, RequestStatus::Rejected, RequestStatus::Removed]);

it('ignores plays outside the cooldown window', function () {
    enableArtistAlbumLimit($this->party, ['cooldown_seconds' => 60]);
    Play::factory()->for($this->party)->create(['artists' => ['Test Artist'], 'played_at' => now()->subMinutes(10)]);

    expect(($this->request)('track-1')->request->status)->toBe(RequestStatus::Queued);
});
