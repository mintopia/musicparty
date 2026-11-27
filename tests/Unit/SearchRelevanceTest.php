<?php

use App\Support\SearchRelevance;

it('is strong when one result holds every word of the query', function (string $query) {
    expect(SearchRelevance::isWeak($query, ['Get Lucky Daft Punk Random Access Memories', 'Alive 2007']))->toBeFalse();
})->with(['plain' => 'get lucky', 'across name and artist' => 'lucky daft punk', 'case and punctuation' => 'GET, LUCKY!']);

it('is weak when no result holds every word of the query', function () {
    expect(SearchRelevance::isWeak('lucky dafft punk', ['Get Lucky Daft Punk', 'Alive 2007']))->toBeTrue();
});

it('is never weak without results or without words in the query', function () {
    expect(SearchRelevance::isWeak('lucky', []))->toBeFalse()
        ->and(SearchRelevance::isWeak('!!!', ['Anything']))->toBeFalse();
});
