<?php

use App\Domain\Music\Support\PlaylistReference;

it('extracts the id from a recognised reference', function (string $input) {
    expect(PlaylistReference::parse($input))->toBe('37i9dQZF1DXcBWIGoYBM5M');
})->with([
    'url with query string' => 'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M?si=abc123&utm_source=copy-link',
    'plain url' => 'https://open.spotify.com/playlist/37i9dQZF1DXcBWIGoYBM5M',
    'localised url' => 'https://open.spotify.com/intl-de/playlist/37i9dQZF1DXcBWIGoYBM5M?si=x',
    'uri' => 'spotify:playlist:37i9dQZF1DXcBWIGoYBM5M',
    'bare id' => '37i9dQZF1DXcBWIGoYBM5M',
    'padded bare id' => "  37i9dQZF1DXcBWIGoYBM5M\n",
]);

it('rejects garbage', function (?string $input) {
    expect(PlaylistReference::parse($input))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'words' => 'not a playlist',
    'too short' => 'abc',
    'album uri' => 'spotify:album:37i9dQZF1DXcBWIGoYBM5M',
    'track url' => 'https://open.spotify.com/track/37i9dQZF1DXcBWIGoYBM5M',
    'injection' => '37i9dQZF1DXcBWIGoYBM5M; DROP TABLE parties',
]);
