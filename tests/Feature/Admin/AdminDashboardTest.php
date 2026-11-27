<?php

use App\Enums\AiReviewOutcome;
use App\Enums\Purpose;
use App\Enums\SettingType;
use App\Models\AiReview;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\ProviderSetting;
use App\Models\Role;
use App\Models\SocialProvider;
use App\Models\Song;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Dashboard\ReverbStatus;
use App\Services\Dashboard\SystemHealth;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\MusicProviderFactory;
use App\Services\Music\Spotify\SpotifyMusicProvider;
use App\Services\PlaybackStatus;
use App\Services\Spotify\SpotifyGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

beforeEach(function () {
    $this->withoutVite();
    app(MusicProviderFactory::class)->register('spotify', fn (User $user, Purpose $purpose) => new SpotifyMusicProvider(app(SpotifyGateway::class), $user, $purpose));
    $role = Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
    $this->admin = User::factory()->create(['first_login' => false, 'nickname' => 'Jess']);
    $this->admin->roles()->attach($role);

    $this->guests = [];
    $this->reverb = $this->mock(ReverbStatus::class);
    $this->reverb->shouldReceive('reachable')->andReturn(true)->byDefault();
    $this->reverb->shouldReceive('subscribers')->andReturnUsing(fn (string $channel) => $this->guests[$channel] ?? 0)->byDefault();

    $this->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([(object) ['status' => 'running']])->byDefault();
    $this->mock(SupervisorRepository::class)->shouldReceive('all')->andReturn([(object) ['processes' => ['redis:default' => 3, 'redis:partyupdates' => 1]]])->byDefault();
    $this->mock(WorkloadRepository::class)->shouldReceive('get')->andReturn([['name' => 'default', 'length' => 2]])->byDefault();
    Redis::shouldReceive('connection->command')->andReturn(['used_memory_human' => '41M'])->byDefault();
    Cache::put(SystemHealth::SCHEDULER_HEARTBEAT_KEY, now()->timestamp);

    $spotify = SocialProvider::factory()->create(['code' => 'spotify', 'name' => 'Spotify', 'enabled' => true]);
    foreach (['client_id', 'client_secret'] as $code) {
        ProviderSetting::query()->forceCreate([
            'provider_type' => $spotify->getMorphClass(), 'provider_id' => $spotify->id, 'code' => $code, 'name' => $code,
            'type' => SettingType::stString, 'value' => 'set', 'order' => 1,
        ]);
    }
    $this->spotify = $spotify;
});

function dashboardPage(): TestResponse
{
    return test()->actingAs(test()->admin)->get(route('admin.dashboard'));
}

function liveParty(array $attributes = []): Party
{
    return Party::factory()->create($attributes + ['active' => true, 'playback_received_at' => now()->subSeconds(5), 'playback_source' => PlaybackSource::Polling]);
}

function playingSong(Party $party, bool $playing): void
{
    $song = Song::factory()->create(['name' => 'Midnight City']);
    $party->forceFill(['song_id' => $song->id])->save();
    app(PlaybackStatus::class)->remember($party, new PlaybackState($playing, $song->provider_track_id, null, 1000, 0, CarbonImmutable::now(), false, 'off', null, null, PlaybackSource::Polling));
}

it('keeps non-admins and guests out', function () {
    $this->get(route('admin.dashboard'))->assertRedirect();
    $this->actingAs(User::factory()->create(['first_login' => false]))->get(route('admin.dashboard'))->assertForbidden();
});

it('shows the all-clear and no-parties states', function () {
    liveParty(['active' => false, 'playback_received_at' => now()->subDays(3)]);

    dashboardPage()->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->where('liveParties', [])
        ->where('quietFor', '3 days')
        ->where('attention', [])
        ->where('health.0.detail', 'Horizon is running with 4 workers, 2 waiting jobs.')
        ->where('health', fn ($health) => collect($health)->pluck('status')->unique()->all() === ['ok'])
        ->where('links.horizon', route('horizon.index'))
        ->where('links.pulse', route('pulse')));
});

