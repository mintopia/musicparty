<?php

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->party = Party::factory()->ended()->create(['code' => 'CSVT', 'music_provider' => 'fake']);
    $this->user = User::factory()->create(['nickname' => 'Alice']);
    $this->member = PartyMember::factory()->for($this->party)->for($this->user)->create();
});

/**
 * @return list<list<string|null>>
 */
function csvRows(string $content): array
{
    return array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", $content))));
}

function csvPlay(Party $party, array $attributes = []): Play
{
    return Play::factory()->for($party)->create($attributes);
}

it('lets a member download the playlist in played order with the requester name', function () {
    $request = TrackRequest::factory()->create(['party_id' => $this->party->id, 'party_member_id' => $this->member->id]);
    csvPlay($this->party, ['title' => 'Second', 'played_at' => '2026-03-01 21:00:00']);
    csvPlay($this->party, [
        'title' => 'First', 'artists' => ['A', 'B'], 'album' => 'Alb', 'provider_track_id' => 'abc',
        'played_at' => '2026-03-01 20:00:00', 'party_member_id' => $this->member->id, 'track_request_id' => $request->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('parties.playlist.csv', ['party' => 'CSVT']));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $rows = csvRows($response->streamedContent());

    expect($rows[0])->toBe(['position', 'played_at', 'title', 'artists', 'album', 'requested_by', 'score', 'track_url'])
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][0])->toBe('1')
        ->and($rows[1][2])->toBe('First')
        ->and($rows[1][3])->toBe('A, B')
        ->and($rows[1][4])->toBe('Alb')
        ->and($rows[1][5])->toBe('Alice')
        ->and($rows[1][7])->toBe('https://fake.test/track/abc')
        ->and($rows[2][0])->toBe('2')
        ->and($rows[2][2])->toBe('Second');
});

it('returns only the header when nothing played', function () {
    $response = $this->actingAs($this->user)->get(route('parties.playlist.csv', ['party' => 'CSVT']));

    expect(csvRows($response->streamedContent()))->toHaveCount(1);
});

it('neutralises csv injection in cells', function (string $title) {
    csvPlay($this->party, ['title' => $title]);

    $rows = csvRows($this->actingAs($this->user)->get(route('parties.playlist.csv', ['party' => 'CSVT']))->streamedContent());

    expect($rows[1][2])->toBe("'".$title);
})->with(['=SUM(1)', '+1', '-1', '@cmd', "\tx"]);

it('refuses non-members, banned members and guests', function (string $who) {
    if ($who === 'banned') {
        $this->member->forceFill(['banned' => true])->save();
    }
    $user = $who === 'outsider' ? User::factory()->create() : $this->user;
    $test = $who === 'guest' ? $this : $this->actingAs($user);

    $response = $test->get(route('parties.playlist.csv', ['party' => 'CSVT']));

    $who === 'guest' ? $response->assertRedirect(route('login')) : $response->assertForbidden();
})->with(['outsider', 'banned', 'guest']);

it('refuses when the party has not ended', function () {
    $this->party->forceFill(['state' => 'live'])->save();

    $this->actingAs($this->user)->get(route('parties.playlist.csv', ['party' => 'CSVT']))->assertStatus(409);
});

it('keeps the json export restricted to hosts', function () {
    $this->actingAs($this->user)->getJson('/api/v1/parties/CSVT/export')->assertForbidden();
});

it('flags own plays, shows history by default and exposes links on an ended party', function () {
    csvPlay($this->party, ['party_member_id' => $this->member->id]);
    csvPlay($this->party);
    $this->party->forceFill(['history_playlist_id' => 'pl1'])->save();

    $this->withoutVite()->actingAs($this->user)->get('/parties/CSVT')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('section', 'history')
            ->where('party.historyPlaylistUrl', 'https://fake.test/playlist/pl1')
            ->where('party.playlistCsvUrl', route('parties.playlist.csv', ['party' => 'CSVT']))
            ->where('history.data.0.is_mine', true)
            ->where('history.data.1.is_mine', false));
});

it('passes null links without a history playlist or when live', function () {
    $this->withoutVite()->actingAs($this->user)->get('/parties/CSVT')
        ->assertInertia(fn (Assert $page): Assert => $page->where('party.historyPlaylistUrl', null));

    $this->party->forceFill(['state' => 'live'])->save();
    $this->withoutVite()->actingAs($this->user)->get('/parties/CSVT')
        ->assertInertia(fn (Assert $page): Assert => $page->where('section', 'queue')->where('party.playlistCsvUrl', null));
});
