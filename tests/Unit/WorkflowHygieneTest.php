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

it('scans before pushing, then attests every platform image', function () {
    $steps = DeployConfig::workflow(WORKFLOWS[1])->toArray()['jobs']['build']['steps'];
    $order = array_map(fn (array $step): string => explode('@', $step['uses'] ?? '')[0], $steps);

    $scan = array_search('aquasecurity/trivy-action', $order, true);
    $push = array_keys(array_filter($steps, fn (array $step): bool => ($step['id'] ?? '') === 'build-and-push'))[0];

    expect($scan)->toBeInt()->toBeLessThan($push);

    $scanWith = $steps[$scan]['with'];
    $pushWith = $steps[$push]['with'];

    expect($scanWith['severity'])->toBe('CRITICAL')
        ->and($scanWith['exit-code'])->toBe('1')
        ->and($pushWith['sbom'])->toBeTrue()
        ->and($pushWith['provenance'])->toBe('mode=max');
});

it('builds each architecture natively in parallel and pushes by digest', function () {
    $build = DeployConfig::workflow(WORKFLOWS[1])->toArray()['jobs']['build'];
    $include = collect($build['strategy']['matrix']['include'])->keyBy('platform');

    expect($include->keys()->sort()->values()->all())->toBe(['linux/amd64', 'linux/arm64'])
        ->and($include['linux/amd64']['runner'])->toBe('ubuntu-latest')
        ->and($include['linux/arm64']['runner'])->toBe('ubuntu-24.04-arm')
        ->and($build['runs-on'])->toBe('${{ matrix.runner }}')
        ->and($build['steps'])->not->toContain(fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'docker/setup-qemu-action@'));

    $push = collect($build['steps'])->firstWhere('id', 'build-and-push')['with'];

    expect($push['platforms'])->toBe('${{ matrix.platform }}')
        ->and($push['outputs'])->toContain('push-by-digest=true')
        ->and($push['outputs'])->toContain('push=true')
        ->and($push)->not->toHaveKey('tags');
});

it('merges the digests into one tagged manifest after every build', function () {
    $merge = DeployConfig::workflow(WORKFLOWS[1])->toArray()['jobs']['merge'];
    $meta = collect($merge['steps'])->firstWhere('id', 'meta')['with']['tags'];
    $commands = collect($merge['steps'])->pluck('run')->filter()->implode("\n");

    expect($merge['needs'])->toBe('build')
        ->and($meta)->toContain('sha-${{ env.PUBLISH_SHA }}')
        ->and($commands)->toContain('docker buildx imagetools create');
});

it('skips CI, and so publishing, for documentation-only pushes', function () {
    $workflow = DeployConfig::workflow(WORKFLOWS[0])->toArray();
    $trigger = $workflow['on'] ?? $workflow[true];

    expect($trigger['push']['paths-ignore'])->toBe(['docs/**', 'openspec/**', 'tests/**', '*.md'])
        ->and($trigger['pull_request'] ?? null)->toBeNull();
});