it('lists live parties with state, counts, guests and owner', function () {
    $playing = liveParty(['name' => 'Coyote LAN 26']);
    playingSong($playing, true);
    $paused = liveParty(['name' => 'Friday Night Disco']);
    playingSong($paused, false);
    $idle = liveParty(['name' => 'Quiet Corner', 'playback_received_at' => null, 'playback_source' => null]);
    liveParty(['name' => 'Offline', 'playback_received_at' => null, 'playback_source' => null]);
    $this->guests["party.{$playing->code}"] = 38;
    $this->guests["party.{$idle->code}"] = 2;

    UpcomingSong::factory()->count(2)->waiting()->create(['party_id' => $playing->id]);
    UpcomingSong::factory()->waiting()->create(['party_id' => $playing->id, 'flagged_at' => now()]);
    UpcomingSong::factory()->sentToPlayer()->create(['party_id' => $playing->id]);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->has('liveParties', 3)
        ->where('liveParties.0.name', 'Coyote LAN 26')
        ->where('liveParties.0.state', 'playing')
        ->where('liveParties.0.guestsOnline', 38)
        ->where('liveParties.0.requestsWaiting', 2)
        ->where('liveParties.0.flagged', 1)
        ->where('liveParties.0.owner', $playing->user->nickname)
        ->where('liveParties.0.track.name', 'Midnight City')
        ->where('liveParties.0.url', route('parties.dashboard', $playing->code))
        ->where('liveParties.1.state', 'paused')
        ->where('liveParties.2.name', 'Quiet Corner')
        ->where('liveParties.2.state', 'idle')
        ->where('liveParties.2.stateDetail', 'Nothing queued')
        ->where('liveParties.2.track', null));
});

it('flags a stale player as a party state and a Needs attention row', function () {
    $party = liveParty(['name' => 'Retro Room', 'playback_source' => PlaybackSource::WebPlayer, 'playback_received_at' => now()->subMinutes(6)]);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->where('liveParties.0.state', 'stale')
        ->where('liveParties.0.stateDetail', 'No report for 6 minutes')
        ->where('attention.0.key', "stale-{$party->code}")
        ->where('attention.0.severity', 'error')
        ->where('attention.0.title', 'Player stale in Retro Room')
        ->where('attention.0.action.url', route('parties.dashboard', $party->code)));
});

it('counts Spotify links needing re-link and names the party they block', function () {
    $party = liveParty(['name' => 'Retro Room']);
    LinkedAccount::query()->where('user_id', $party->user_id)->update(['needs_relink_at' => now()]);
    $other = User::factory()->withSpotify()->create(['nickname' => 'Sam']);
    LinkedAccount::query()->where('user_id', $other->id)->update(['needs_relink_at' => now()]);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->where('liveParties.0.state', 'error')
        ->where('liveParties.0.stateLabel', 'Re-link needed')
        ->where('attention.0.key', 'relink')
        ->where('attention.0.severity', 'error')
        ->where('attention.0.title', '2 Spotify links need re-linking')
        ->where('attention.0.action.url', route('admin.users.index', ['spotify_relink' => 1])));
});

it('reports failed jobs from the last hour only', function () {
    $job = fn (string $uuid, $at) => DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'redis', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncParty']), 'exception' => 'x', 'failed_at' => $at]);
    $job('a', now()->subMinutes(5));
    $job('b', now()->subMinutes(20));
    $job('c', now()->subHours(3));

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->where('attention.0.key', 'failed-jobs')
        ->where('attention.0.title', '2 jobs failed in the last hour')
        ->where('attention.0.detail', fn (string $detail) => str_starts_with($detail, 'All 2 are SyncParty.'))
        ->where('attention.0.action.url', route('horizon.index', ['view' => 'failed'])));
});

