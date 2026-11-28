<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\ModSettingsStore;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

class LockProbingMusicProvider extends FakeMusicProvider
{
    /** @var list<bool> */
    public array $partyLockFreeDuringLookup = [];

    public function __construct(private readonly int $partyId)
    {
        parent::__construct();
    }

    public function getTrack(string $id): ?Track
    {
        $lock = Cache::lock("add-request:{$this->partyId}", 5);
        $this->partyLockFreeDuringLookup[] = $lock->get();
        $lock->release();
        Sleep::for(4)->seconds();

        return parent::getTrack($id);
    }
}

beforeEach(function () {
    Sleep::fake();
    Artisan::call('mods:sync');
    $this->party = Party::factory()->create(['provider_id' => 'fake', 'active' => true]);
    $this->members = User::factory()->count(3)->create(['first_login' => false]);
    foreach ($this->members as $member) {
        PartyMember::factory()->for($this->party)->for($member)->create();
    }
    config(['musicparty.request_lock_wait_seconds' => 0]);
});

it('does not hold the Party lock across the music provider lookup', function () {
    $probe = new LockProbingMusicProvider($this->party->id);
    $probe->withTrack(FakeFallbackPlaylist::track('slow-1'))->withTrack(FakeFallbackPlaylist::track('slow-2'))->withTrack(FakeFallbackPlaylist::track('slow-3'));
    app()->instance(FakeMusicProvider::class, $probe);

    foreach ($this->members as $index => $member) {
        Sanctum::actingAs($member);
        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 'slow-'.($index + 1)])->assertSuccessful();
    }

    expect(UpcomingSong::query()->count())->toBe(3)
        ->and($probe->partyLockFreeDuringLookup)->toBe([true, true, true]);
});

it('does not hold the Party lock across the AI review call', function () {
    app(PartyMods::class)->enable($this->party, AiRequestReviewMod::CODE);
    $store = app(ModSettingsStore::class);
    $store->set($this->party, AiRequestReviewMod::CODE, 'driver', 'decision-model');
    $store->set($this->party, AiRequestReviewMod::CODE, 'api_key', 'sk-secret-key');
    $this->party->forceFill(['allow_requests' => true, 'flag_requests' => true])->save();
    app(FakeMusicProvider::class)->withTrack(FakeFallbackPlaylist::track('ai-1'))->withTrack(FakeFallbackPlaylist::track('ai-2'))->withTrack(FakeFallbackPlaylist::track('ai-3'));
    $lockFreeDuringReview = [];
    Http::fake(function (Request $request) use (&$lockFreeDuringReview) {
        $lock = Cache::lock("add-request:{$this->party->id}", 5);
        $lockFreeDuringReview[] = $lock->get();
        $lock->release();
        Sleep::for(4)->seconds();

        return Http::response(['answers' => ['suitability' => ['type' => 'score', 'score' => 4.0, 'legend' => [0 => 'No', 1 => 'Hmm', 2 => 'Unsure', 3 => 'Ok', 4 => 'Yes'], 'probabilities' => [], 'confidence' => 0.9]]]);
    });

    foreach ($this->members as $index => $member) {
        Sanctum::actingAs($member);
        $this->postJson(route('api.v1.parties.upcomingsongs.store', [$this->party]), ['provider_track_id' => 'ai-'.($index + 1)])->assertSuccessful();
    }

    expect(UpcomingSong::query()->count())->toBe(3)
        ->and($lockFreeDuringReview)->not->toBeEmpty()
        ->and(array_unique($lockFreeDuringReview))->toBe([true]);
});
