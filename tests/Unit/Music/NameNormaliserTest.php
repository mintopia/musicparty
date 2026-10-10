<?php

use App\Domain\Music\Support\NameNormaliser;

it('lowercases, trims and collapses whitespace', function () {
    expect(NameNormaliser::normalise("  The   BEATLES \t"))->toBe('the beatles')
        ->and(NameNormaliser::normalise(null))->toBe('')
        ->and(NameNormaliser::normalise('Café'))->toBe(NameNormaliser::normalise(' CAFÉ '));
});

it('normalises lists, dropping blanks and duplicates', function () {
    expect(NameNormaliser::normaliseAll([' A ', 'a', '', '  ', 'B', 5]))->toBe(['a', 'b'])
        ->and(NameNormaliser::normaliseAll(null))->toBe([]);
});
