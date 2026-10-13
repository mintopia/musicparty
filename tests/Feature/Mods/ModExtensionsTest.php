<?php

use App\Http\Resources\TrackAnnotationResource;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\Bar\BarMod;
use App\Mods\Contracts\DecoratesTracks;
use App\Mods\Contracts\ModifiesScore;
use App\Mods\ModExtensions;
use App\Mods\PartyMods;
use App\Mods\TrackDecoration;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\QueueSelector;
use App\Services\Requests\TrackEligibility;
use Illuminate\Support\Facades\Cache;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Support\ExtensionModFixture;

beforeEach(function () {
    ExtensionModFixture::bindRegistry();
    $this->artisan('mods:sync')->assertSuccessful();
    $this->partyA = Party::factory()->create(['active' => true, 'weighted' => false]);
    $this->partyB = Party::factory()->create(['active' => true, 'weighted' => false]);
    Cache::flush();
    app()->forgetInstance(FakeMusicProvider::class);
    app(FakeMusicProvider::class)->withQueueDepth(0);
});

function extRequest(Party $party, string $trackId, int $score = 0): UpcomingSong
{
    $song = Song::factory()->create(['provider_track_id' => $trackId]);

    return UpcomingSong::factory()->for($party)->create(['song_id' => $song->id, 'score' => $score, 'queued_at' => null]);
}

function extSent(): array
{
    return array_map(fn ($track) => $track->id, app(FakeMusicProvider::class)->sentToPlayer());
}

it('lists only registry mods that implement the contract and are enabled for the party', function () {
    app(PartyMods::class)->enable($this->partyA, 'bar');
    app(PartyMods::class)->enable($this->partyB, 'boom');
    $extensions = app(ModExtensions::class);

    expect($extensions->for($this->partyA, ModifiesScore::class))->toHaveCount(1)
        ->and($extensions->for($this->partyA, ModifiesScore::class)[0])->toBeInstanceOf(BarMod::class)
        ->and($extensions->for($this->partyB, DecoratesTracks::class))->toHaveCount(1)
        ->and($extensions->for($this->partyA, stdClass::class))->toBe([]);
});

it('lets an enabled score modifier change which request is selected', function () {
    app(PartyMods::class)->enable($this->partyA, 'bar');
    BarMod::$bonuses = ['LOW' => 10];
    extRequest($this->partyA, 'HIGH', 5);
    extRequest($this->partyA, 'LOW', 1);

    app(QueueSelector::class)->sendNext($this->partyA);

    expect(extSent())->toBe(['LOW']);
});

it('ignores a score modifier from a mod that is not enabled for the party', function () {
    app(PartyMods::class)->enable($this->partyA, 'bar');
    BarMod::$bonuses = ['LOW' => 10];
    extRequest($this->partyB, 'HIGH', 5);
    extRequest($this->partyB, 'LOW', 1);

    app(QueueSelector::class)->sendNext($this->partyB);

    expect(extSent())->toBe(['HIGH']);
});

it('applies score modifiers to the weights of a weighted draw', function () {
    Party::whereKey($this->partyA->id)->update(['weighted' => true]);
    $this->partyA->refresh();
    app(PartyMods::class)->enable($this->partyA, 'bar');
    BarMod::$bonuses = ['ZERO' => 50];
    extRequest($this->partyA, 'ZERO', 0);
    extRequest($this->partyA, 'ONE', 1);
    app()->instance(Randomizer::class, new Randomizer(new Mt19937(1)));

    $selector = app(QueueSelector::class);
    $scores = UpcomingSong::with('song', 'party')->get()->mapWithKeys(fn ($request) => [$request->song->provider_track_id => $selector->effectiveScore($request)]);
    $selector->sendNext($this->partyA);

    expect($scores->all())->toBe(['ZERO' => 50, 'ONE' => 1])
        ->and(extSent())->toHaveCount(1);
});

it('skips a failing score modifier and keeps the stored score', function () {
    app(PartyMods::class)->enable($this->partyA, 'boom');
    extRequest($this->partyA, 'HIGH', 5);
    extRequest($this->partyA, 'LOW', 1);

    app(QueueSelector::class)->sendNext($this->partyA);

    expect(extSent())->toBe(['HIGH']);
});

it('lets a mod add a system request through addRequest that is then selectable', function () {
    $song = Song::factory()->create(['provider_track_id' => 'SYSTEM']);

    $request = app(QueueSelector::class)->addRequest($this->partyA, $song, null, ['score_adjustment' => 3]);
    app(QueueSelector::class)->sendNext($this->partyA);

    expect($request->user_id)->toBeNull()
        ->and($request->score)->toBe(3)
        ->and(extSent())->toBe(['SYSTEM'])
        ->and($request->fresh()->queued_at)->not->toBeNull();
});

it('includes a decoration from an enabled mod in toApi and not for a party without it', function () {
    app(PartyMods::class)->enable($this->partyA, 'bar');
    $decorated = extRequest($this->partyA, 'ONE')->load('song');
    $plain = extRequest($this->partyB, 'TWO')->load('song');

    expect($decorated->toApi())->toMatchArray(['fallback_name' => 'Bar ONE', 'css_classes' => '', 'decoration' => ['displayName' => 'Bar ONE', 'label' => 'bar']])
        ->and($plain->toApi())->toMatchArray(['fallback_name' => 'Fallback Track', 'css_classes' => '', 'decoration' => null]);
});

it('skips a throwing decorator and still merges the others first-non-null per field', function () {
    app(PartyMods::class)->enable($this->partyA, 'boom');
    app(PartyMods::class)->enable($this->partyA, 'bar');
    $request = extRequest($this->partyA, 'ONE')->load('song');

    $decoration = app(ModExtensions::class)->decorationFor($this->partyA, $request->song, $request);

    expect($decoration)->toEqual(new TrackDecoration(displayName: 'Bar ONE', label: 'bar'))
        ->and($decoration->toArray())->toBe(['displayName' => 'Bar ONE', 'label' => 'bar']);
});

it('returns no decoration when no enabled mod decorates', function () {
    $request = extRequest($this->partyA, 'ONE')->load('song');

    expect(app(ModExtensions::class)->decorationFor($this->partyA, $request->song, $request))->toBeNull();
});

it('carries a mod decoration on queued track annotations for TrackRow', function () {
    app(PartyMods::class)->enable($this->partyA, 'bar');
    $request = extRequest($this->partyA, 'ONE')->load('song');
    $member = PartyMember::factory()->for($this->partyA)->for(User::factory())->create();
    $annotations = app(TrackEligibility::class)->annotate($this->partyA, $member, [$request->song->toTrack()]);

    expect((new TrackAnnotationResource($annotations[0]))->resolve()['decoration'])->toBe(['displayName' => 'Bar ONE', 'label' => 'bar']);

    $otherMember = PartyMember::factory()->for($this->partyB)->for(User::factory())->create();
    $plain = extRequest($this->partyB, 'TWO')->load('song');
    $other = app(TrackEligibility::class)->annotate($this->partyB, $otherMember, [$plain->song->toTrack()]);

    expect((new TrackAnnotationResource($other[0]))->resolve()['decoration'])->toBeNull();
});
