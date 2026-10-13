<?php

use App\Enums\ScheduleFrequency;
use App\Models\ModData;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PlayedSong;
use App\Models\SongRating;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use App\Mods\ModScheduler;
use App\Mods\PartyMods;
use App\Mods\Trust\TrustMod;
use App\Mods\Trust\TrustScores;
use App\Services\ModDataStore;
use App\Services\ModSettingsStore;
use App\Services\QueueSelector;
use Illuminate\Support\Facades\Artisan;

function trustParty(bool $enable = true): array
{
    $party = Party::factory()->create(['active' => true]);
    $owner = $party->user;
    $users = ['owner' => $owner];
    foreach (['a', 'b', 'c'] as $name) {
        $users[$name] = User::factory()->create();
    }
    foreach ($users as $user) {
        PartyMember::factory()->for($party)->for($user)->create();
    }
    if ($enable) {
        app(PartyMods::class)->enable($party, TrustMod::CODE);
    }

    return [$party, $users];
}

function trustRequests(Party $party, User $requester, int $count): array
{
    return UpcomingSong::factory()->count($count)->create(['party_id' => $party->id, 'user_id' => $requester->id])->all();
}

function trustEndorse(User $voter, array $requests, array $attributes = []): void
{
    foreach ($requests as $request) {
        Vote::factory()->create(['upcoming_song_id' => $request->id, 'user_id' => $voter->id] + $attributes);
    }
}

function trustEndorseAll(Party $party, array $users, array $attributes = []): void
{
    trustEndorse($users['owner'], trustRequests($party, $users['a'], 3), $attributes);
    trustEndorse($users['owner'], trustRequests($party, $users['b'], 3), $attributes);
}

function trustScore(Party $party, User $user): float
{
    return app(TrustScores::class)->scoreFor($party, $user->id);
}

beforeEach(function () {
    Artisan::call('mods:sync');
    $this->travelTo(now()->startOfMinute());
});

it('stores scores on the scheduled recalculation for that party only', function () {
    [$party, $users] = trustParty();
    [$other, $otherUsers] = trustParty(enable: false);
    trustEndorseAll($party, $users);
    trustEndorseAll($other, $otherUsers);

    app(ModScheduler::class)->run(ScheduleFrequency::Minute);

    expect(trustScore($party, $users['a']))->toBeGreaterThan(0)->toBeLessThanOrEqual(1)
        ->and(trustScore($party, $users['b']))->toBeGreaterThan(0)
        ->and(trustScore($party, $users['c']))->toBe(0.0)
        ->and(trustScore($other, $otherUsers['a']))->toBe(0.0)
        ->and(ModData::where('mod_code', TrustMod::CODE)->where('owner_type', $other->getMorphClass())->where('owner_id', $other->id)->count())->toBe(0);
});

it('throttles recalculation by the interval setting', function () {
    [$party, $users] = trustParty();
    trustEndorseAll($party, $users);
    $member = PartyMember::where('party_id', $party->id)->where('user_id', $users['c']->id)->firstOrFail();
    $scheduler = app(ModScheduler::class);

    $scheduler->run(ScheduleFrequency::Minute);
    app(ModDataStore::class)->set($member, TrustMod::CODE, 'trustscore', 0.123);

    $this->travelTo(now()->addMinutes(14));
    $scheduler->run(ScheduleFrequency::Minute);
    expect(trustScore($party, $users['c']))->toBe(0.123);

    $this->travelTo(now()->addMinutes(2));
    $scheduler->run(ScheduleFrequency::Minute);
    expect(trustScore($party, $users['c']))->toBe(0.0);
});

it('ignores endorsements outside the window and downvotes', function (string $case) {
    [$party, $users] = trustParty();
    match ($case) {
        'old' => trustEndorseAll($party, $users, ['created_at' => now()->subDays(8)]),
        'down' => trustEndorseAll($party, $users, ['value' => -1]),
    };

    $scores = app(TrustScores::class)->recalculate($party);

    expect(array_sum($scores))->toBe(0.0);
})->with(['old', 'down']);

