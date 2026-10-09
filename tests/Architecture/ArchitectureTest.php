<?php

use App\Models\Song;
use App\Models\User;
use Tests\Architecture\Support\ArchitectureRules;
use Tests\Fixtures\Architecture\CrossContextWriter;
use Tests\Fixtures\Architecture\LeakyBroadcastEvent;
use Tests\Fixtures\Architecture\SerialisingBroadcastEvent;

$fixtureContexts = [
    'identity' => ['members' => [CrossContextWriter::class], 'models' => [User::class]],
    'music' => ['members' => [], 'models' => [Song::class]],
];

it('keeps model writes inside their bounded context', function () {
    expect(ArchitectureRules::crossContextWriteViolations(ArchitectureRules::appClasses()))->toBe([]);
});

it('detects cross-context model writes in a violating fixture', function () use ($fixtureContexts) {
    $violations = ArchitectureRules::crossContextWriteViolations([CrossContextWriter::class], $fixtureContexts);

    expect($violations)->toHaveKey(CrossContextWriter::class);
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
