<?php

use App\Domain\Mod\Trust\PageRank;

it('ranks a three-member chain by hand-computed values', function () {
    $scores = (new PageRank)([1, 2, 3], [1 => [2 => 1.0], 2 => [3 => 1.0]], 1);

    expect($scores[1])->toEqualWithDelta(1.0, 1e-4)
        ->and($scores[2])->toEqualWithDelta(0.85, 1e-4)
        ->and($scores[3])->toEqualWithDelta(0.7225, 1e-4);
});

it('ranks a four-member graph by hand-computed values', function () {
    $scores = (new PageRank)([1, 2, 3, 4], [1 => [2 => 3.0, 3 => 1.0], 2 => [4 => 1.0]], 1);

    expect($scores[1])->toEqualWithDelta(1.0, 1e-4)
        ->and($scores[2])->toEqualWithDelta(0.6375, 1e-4)
        ->and($scores[3])->toEqualWithDelta(0.2125, 1e-4)
        ->and($scores[4])->toEqualWithDelta(0.541875, 1e-4);
});

it('gives members nobody trusts a score of zero and normalises the maximum to one', function () {
    $scores = (new PageRank)([1, 2, 3], [1 => [2 => 1.0]], 1);

    expect($scores[3])->toBe(0.0)
        ->and(max([0.0, ...$scores]))->toEqualWithDelta(1.0, 1e-12);
});

it('ignores self edges and unknown nodes, and returns nothing without the anchor', function () {
    $scores = (new PageRank)([1, 2], [1 => [1 => 5.0, 99 => 3.0, 2 => 1.0]], 1);

    expect($scores[2])->toEqualWithDelta(0.85, 1e-4)
        ->and((new PageRank)([1, 2], [], 7))->toBe([]);
});