it('counts positive ratings on played requests', function () {
    [$party, $users] = trustParty();
    foreach (trustRequests($party, $users['a'], 3) as $request) {
        $played = PlayedSong::factory()->create(['party_id' => $party->id, 'upcoming_song_id' => $request->id]);
        SongRating::factory()->create(['played_song_id' => $played->id, 'user_id' => $users['owner']->id]);
    }
    foreach (trustRequests($party, $users['b'], 2) as $request) {
        $played = PlayedSong::factory()->create(['party_id' => $party->id, 'upcoming_song_id' => $request->id]);
        SongRating::factory()->create(['played_song_id' => $played->id, 'user_id' => $users['owner']->id]);
    }

    app(TrustScores::class)->recalculate($party);

    expect(trustScore($party, $users['a']))->toBeGreaterThan(trustScore($party, $users['b']))
        ->and(trustScore($party, $users['b']))->toBeGreaterThan(0);
});

it('yields zeros without error for small or empty parties', function () {
    $party = Party::factory()->create(['active' => true]);
    $member = User::factory()->create();
    PartyMember::factory()->for($party)->for($member)->create();
    trustEndorse($party->user, trustRequests($party, $member, 6));
    $empty = Party::factory()->create(['active' => true]);

    expect(app(TrustScores::class)->recalculate($party))->toBe([$member->id => 0.0])
        ->and(app(TrustScores::class)->recalculate($empty))->toBe([]);
});

it('lets the trusted user setting override the party owner', function () {
    [$party, $users] = trustParty();
    trustEndorseAll($party, $users);
    $scores = app(TrustScores::class);
    expect($scores->trustedUser($party)?->id)->toBe($users['owner']->id);

    app(ModSettingsStore::class)->set($party, TrustMod::CODE, 'trusted_user_id', $users['a']->id);
    $scores->recalculate($party);

    expect($scores->trustedUser($party)?->id)->toBe($users['a']->id)
        ->and(trustScore($party, $users['a']))->toBe(1.0)
        ->and(trustScore($party, $users['b']))->toBe(0.0);
});

it('falls back to the migrated legacy trusted user when no setting is present', function () {
    [$party, $users] = trustParty();
    app(ModDataStore::class)->set($party, TrustMod::CODE, 'trusted_user_id', $users['b']->id);
    $scores = app(TrustScores::class);

    expect($scores->trustedUser($party)?->id)->toBe($users['b']->id);

    app(ModSettingsStore::class)->set($party, TrustMod::CODE, 'trusted_user_id', $users['a']->id);

    expect($scores->trustedUser($party)?->id)->toBe($users['a']->id);
});

it('adds the trust bonus to member requests only while enabled', function () {
    [$party, $users] = trustParty();
    app(ModSettingsStore::class)->set($party, TrustMod::CODE, 'max_bonus', 10);
    trustEndorseAll($party, $users);
    app(TrustScores::class)->recalculate($party);
    $trust = trustScore($party, $users['a']);
    $selector = app(QueueSelector::class);
    $memberRequest = UpcomingSong::factory()->create(['party_id' => $party->id, 'user_id' => $users['a']->id, 'score' => 2])->load('party');
    $systemRequest = UpcomingSong::factory()->create(['party_id' => $party->id, 'user_id' => null, 'score' => 2])->load('party');

    expect($selector->effectiveScore($memberRequest))->toBe(2 + (int) round($trust * 10))->and((int) round($trust * 10))->toBeGreaterThan(0)
        ->and($selector->effectiveScore($systemRequest))->toBe(2);

    app(PartyMods::class)->disable($party, TrustMod::CODE);

    expect($selector->effectiveScore($memberRequest->refresh()->load('party')))->toBe(2)
        ->and(trustScore($party, $users['a']))->toBe(0.0);
});

it('prints a sorted score table from the command', function () {
    [$party, $users] = trustParty();
    trustEndorseAll($party, $users);

    $this->artisan('party:calculatetrust', ['party' => $party->code])
        ->expectsTable(['Nickname', 'Score'], collect(app(TrustScores::class)->recalculate($party))
            ->sortDesc()
            ->map(fn (float $score, int $id): array => [User::find($id)?->nickname, $score])
            ->values()->all())
        ->assertSuccessful();
});

it('fails the command for an unknown party code', function () {
    $this->artisan('party:calculatetrust', ['party' => 'NOPE'])
        ->expectsOutputToContain('Unable to find a party with code NOPE')
        ->assertFailed();
});
