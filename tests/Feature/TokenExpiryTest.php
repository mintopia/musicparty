<?php

use App\Domain\Admin\Actions\EndExpiredActAsHostSessions;
use App\Domain\Admin\Actions\EnterActAsHost;
use App\Domain\Admin\Actions\IssueIntegrationToken;
use App\Domain\Admin\Jobs\SweepExpiredActAsHostSessions;
use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Admin\Models\Role;
use App\Domain\Identity\Models\AccessToken;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Events\PartyLogEntryRecorded;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyLogEntry;
use App\Domain\Playback\Actions\IssuePlayerToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([PartyLogEntryRecorded::class]);
    $this->travelTo(now()->startOfSecond());
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's', 'broadcasting.connections.reverb.app_id' => '1']);
    require base_path('routes/channels.php');
    $this->host = User::factory()->create();
    $this->party = Party::factory()->create(['user_id' => $this->host->id, 'music_provider' => 'fake', 'player_kind' => 'fake']);
});

function expiryAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::query()->firstOrCreate(['code' => 'admin'], ['name' => 'Admin']));

    return $user->fresh();
}

function issuePlayerPlainText(): string
{
    return app(IssuePlayerToken::class)(test()->host, test()->party, 'Stage')->plainTextToken;
}

it('sets the player token expiry from config', function () {
    issuePlayerPlainText();

    expect(PersonalAccessToken::query()->firstOrFail()->expires_at?->equalTo(now()->addDays(30)))->toBeTrue();

    config(['musicparty.tokens.player_ttl_days' => 7]);
    issuePlayerPlainText();

    expect(PersonalAccessToken::query()->latest('id')->firstOrFail()->expires_at?->equalTo(now()->addDays(7)))->toBeTrue();
});

it('gets 401 and no channel access from an expired player token', function () {
    $plain = issuePlayerPlainText();
    $poll = fn () => $this->withToken($plain)->postJson("/api/v1/parties/{$this->party->code}/player/poll");
    $channel = fn () => $this->withToken($plain)->postJson('/broadcasting/auth', ['channel_name' => 'private-player.'.$this->party->code, 'socket_id' => '1234.5678']);

    $channel()->assertOk();
    expect($poll()->status())->not->toBe(401);

    $this->travel(30)->days();
    $this->travel(1)->seconds();
    app('auth')->forgetGuards();

    $poll()->assertUnauthorized();
    app('auth')->forgetGuards();
    $channel()->assertForbidden();
});

it('sets no integration token expiry by default and one when configured', function () {
    $admin = expiryAdmin();

    $none = app(IssueIntegrationToken::class)->handle($admin, 'None', ['read']);
    expect($none['token']->tokens->firstOrFail()->expires_at)->toBeNull();

    config(['musicparty.tokens.integration_ttl_days' => 10]);
    $set = app(IssueIntegrationToken::class)->handle($admin, 'Set', ['read']);
    expect($set['token']->tokens->firstOrFail()->expires_at?->equalTo(now()->addDays(10)))->toBeTrue();

    $this->travel(11)->days();
    app('auth')->forgetGuards();
    $this->withToken($set['plainText'])->getJson('/api/v1/integration/ping')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($none['plainText'])->getJson('/api/v1/integration/ping')->assertOk();
});

it('uses the custom token model and writes last_used_at once for 100 calls in a minute', function () {
    $plain = issuePlayerPlainText();
    expect(Sanctum::$personalAccessTokenModel)->toBe(AccessToken::class);

    DB::enableQueryLog();
    foreach (range(1, 100) as $call) {
        app('auth')->forgetGuards();
        $this->withToken($plain)->postJson("/api/v1/parties/{$this->party->code}/player/poll");
        $this->travel(500)->milliseconds();
    }

    $writes = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_starts_with($q['query'], 'update "personal_access_tokens"'));
    expect($writes)->toHaveCount(1);

    $first = PersonalAccessToken::query()->firstOrFail()->last_used_at ?? now();
    $this->travel(61)->seconds();
    app('auth')->forgetGuards();
    $this->withToken($plain)->postJson("/api/v1/parties/{$this->party->code}/player/poll");

    expect(PersonalAccessToken::query()->firstOrFail()->last_used_at?->gt($first))->toBeTrue();
});

it('schedules the prune and the act-as-host sweep', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($e): bool => str_contains($e->command ?? '', 'sanctum:prune-expired') && $e->expression === '0 0 * * *'))->toBeTrue()
        ->and($events->contains(fn ($e): bool => str_contains($e->description ?? '', SweepExpiredActAsHostSessions::class) && $e->expression === '* * * * *'))->toBeTrue();
});

it('ends act-as-host powers at expiry and logs it through the sweep', function () {
    $admin = expiryAdmin();
    $member = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($member)->host()->create();
    app(EnterActAsHost::class)->handle($admin, $this->party);

    expect($this->party->canBeManagedBy($admin))->toBeTrue()
        ->and(AdminHostSession::query()->firstOrFail()->expires_at?->equalTo(now()->addMinutes(120)))->toBeTrue();

    $this->travel(119)->minutes();
    expect($this->party->canBeManagedBy($admin->fresh()))->toBeTrue();

    $this->travel(1)->minutes();
    expect($this->party->canBeManagedBy($admin->fresh()))->toBeFalse()
        ->and(PartyLogEntry::query()->where('action', 'act_as_host.expired')->count())->toBe(0);

    (new SweepExpiredActAsHostSessions)->handle(app(EndExpiredActAsHostSessions::class));
    (new SweepExpiredActAsHostSessions)->handle(app(EndExpiredActAsHostSessions::class));

    expect(AdminHostSession::query()->count())->toBe(0);

    Sanctum::actingAs($member);
    $this->getJson("/api/v1/parties/{$this->party->code}/log")->assertOk()
        ->assertJsonFragment(['action' => 'act_as_host.entered'])
        ->assertJsonFragment(['action' => 'act_as_host.expired'])
        ->assertJsonCount(2, 'data');
});

it('starts a fresh logged session when re-entering after expiry', function () {
    $admin = expiryAdmin();
    app(EnterActAsHost::class)->handle($admin, $this->party);
    $this->travel(121)->minutes();
    app(EnterActAsHost::class)->handle($admin, $this->party);

    expect($this->party->canBeManagedBy($admin->fresh()))->toBeTrue()
        ->and(AdminHostSession::query()->count())->toBe(1)
        ->and(PartyLogEntry::query()->where('action', 'act_as_host.entered')->count())->toBe(2);
});
