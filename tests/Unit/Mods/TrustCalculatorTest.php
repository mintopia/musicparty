<?php

use App\Mods\Trust\TrustCalculator;

function assertSane(array $scores, array $members): void
{
    expect(array_keys($scores))->toEqualCanonicalizing($members);

    foreach ($scores as $score) {
        expect($score)->toBeFloat()->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
    }
}

it('scores the trusted user 1 and ranks endorsed members above unendorsed ones', function (): void {
    $scores = (new TrustCalculator)->calculate([1, 2, 3, 4], 1, [
        1 => [2 => 6, 3 => 1],
        2 => [3 => 2],
    ]);

    assertSane($scores, [1, 2, 3, 4]);
    expect($scores[1])->toBe(1.0)
        ->and($scores[2])->toBeGreaterThan($scores[4])
        ->and($scores[3])->toBeGreaterThan($scores[4])
        ->and($scores[4])->toBe(0.0);
});

it('ranks a member endorsed by the trusted user above one endorsed only by a low-trust member', function (): void {
    $scores = (new TrustCalculator)->calculate([1, 2, 3, 4, 5], 1, [
        1 => [2 => 3, 3 => 3],
        3 => [4 => 10],
        2 => [5 => 1],
    ]);

    expect($scores[2])->toBeGreaterThan($scores[4]);
});

it('does not let vote spamming grow a voter\'s influence beyond one normalised budget', function (): void {
    $calculator = new TrustCalculator;
    $base = [1 => [2 => 5], 2 => [3 => 1, 4 => 1]];
    $spam = [1 => [2 => 5], 2 => [3 => 1000, 4 => 1000]];

    expect($calculator->calculate([1, 2, 3, 4], 1, $spam))->toEqual($calculator->calculate([1, 2, 3, 4], 1, $base));
});

it('is inert for small parties and parties without enough endorsements', function (array $members, array $endorsements): void {
    $scores = (new TrustCalculator)->calculate($members, 1, $endorsements);

    expect($scores)->toBe(array_fill_keys($members, 0.0));
})->with([
    'fewer than three members' => [[1, 2], [1 => [2 => 50]]],
    'no endorsements' => [[1, 2, 3], []],
    'too few endorsements' => [[1, 2, 3], [1 => [2 => 2], 2 => [3 => 2]]],
]);

it('survives degenerate input without throwing and stays within bounds', function (array $members, int $trusted, array $endorsements): void {
    $scores = (new TrustCalculator)->calculate($members, $trusted, $endorsements);

    assertSane($scores, $members);
})->with([
    'exact cycle' => [[1, 2, 3], 1, [1 => [2 => 5], 2 => [3 => 5], 3 => [1 => 5]]],
    'trusted user not a member' => [[2, 3, 4], 1, [2 => [3 => 9], 3 => [4 => 9]]],
    'self votes only' => [[1, 2, 3], 1, [1 => [1 => 9], 2 => [2 => 9], 3 => [3 => 9]]],
    'system requests only' => [[1, 2, 3], 1, [1 => [0 => 9], 2 => [0 => 9]]],
    'non-positive counts' => [[1, 2, 3], 1, [1 => [2 => 0, 3 => -4]]],
    'non-member voters and owners' => [[1, 2, 3], 1, [9 => [1 => 9], 1 => [8 => 9]]],
    'no members' => [[], 1, []],
]);

it('keeps cycles stable with the trusted user holding the maximum', function (): void {
    $scores = (new TrustCalculator)->calculate([1, 2, 3], 1, [1 => [2 => 5], 2 => [3 => 5], 3 => [1 => 5]]);

    expect($scores[1])->toBe(1.0)
        ->and($scores[2])->toBeGreaterThan($scores[3]);
});
