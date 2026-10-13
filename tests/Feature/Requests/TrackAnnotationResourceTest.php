<?php

use App\Http\Resources\TrackAnnotationResource;
use App\Services\Music\Fake\FakeMusicProvider;
use App\Services\Requests\TrackAnnotation;

function annotationFor(bool $upcoming, bool $allowed, ?string $reason = null): array
{
    $track = app(FakeMusicProvider::class)->getTrack('fake-track-1') ?? throw new RuntimeException('No fake track');

    $annotation = new TrackAnnotation(
        $track,
        $upcoming,
        $upcoming ? 7 : null,
        $upcoming ? 3 : 0,
        $upcoming ? 1 : null,
        $upcoming ? 'Jess' : null,
        $allowed,
        $reason,
    );

    return (new TrackAnnotationResource($annotation))->resolve();
}

it('describes a track that is not in the queue and allowed', function () {
    $json = annotationFor(false, true);

    expect($json)->toHaveKeys(['provider_id', 'id', 'name', 'duration_ms', 'explicit', 'album', 'artists', 'queue', 'eligibility'])
        ->and($json['album'])->toHaveKeys(['id', 'name', 'image_url'])
        ->and($json['artists'][0])->toHaveKeys(['id', 'name'])
        ->and($json['queue'])->toBe(['in_queue' => false, 'upcoming_id' => null, 'score' => 0, 'vote' => null, 'requested_by' => null])
        ->and($json['eligibility'])->toBe(['allowed' => true, 'reason' => null]);
});

it('describes a track already in the queue', function () {
    $json = annotationFor(true, true);

    expect($json['queue'])->toBe(['in_queue' => true, 'upcoming_id' => 7, 'score' => 3, 'vote' => 1, 'requested_by' => 'Jess']);
});

it('describes a blocked track with its reason', function () {
    $json = annotationFor(false, false, 'Explicit tracks are not allowed');

    expect($json['eligibility'])->toBe(['allowed' => false, 'reason' => 'Explicit tracks are not allowed']);
});

it('does not expose the legacy spotify-shaped keys', function () {
    expect(annotationFor(false, true))->not->toHaveKeys(['external_urls', 'popularity', 'upcoming']);
});
