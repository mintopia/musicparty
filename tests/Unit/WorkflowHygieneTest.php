<?php

declare(strict_types=1);

use Tests\Support\DeployConfig;

const WORKFLOWS = ['.github/workflows/ci.yml', '.github/workflows/publish-docker-images.yml'];

/**
 * @return list<string>
 */
function workflowUses(mixed $node): array
{
    $uses = [];

    foreach ((array) $node as $key => $value) {
        if ($key === 'uses' && is_string($value)) {
            $uses[] = $value;
        } elseif (is_array($value)) {
            $uses = [...$uses, ...workflowUses($value)];
        }
    }

    return $uses;
}

it('pins every action to a 40-character commit SHA', function (string $path) {
    $uses = workflowUses(DeployConfig::workflow($path)->toArray());

    expect($uses)->not->toBeEmpty();

    foreach ($uses as $reference) {
        expect($reference)->toMatch('/^[\w.\-]+\/[\w.\-\/]+@[0-9a-f]{40}$/');
    }
})->with(WORKFLOWS);

it('declares permissions at the top level', function (string $path) {
    expect(DeployConfig::workflow($path)->toArray())->toHaveKey('permissions');
})->with(WORKFLOWS);

it('never requests id-token write', function (string $path) {
    expect(json_encode(DeployConfig::workflow($path)->toArray()))->not->toContain('id-token');
})->with(WORKFLOWS);

it('limits CI to read-only contents', function () {
    expect(DeployConfig::workflow(WORKFLOWS[0])->toArray()['permissions'])->toBe(['contents' => 'read']);
});

it('publishes only after CI succeeds on the same commit', function () {
    $workflow = DeployConfig::workflow(WORKFLOWS[1])->toArray();
    $trigger = $workflow['on'] ?? $workflow[true];

    expect($trigger['workflow_run']['workflows'])->toBe(['CI'])
        ->and($trigger['workflow_run']['types'])->toBe(['completed'])
        ->and($trigger)->not->toHaveKey('push')
        ->and($workflow['jobs']['build']['if'])->toContain("workflow_run.conclusion == 'success'")
        ->and($workflow['env']['PUBLISH_SHA'])->toContain('workflow_run.head_sha');

    $checkout = collect($workflow['jobs']['build']['steps'])->first(fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'actions/checkout@'));

    expect($checkout['with']['ref'])->toBe('${{ env.PUBLISH_SHA }}');
});

it('scans before pushing, then attests and tags by commit', function () {
    $steps = DeployConfig::workflow(WORKFLOWS[1])->toArray()['jobs']['build']['steps'];
    $order = array_map(fn (array $step): string => explode('@', $step['uses'] ?? '')[0], $steps);

    $scan = array_search('aquasecurity/trivy-action', $order, true);
    $push = array_keys(array_filter($steps, fn (array $step): bool => ($step['id'] ?? '') === 'build-and-push'))[0];

    expect($scan)->toBeInt()->toBeLessThan($push);

    $scanWith = $steps[$scan]['with'];
    $pushWith = $steps[$push]['with'];
    $meta = collect($steps)->firstWhere('id', 'meta')['with']['tags'];

    expect($scanWith['severity'])->toBe('CRITICAL')
        ->and($scanWith['exit-code'])->toBe('1')
        ->and($pushWith['sbom'])->toBeTrue()
        ->and($pushWith['provenance'])->toBe('mode=max')
        ->and($meta)->toContain('sha-${{ env.PUBLISH_SHA }}');
});
