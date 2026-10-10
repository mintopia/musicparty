<?php

use App\Support\Realtime\AsyncApiCoverage;
use Tests\Fixtures\UndocumentedBroadcastEvent;

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
