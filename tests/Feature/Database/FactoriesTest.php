<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\LinkedAccount;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\PartyModeration;
use App\Models\PartyModSetting;
use App\Models\PlayedSong;
use App\Models\SocialProvider;
use App\Models\Song;
use App\Models\SongRating;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\PartyMemberRoleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use SpotifyWebAPI\SpotifyWebAPI;
use Tests\Support\FakeSpotify;

it('creates a valid record from every model factory', function (string $model) {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($model::query()->count())->toBeGreaterThanOrEqual(1);
})->with([
    Party::class, PartyMember::class, User::class, LinkedAccount::class, SocialProvider::class,
    Song::class, Album::class, Artist::class, UpcomingSong::class, PlayedSong::class, Vote::class,
    SongRating::class, PartyModeration::class, Mod::class, ModSetting::class, PartyModSetting::class,
    PartyMemberRole::class,
]);

it('creates party members with each role state', function (string $state, string $code) {
    $member = PartyMember::factory()->{$state}()->create();

    expect($member->role->code)->toBe($code);
})->with([
    'owner' => ['owner', 'owner'],
    'vip' => ['vip', 'vip'],
    'user' => ['user', 'user'],
    'banned' => ['banned', 'banned'],
]);

it('rejects a second member row for the same party and user', function () {
    $member = PartyMember::factory()->create();

    expect(fn () => PartyMember::factory()->create(['party_id' => $member->party_id, 'user_id' => $member->user_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('creates one member row however often a user first visits a party', function () {
    $this->seed(PartyMemberRoleSeeder::class);
    $party = Party::factory()->create();
    $user = User::factory()->create();

    $first = $party->getMember($user);
    $second = $party->getMember($user);

    expect($second->id)->toBe($first->id)
        ->and($party->members()->whereUserId($user->id)->count())->toBe(1);
});

it('has no banned or canvote columns on party members', function () {
    expect(Schema::hasColumns('party_members', ['banned']))->toBeFalse()
        ->and(Schema::hasColumns('party_members', ['canvote']))->toBeFalse();
});

it('reuses one role row across members', function () {
    PartyMember::factory()->count(2)->vip()->create();

    expect(PartyMemberRole::query()->whereCode('vip')->count())->toBe(1);
});

it('creates role factory states', function (string $state, string $code) {
    expect(PartyMemberRole::factory()->{$state}()->create()->code)->toBe($code);
})->with([
    'owner' => ['owner', 'owner'],
    'vip' => ['vip', 'vip'],
    'user' => ['user', 'user'],
    'banned' => ['banned', 'banned'],
]);

it('creates requests in waiting and sent to player states', function () {
    expect(UpcomingSong::factory()->waiting()->create()->queued_at)->toBeNull()
        ->and(UpcomingSong::factory()->sentToPlayer()->create()->queued_at)->not->toBeNull();
});

it('creates up and down votes', function () {
    expect(Vote::factory()->up()->create()->value)->toBe(1)
        ->and(Vote::factory()->down()->create()->value)->toBe(-1);
});

it('links a song to its artist', function () {
    $song = Song::factory()->create();

    expect($song->artists)->toHaveCount(1);
});

it('creates a party against the bound spotify fake without any http request', function () {
    Http::fake();
    $fake = FakeSpotify::install();

    Party::factory()->create();

    expect(app(SpotifyWebAPI::class))->toBe($fake);
    Http::assertNothingSent();
});
