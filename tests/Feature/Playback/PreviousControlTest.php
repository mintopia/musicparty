<?php

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Actions\ControlPlayback;
use App\Domain\Playback\Control;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Data\TrackReference;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Queue\Actions\RatePlay;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\Rating;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use App\Domain\Queue\VoteDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->live()->create(['code' => 'ABCD']);
    $this->player = useFakePlayer($this->party)->forParty($this->party);

    foreach (['a' => RequestStatus::Played, 'b' => RequestStatus::Playing, 'c' => RequestStatus::UpNext] as $id => $status) {
        $this->{"request{$id}"} = TrackRequest::factory()->for($this->party)->status($status)->create(['provider_track_id' => "track-{$id}"]);
    }

    $this->playA = Play::factory()->for($this->party)->create(['track_request_id' => $this->requesta->id, 'provider_track_id' => 'track-a', 'played_at' => now()->subMinutes(5)]);
    $this->playB = Play::factory()->for($this->party)->create(['track_request_id' => $this->requestb->id, 'provider_track_id' => 'track-b', 'played_at' => now()]);
});

function playingAt(int $positionMs): void
{
    test()->player->emitState(new PlaybackState(PlaybackStatus::Playing, new TrackReference('fake', 'track-b'), $positionMs, now()->toImmutable()));
}

function pressPrevious(): void
{
    app(ControlPlayback::class)(test()->party, Control::Previous);
}

it('restarts the track with a seek to zero from 3 seconds in', function () {
    playingAt(40000);

    pressPrevious();

    $last = array_last($this->player->commands());
    expect([$last->type, $last->value])->toBe(['seek', 0])
        ->and(array_map(fn ($command) => $command->type, $this->player->commands()))->not->toContain('previous')
        ->and(Play::query()->count())->toBe(2);
});

it('goes back without counting a replay', function () {
    playingAt(1000);

    pressPrevious();
    expect(array_last($this->player->commands())->type)->toBe('previous');

    $this->player->emitTrackChanged('fake', 'track-a');

    expect(Play::query()->count())->toBe(2)
        ->and($this->requesta->fresh()->status)->toBe(RequestStatus::Playing)
        ->and($this->requestb->fresh()->status)->toBe(RequestStatus::Played)
        ->and($this->requestc->fresh()->status)->toBe(RequestStatus::UpNext)
        ->and(TrackRequest::query()->count())->toBe(3);
});

it('lets a rating during the replay land on the existing play', function () {
    playingAt(1000);
    pressPrevious();
    $this->player->emitTrackChanged('fake', 'track-a');

    $member = PartyMember::factory()->for($this->party)->create();
    (new RatePlay)($member, $this->playA, VoteDirection::Up);

    expect(Rating::query()->where('play_id', $this->playA->id)->count())->toBe(1);
});

it('hands off to the up next request after the replay', function () {
    playingAt(1000);
    pressPrevious();
    $this->player->emitTrackChanged('fake', 'track-a');

    $this->player->emitTrackChanged('fake', 'track-c');

    expect($this->requestc->fresh()->status)->toBe(RequestStatus::Playing)
        ->and($this->requesta->fresh()->status)->toBe(RequestStatus::Played)
        ->and(Play::query()->count())->toBe(3);
});

it('handles a track change without a marker as before', function () {
    playingAt(1000);

    $this->player->emitTrackChanged('fake', 'track-a');

    expect(Play::query()->count())->toBe(2)
        ->and($this->requesta->fresh()->status)->toBe(RequestStatus::Played);
});

it('does not use a marker for an unrelated track', function () {
    playingAt(1000);
    pressPrevious();

    $this->player->emitTrackChanged('fake', 'track-c');

    expect($this->requestc->fresh()->status)->toBe(RequestStatus::Playing)
        ->and(Play::query()->count())->toBe(3);
});
