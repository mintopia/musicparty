<?php

use App\Domain\Membership\Broadcast\MemberBannedEvent;
use App\Domain\Party\Broadcast\PartyLogEntryAddedEvent;
use App\Domain\Party\Broadcast\PartyStateChangedEvent;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Broadcast\BrowserPlayerCommandEvent;
use App\Domain\Playback\Broadcast\PlayerCommandEvent;
use App\Domain\Queue\Broadcast\MemberRatingChangedEvent;
use App\Domain\Queue\Broadcast\MemberVoteChangedEvent;
use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Domain\Queue\Broadcast\PendingRequestAddedEvent;
use App\Domain\Queue\Broadcast\PendingRequestResolvedEvent;
use App\Domain\Queue\Broadcast\QueueUpdatedEvent;
use App\Domain\Queue\Broadcast\RequestDecidedEvent;
use App\Domain\Queue\Broadcast\RequestRejectedEvent;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Stats\Actions\ComputePartyStats;
use App\Domain\Stats\Broadcast\StatsUpdatedEvent;
use App\Domain\Theming\Broadcast\ThemeUpdatedEvent;
use App\Support\Realtime\AsyncApiCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\UndocumentedBroadcastEvent;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function asyncApiSpec(): array
{
    return json_decode((string) file_get_contents(base_path('asyncapi/asyncapi.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('documents every broadcast event under app/Domain', function () {
    $classes = AsyncApiCoverage::discoverBroadcastEvents(app_path('Domain'), 'App\\Domain');

    expect($classes)->not->toBeEmpty()
        ->and(AsyncApiCoverage::undocumented($classes, asyncApiSpec()))->toBe([]);
});

it('is a valid AsyncAPI 3 skeleton referencing existing messages', function () {
    $spec = asyncApiSpec();

    expect($spec['asyncapi'])->toStartWith('3.')
        ->and($spec['channels'])->toHaveKey('party')
        ->and($spec['channels']['party']['address'])->toBe('party.{code}');

    foreach ($spec['channels'] as $channel) {
        foreach (array_keys($channel['messages']) as $key) {
            expect($spec['components']['messages'])->toHaveKey($key);
        }
    }
});

it('reports a broadcast event missing from the spec', function () {
    $classes = [...AsyncApiCoverage::discoverBroadcastEvents(app_path('Domain'), 'App\\Domain'), UndocumentedBroadcastEvent::class];

    expect(AsyncApiCoverage::undocumented($classes, asyncApiSpec()))->toBe([UndocumentedBroadcastEvent::class]);
});

it('discovers broadcast events and ignores other classes', function () {
    $classes = AsyncApiCoverage::discoverBroadcastEvents(base_path('tests/Fixtures'), 'Tests\\Fixtures');

    expect($classes)->toContain(UndocumentedBroadcastEvent::class);
});

it('documents each broadcast event under its broadcastAs wire name', function () {
    $messages = array_filter(asyncApiSpec()['components']['messages'], fn (array $message): bool => isset($message['x-event-class']));

    expect($messages)->not->toBeEmpty();

    foreach ($messages as $message) {
        $event = new ReflectionClass($message['x-event-class'])->newInstanceWithoutConstructor();

        assert(method_exists($event, 'broadcastAs'));

        expect($message['name'])->toBe($event->broadcastAs());
    }
});

it('has a consumer for every documented broadcast event', function () {
    $externalConsumers = [
        'player.command',
        'browser-player.command',
    ];

    expect(AsyncApiCoverage::unconsumed(asyncApiSpec(), resource_path('js'), $externalConsumers))->toBe([]);
});

it('reports a documented event with no listener and no external consumer', function () {
    $spec = ['components' => ['messages' => [
        'Ghost' => ['name' => 'ghost.happened', 'x-event-class' => 'X'],
        'Heard' => ['name' => 'queue.updated', 'x-event-class' => 'Y'],
        'External' => ['name' => 'player.command', 'x-event-class' => 'Z'],
    ]]];

    expect(AsyncApiCoverage::unconsumed($spec, resource_path('js'), ['player.command']))->toBe(['ghost.happened']);
});

/**
 * @return array<class-string, callable(): object>
 */
function broadcastFixtures(): array
{
    $party = Party::factory()->live()->create(['code' => 'ABCD']);
    TrackRequest::factory()->for($party)->create(['status' => RequestStatus::Queued]);

    return [
        QueueUpdatedEvent::class => fn () => new QueueUpdatedEvent('ABCD', app(PartyQueueSnapshot::class)->build($party)),
        PartyStateChangedEvent::class => fn () => new PartyStateChangedEvent('ABCD', 'live'),
        ThemeUpdatedEvent::class => fn () => new ThemeUpdatedEvent('ABCD'),
        StatsUpdatedEvent::class => fn () => new StatsUpdatedEvent('ABCD', ComputePartyStats::empty()),
        RequestRejectedEvent::class => fn () => new RequestRejectedEvent('ABCD', 1, 'track-1', 'blocked'),
        RequestDecidedEvent::class => fn () => new RequestDecidedEvent('ABCD', 1, 2, 'queued', null),
        MemberVoteChangedEvent::class => fn () => new MemberVoteChangedEvent('ABCD', 1, 2, 1),
        MemberRatingChangedEvent::class => fn () => new MemberRatingChangedEvent('ABCD', 1, 2, 1),
        MemberBannedEvent::class => fn () => new MemberBannedEvent('ABCD', 1),
        PartyLogEntryAddedEvent::class => fn () => new PartyLogEntryAddedEvent('ABCD', 1, 'party.updated', null),
        PendingRequestAddedEvent::class => fn () => new PendingRequestAddedEvent('ABCD', 2, 'Title', ['Artist'], 1),
        PendingRequestResolvedEvent::class => fn () => new PendingRequestResolvedEvent('ABCD', 2, 'approved'),
        PlayerCommandEvent::class => fn () => new PlayerCommandEvent('ABCD', ['cmd' => 'play']),
        BrowserPlayerCommandEvent::class => fn () => new BrowserPlayerCommandEvent('ABCD', 'spotify', 'track-1'),
    ];
}

it('documents every channel registered in routes/channels.php', function () {
    $patterns = AsyncApiCoverage::registeredChannelPatterns();

    expect($patterns)->not->toBeEmpty()
        ->and(AsyncApiCoverage::undocumentedChannels($patterns, asyncApiSpec()))->toBe([]);
});

it('reports a registered channel missing from the spec', function () {
    $patterns = [...AsyncApiCoverage::registeredChannelPatterns(), 'fixture.{id}'];

    expect(AsyncApiCoverage::undocumentedChannels($patterns, asyncApiSpec()))->toBe(['fixture.{id}']);
});

it('validates each documented payload against the payload its serializer produces', function () {
    expect(AsyncApiCoverage::payloadViolations(asyncApiSpec(), broadcastFixtures()))->toBe([]);
});

it('has a payload fixture for every broadcast event', function () {
    $classes = AsyncApiCoverage::discoverBroadcastEvents(app_path('Domain'), 'App\\Domain');

    expect(array_diff($classes, array_keys(broadcastFixtures())))->toBe([]);
});

it('fails when a payload field changes without the spec', function () {
    $fixtures = broadcastFixtures();
    $fixtures[PartyStateChangedEvent::class] = fn () => new class('ABCD', 'live') extends PartyStateChangedEvent
    {
        public function broadcastWith(): array
        {
            return ['state' => 'live', 'extra' => true];
        }
    };

    expect(AsyncApiCoverage::payloadViolations(asyncApiSpec(), $fixtures))
        ->toBe(['party.state_changed: $: undocumented property extra']);
});

it('fails when a documented field is no longer produced', function () {
    $spec = asyncApiSpec();
    $spec['components']['messages']['Party.MemberBannedEvent']['payload']['required'][] = 'banned_at';
    $spec['components']['messages']['Party.MemberBannedEvent']['payload']['properties']['banned_at'] = ['type' => 'string'];

    expect(AsyncApiCoverage::payloadViolations($spec, broadcastFixtures()))
        ->toBe(['member.banned: $: missing required property banned_at']);
});

it('fails when a documented field type changes', function () {
    $fixtures = broadcastFixtures();
    $fixtures[MemberBannedEvent::class] = fn () => new class
    {
        /**
         * @return array{member_id: string}
         */
        public function broadcastWith(): array
        {
            return ['member_id' => 'one'];
        }
    };

    expect(AsyncApiCoverage::payloadViolations(asyncApiSpec(), $fixtures))
        ->toBe(['member.banned: $.member_id: expected type "integer", got string']);
});