it('reports AI reviews that failed open in the last hour', function () {
    $party = Party::factory()->create(['name' => 'Coyote LAN 26']);
    AiReview::factory()->count(2)->outcome(AiReviewOutcome::Error)->create(['party_id' => $party->id, 'created_at' => now()->subMinutes(11)]);
    AiReview::factory()->outcome(AiReviewOutcome::Error)->create(['party_id' => $party->id, 'created_at' => now()->subHours(2)]);
    AiReview::factory()->outcome(AiReviewOutcome::Allow)->create(['party_id' => $party->id]);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->where('attention.0.key', 'ai-errors')
        ->where('attention.0.title', '2 AI reviews let a Request through by mistake')
        ->where('attention.0.action.url', route('parties.ai-reviews.index', $party->code)));
});

it('reports an enabled social provider that lacks a required setting', function () {
    $steam = SocialProvider::factory()->create(['code' => 'steam', 'name' => 'Steam', 'enabled' => true, 'auth_enabled' => true]);
    ProviderSetting::query()->forceCreate([
        'provider_type' => $steam->getMorphClass(), 'provider_id' => $steam->id, 'code' => 'client_secret', 'name' => 'Client Secret',
        'type' => SettingType::stString, 'validation' => 'required|string', 'value' => null, 'order' => 1,
    ]);
    $off = SocialProvider::factory()->create(['code' => 'twitch', 'name' => 'Twitch', 'enabled' => false]);
    ProviderSetting::query()->forceCreate([
        'provider_type' => $off->getMorphClass(), 'provider_id' => $off->id, 'code' => 'client_secret', 'name' => 'Client Secret',
        'type' => SettingType::stString, 'validation' => 'required|string', 'value' => null, 'order' => 1,
    ]);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->has('attention', 1)
        ->where('attention.0.title', 'Steam sign-in is on but has no client secret')
        ->where('attention.0.action.url', route('admin.settings.socialproviders.edit', $steam)));
});

it('degrades each system health row when its check fails', function () {
    $this->mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([]);
    Cache::put(SystemHealth::SCHEDULER_HEARTBEAT_KEY, now()->subMinutes(10)->timestamp);
    $this->reverb->shouldReceive('reachable')->andReturn(false);
    Redis::shouldReceive('connection->command')->andThrow(new RuntimeException('down'));
    ProviderSetting::query()->where('provider_id', $this->spotify->id)->where('code', 'client_secret')->update(['value' => '']);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->where('health', fn ($health) => collect($health)->pluck('status')->all() === ['error', 'error', 'error', 'error', 'ok', 'error'])
        ->where('health.0.detail', 'Horizon is not running, so queued jobs are not being processed.')
        ->where('health.1.detail', 'Last ran 10 minutes ago. Check that the scheduler is running.')
        ->where('health.5.detail', 'Missing client secret, so nobody can link Spotify.'));
});

it('summarises activity from real records', function () {
    $user = User::factory()->create(['nickname' => 'Priya', 'created_at' => now()->subMinutes(4)]);
    $party = Party::factory()->create(['name' => 'Retro Room', 'created_at' => now()->subMinutes(30)]);
    User::query()->whereKey($this->admin->id)->update(['created_at' => now()->subDays(30)]);

    dashboardPage()->assertInertia(fn (Assert $page) => $page
        ->where('activity.stats.signUpsThisWeek', User::query()->where('created_at', '>=', now()->subWeek())->count())
        ->where('activity.stats.partiesThisWeek', 1)
        ->where('activity.stats.playedToday', 0)
        ->where('activity.recent', fn ($recent) => collect($recent)->contains(['icon' => 'ti-user-plus', 'text' => 'Priya signed up.', 'at' => $user->created_at->toIso8601String()])
            && collect($recent)->contains(fn (array $event) => $event['text'] === $party->user->nickname.' created Retro Room.')));
});
