<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');
    $this->party = Party::factory()->create(['code' => 'ABCD']);
});

it('refuses the legacy private party channels even for the host', function (string $channel) {
    $host = PartyMember::factory()->for($this->party)->host()->create();

    $this->actingAs($host->user)->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => $channel,
    ])->assertForbidden();
})->with([
    'private party' => 'private-party.ABCD',
    'owner' => 'private-party.ABCD.owner',
    'spotify token' => 'private-spotifytoken.1',
]);

it('schedules only the v3 jobs and sanctum prune', function () {
    $schedule = app(Schedule::class);
    Artisan::call('list');

    $descriptions = collect($schedule->events())
        ->map(fn ($event) => $event->description ?: $event->command)
        ->all();

    expect($descriptions)->toHaveCount(6)
        ->and(implode(' ', $descriptions))->toContain('sanctum:prune-expired')
        ->and(implode(' ', $descriptions))->not->toContain('party:');
});
