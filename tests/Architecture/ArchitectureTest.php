<?php

use App\Domain\Party\Models\Party;
use App\Domain\Playback\Listeners\HandlePlayerClientEvent;
use Illuminate\Database\Eloquent\Model;
use Tests\Architecture\Support\ArchitectureRules;
use Tests\Fixtures\Architecture\Actions\RoleCheckingAction;
use Tests\Fixtures\Architecture\CrossContextWriter;
use Tests\Fixtures\Architecture\LeakyBroadcastEvent;
use Tests\Fixtures\Architecture\ReverbListenerLeaker;
use Tests\Fixtures\Architecture\SerialisingBroadcastEvent;
use Tests\Fixtures\Architecture\UnnamedBroadcastEvent;

$fixtureContexts = ArchitectureRules::contextsWithMembers('identity', [CrossContextWriter::class]);

it('maps all ten contexts and assigns every model to exactly one', function () {
    $contexts = ArchitectureRules::contexts();
    $models = array_filter(ArchitectureRules::appClasses(), fn (string $class): bool => str_contains($class, '\\Models\\') && is_subclass_of($class, Model::class));
    $owned = array_merge(...array_column($contexts, 'models'));

    expect(array_keys($contexts))->toBe(['admin', 'identity', 'membership', 'mod', 'music', 'party', 'playback', 'queue', 'stats', 'theming'])
        ->and(array_values($models))->each->toBeIn($owned)
        ->and(count($owned))->toBe(count(array_unique($owned)));

    foreach ($contexts as $definition) {
        foreach ($definition['models'] as $model) {
            expect(str_starts_with($model, $definition['members'][0]))->toBeTrue();
        }
    }
});

it('keeps model writes inside their bounded context', function () {
    expect(ArchitectureRules::crossContextWriteViolations(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects cross-context model writes in a violating fixture', function () use ($fixtureContexts) {
    $violations = ArchitectureRules::crossContextWriteViolations([CrossContextWriter::class], $fixtureContexts);

    expect($violations)->toHaveKey(CrossContextWriter::class);
});

it('gives every broadcast event an explicit wire name', function () {
    expect(ArchitectureRules::broadcastEvents(ArchitectureRules::appClasses()))->not->toBeEmpty()
        ->and(ArchitectureRules::missingBroadcastAs(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects a broadcast event without broadcastAs in a violating fixture', function () {
    expect(ArchitectureRules::missingBroadcastAs([UnnamedBroadcastEvent::class]))->toBe([UnnamedBroadcastEvent::class]);
});

it('never serialises models in broadcast events', function () {
    expect(ArchitectureRules::broadcastEvents(ArchitectureRules::appClasses()))->not->toBeEmpty()
        ->and(ArchitectureRules::modelSerialisationViolations(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects model serialisation in a violating fixture', function () {
    $violations = ArchitectureRules::modelSerialisationViolations([SerialisingBroadcastEvent::class]);

    expect($violations)->toHaveKey(SerialisingBroadcastEvent::class)
        ->and($violations[SerialisingBroadcastEvent::class])->toContain('uses SerializesModels', 'missing broadcastWith()');
});

it('never puts provider tokens or personal data in broadcast payloads', function () {
    expect(ArchitectureRules::forbiddenPayloadKeyViolations(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects forbidden payload keys in a violating fixture', function () {
    $violations = ArchitectureRules::forbiddenPayloadKeyViolations([LeakyBroadcastEvent::class]);

    expect($violations[LeakyBroadcastEvent::class] ?? [])->toContain('access_token', 'email');
});

it('keeps Reverb message listeners free of domain, model and action imports', function () {
    expect(ArchitectureRules::reverbMessageListeners(ArchitectureRules::appClasses()))->toContain(HandlePlayerClientEvent::class)
        ->and(ArchitectureRules::listenerDomainViolations(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects domain access in a violating Reverb listener fixture', function () {
    $violations = ArchitectureRules::listenerDomainViolations([ReverbListenerLeaker::class]);

    expect($violations)->toHaveKey(ReverbListenerLeaker::class)
        ->and($violations[ReverbListenerLeaker::class])->toContain(Party::class);
});

it('leaves party role authorisation to the Policies', function () {
    expect(ArchitectureRules::partyRoleAuthorisationViolations(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects a party role comparison in a violating Action fixture', function () {
    expect(ArchitectureRules::partyRoleAuthorisationViolations([RoleCheckingAction::class]))->toBe([RoleCheckingAction::class]);
});

it('keeps no classes under the removed legacy directories', function (string $directory) {
    expect(glob(dirname(__DIR__, 2).'/app/'.$directory.'/*'))->toBe([]);
})->with(['Models', 'Events', 'Jobs', 'Listeners', 'Services']);

arch('the Music context does not depend on the Playback context')
    ->expect('App\Domain\Music')
    ->not->toUse('App\Domain\Playback');
