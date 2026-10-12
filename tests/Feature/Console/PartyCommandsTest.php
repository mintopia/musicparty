<?php

use App\Domain\Party\Broadcast\PartyStateChangedEvent;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Jobs\TickParty;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

beforeEach(fn () => CarbonImmutable::setTestNow('2026-01-01 12:00:00'));

afterEach(fn () => CarbonImmutable::setTestNow());

it('registers the operator commands and no party:force', function () {
    $names = array_keys(Artisan::all());

    expect($names)->toContain('party:check', 'party:fallback', 'party:updatestate', 'party:pushupdate', 'party:refreshaccesstokens')
        ->not->toContain('party:force');
});

it('fails with a non-zero exit for an unknown code', function (string $command) {
    $this->artisan($command, ['code' => 'ZZZZ'])
        ->expectsOutputToContain('No Party found with code ZZZZ.')
        ->assertFailed();
})->with(['party:check', 'party:updatestate', 'party:pushupdate']);

it('checks a party and reports health, host account and playable tracks', function () {
    $party = livePlaybackParty();
    useFakePlayer($party);

    $this->artisan('party:check', ['code' => strtolower($party->code)])
        ->expectsOutputToContain("Party {$party->code}")
        ->expectsOutputToContain('Player health: n/a')
        ->expectsOutputToContain('Host account: not linked')
        ->expectsOutputToContain('Playable Fallback Tracks: 0 of 20')
        ->assertSuccessful();
});

it('checks every live party when no code is given', function () {
    $first = livePlaybackParty();
    $second = Party::factory()->live()->create(['fallback_playlist_id' => 'pl']);
    $paused = Party::factory()->create();

    $this->artisan('party:check')
        ->expectsOutputToContain("Party {$first->code}")
        ->expectsOutputToContain("Party {$second->code}")
        ->doesntExpectOutputToContain("Party {$paused->code}")
        ->assertSuccessful();
});

it('mirrors info log lines under -v only', function () {
    $party = livePlaybackParty();

    $this->artisan('party:check', ['code' => $party->code, '-v' => true])
        ->expectsOutputToContain("[info] Checking Party {$party->code}.")
        ->assertSuccessful();

    $this->artisan('party:check', ['code' => $party->code])
        ->doesntExpectOutputToContain('[info]')
        ->assertSuccessful();
});

it('stops mirroring once the command finishes and never mirrors other commands', function () {
    Artisan::command('test:after-mirror', function () {
        Log::info('after');
    });
    $party = livePlaybackParty();
    Artisan::call('party:check', ['code' => $party->code, '-v' => true], new BufferedOutput);

    $output = new BufferedOutput;
    Artisan::call('test:after-mirror', [], $output);

    expect($output->fetch())->toBe('');
});

it('ticks only live parties whose last tick is over 90 seconds old', function () {
    Queue::fake();
    $never = Party::factory()->live()->create();
    $stale = Party::factory()->live()->create();
    $fresh = Party::factory()->live()->create();
    $noPlayer = Party::factory()->live()->create(['player_kind' => null]);
    Party::factory()->create();
    Cache::put("party:last-tick:{$stale->code}", now()->subSeconds(91)->getTimestamp());
    Cache::put("party:last-tick:{$fresh->code}", now()->subSeconds(89)->getTimestamp());

    $this->artisan('party:fallback')->expectsOutputToContain('Ticked 2 stale Live Parties.')->assertSuccessful();

    Queue::assertPushedTimes(TickParty::class, 2);
    Queue::assertPushed(TickParty::class, fn (TickParty $job): bool => in_array($job->partyCode, [$never->code, $stale->code], true));
    Queue::assertNotPushed(TickParty::class, fn (TickParty $job): bool => in_array($job->partyCode, [$fresh->code, $noPlayer->code], true));
});

it('runs one tick synchronously and records when it happened', function () {
    $party = livePlaybackParty();
    useFakePlayer($party);

    $this->artisan('party:updatestate', ['code' => strtolower($party->code)])
        ->expectsOutputToContain("Ticked Party {$party->code}.")
        ->assertSuccessful();

    expect(TickParty::lastTickedAt($party->code)?->equalTo(now()))->toBeTrue();
});

it('re-broadcasts the party state and queue', function () {
    Queue::fake();
    Event::fake([PartyStateChangedEvent::class]);
    $party = Party::factory()->live()->create();

    $this->artisan('party:pushupdate', ['code' => $party->code])->assertSuccessful();

    Event::assertDispatched(PartyStateChangedEvent::class);
    Queue::assertPushed(BroadcastPartyQueue::class, fn (BroadcastPartyQueue $job): bool => $job->partyCode === $party->code);
});

it('refreshes expiring host tokens once', function () {
    $this->artisan('party:refreshaccesstokens')
        ->expectsOutputToContain('Expiring Host access tokens refreshed.')
        ->assertSuccessful();
});
