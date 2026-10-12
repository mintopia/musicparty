<?php

use App\Domain\Music\Data\AlbumData;
use App\Domain\Music\Data\ArtistData;
use App\Domain\Music\Data\TrackData;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Queue\Blocklist;
use App\Domain\Queue\BlocklistMatchType;

function blocklistTrack(string $name = 'Some Song', string $id = 'TrAcK1', ?string $isrc = 'USRC17607839'): TrackData
{
    return new TrackData('fake', $id, $name, [new ArtistData('artist-1', 'AC/DC')], new AlbumData('album-1', 'Album'), 180000, false, $isrc);
}

function blocklistMatches(BlocklistMatchType $type, string $value, bool $regex, TrackData $track): bool
{
    $entry = (new BlocklistEntry)->forceFill(['match_type' => $type, 'value' => $value, 'is_regex' => $regex]);

    return app(Blocklist::class)->matches($entry, $track);
}

it('treats * as a wildcard and matches the whole value, ignoring case', function (string $value, string $name, bool $expected) {
    expect(blocklistMatches(BlocklistMatchType::TrackName, $value, false, blocklistTrack($name)))->toBe($expected);
})->with([
    'contains' => ['*christmas*', 'Last CHRISTMAS (Remix)', true],
    'prefix only' => ['christmas*', 'Last Christmas', false],
    'exact ignoring case' => ['LAST christmas', 'last Christmas', true],
    'no substring' => ['Last', 'Last Christmas', false],
    'dot is literal' => ['a.c', 'abc', false],
    'regex chars literal' => ['AC/DC (live)', 'ac/dc (LIVE)', true],
    'parens do not group' => ['AC/DC (live)', 'AC/DC live', false],
]);

it('matches a regex on an ISRC', function () {
    expect(blocklistMatches(BlocklistMatchType::Isrc, '^USRC1\d+$', true, blocklistTrack()))->toBeTrue()
        ->and(blocklistMatches(BlocklistMatchType::Isrc, '^usrc1', true, blocklistTrack()))->toBeTrue()
        ->and(blocklistMatches(BlocklistMatchType::Isrc, '^GBXX', true, blocklistTrack()))->toBeFalse();
});

it('matches ISRC ignoring case and supports a wildcard', function () {
    expect(blocklistMatches(BlocklistMatchType::Isrc, 'usrc17607839', false, blocklistTrack()))->toBeTrue()
        ->and(blocklistMatches(BlocklistMatchType::Isrc, 'USRC*', false, blocklistTrack()))->toBeTrue();
});

it('matches a Track id case-sensitively', function () {
    expect(blocklistMatches(BlocklistMatchType::TrackId, 'TrAcK1', false, blocklistTrack()))->toBeTrue()
        ->and(blocklistMatches(BlocklistMatchType::TrackId, 'track1', false, blocklistTrack()))->toBeFalse()
        ->and(blocklistMatches(BlocklistMatchType::TrackId, 'track1', true, blocklistTrack()))->toBeFalse()
        ->and(blocklistMatches(BlocklistMatchType::TrackId, '^TrAcK\d$', true, blocklistTrack()))->toBeTrue();
});
