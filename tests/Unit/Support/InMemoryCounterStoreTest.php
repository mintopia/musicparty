<?php

use App\Support\Metrics\InMemoryCounterStore;

it('counts increments and reads unset keys as zero', function (): void {
    $store = new InMemoryCounterStore;
    $store->increment('a');
    $store->increment('a');

    expect($store->get('a'))->toBe(2)
        ->and($store->get('missing'))->toBe(0)
        ->and($store->many(['a', 'missing', 'a']))->toBe([2, 0, 2])
        ->and($store->many([]))->toBe([]);
});

it('keeps set members unique and as strings', function (): void {
    $store = new InMemoryCounterStore;
    $store->addMember('codes', 200);
    $store->addMember('codes', '200');
    $store->addMember('codes', 404);

    expect($store->members('codes'))->toBe(['200', '404'])
        ->and($store->members('none'))->toBe([]);
});
