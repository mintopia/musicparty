<?php

namespace Tests\Unit\Transformers\Api\V1;

use App\Transformers\Api\V1\TrackTransformer;
use PHPUnit\Framework\TestCase;

class TrackTransformerTest extends TestCase
{
    /**
     * @return void
     */
    public function test_transform_maps_correctly()
    {
        // Arrange
        $track = (object) [
            'id' => 1,
            'name' => 'Track name',
            'album' => (object) [
                'id' => 2,
                'name' => 'Album name',
                'images' => [
                    (object) [
                        'url' => 'Image URL',
                    ],
                ],
            ],
            'artists' => [
                (object) [
                    'id' => 3,
                    'name' => 'artist 1',
                ],
                (object) [
                    'id' => 4,
                    'name' => 'artist 2',
                ],
            ],
            'duration_ms' => 180000,
            'votes' => 3,
            'hasVoted' => true,
        ];

        $sut = new TrackTransformer();

        // Act
        $actual = $sut->transform($track);

        // Assert
        $expected = [
            'spotify_id' => $track->id,
            'name' => $track->name,
            'album' => (object) [
                'spotify_id' => $track->album->id,
                'name' => $track->album->name,
                'image_url' => $track->album->images[0]->url,
            ],
            'artists' => [
                (object) [
                    'spotify_id' => $track->artists[0]->id,
                    'name' => $track->artists[0]->name,
                ],
                (object) [
                    'spotify_id' => $track->artists[1]->id,
                    'name' => $track->artists[1]->name,
                ],
            ],
            'length' => $track->duration_ms,
            'votes' => $track->votes,
            'voted' => $track->hasVoted,
        ];

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return void
     */
    public function test_transform_handles_no_images()
    {
        // Arrange
        $track = (object) [
            'id' => 1,
            'name' => 'Track name',
            'album' => (object) [
                'id' => 2,
                'name' => 'Album name',
                'images' => [],
            ],
            'artists' => [
                (object) [
                    'id' => 3,
                    'name' => 'artist 1',
                ],
                (object) [
                    'id' => 4,
                    'name' => 'artist 2',
                ],
            ],
            'duration_ms' => 180000,
            'votes' => 3,
            'hasVoted' => true,
        ];

        $sut = new TrackTransformer();

        // Act
        $actual = $sut->transform($track);

        // Assert
        $expected = [
            'spotify_id' => $track->id,
            'name' => $track->name,
            'album' => (object) [
                'spotify_id' => $track->album->id,
                'name' => $track->album->name,
                'image_url' => null,
            ],
            'artists' => [
                (object) [
                    'spotify_id' => $track->artists[0]->id,
                    'name' => $track->artists[0]->name,
                ],
                (object) [
                    'spotify_id' => $track->artists[1]->id,
                    'name' => $track->artists[1]->name,
                ],
            ],
            'length' => $track->duration_ms,
            'votes' => $track->votes,
            'voted' => $track->hasVoted,
        ];

        $this->assertEquals($expected, $actual);
    }

    public function test_transform_handles_no_artists()
    {
        // Arrange
        $track = (object) [
            'id' => 1,
            'name' => 'Track name',
            'album' => (object) [
                'id' => 2,
                'name' => 'Album name',
                'images' => [],
            ],
            'artists' => [],
            'duration_ms' => 180000,
            'votes' => 3,
            'hasVoted' => true,
        ];

        $sut = new TrackTransformer();

        // Act
        $actual = $sut->transform($track);

        // Assert
        $expected = [
            'spotify_id' => $track->id,
            'name' => $track->name,
            'album' => (object) [
                'spotify_id' => $track->album->id,
                'name' => $track->album->name,
                'image_url' => null,
            ],
            'artists' => [],
            'length' => $track->duration_ms,
            'votes' => $track->votes,
            'voted' => $track->hasVoted,
        ];

        $this->assertEquals($expected, $actual);
    }
}
