<?php

use App\Jobs\PartyPlayYouTubeVideo;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

function youtubeParty(string $roleCode): array
{
    $host = User::factory()->create();
    $party = new Party;
    $party->code = 'ABCD';
    $party->name = 'Test Party';
    $party->user_id = $host->id;
    Party::withoutEvents(fn () => $party->save());

    $member = User::factory()->create();
    $role = PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $row = new PartyMember;
    $row->party_id = $party->id;
    $row->user_id = $member->id;
    $row->role_id = $role->id;
    $row->save();

    return [$party, $member];
}

it('returns 401 to a guest posting a video and broadcasts nothing', function () {
    Bus::fake();
    [$party] = youtubeParty('owner');

    $this->postJson(route('parties.youtube_play', $party), ['video' => 'https://youtu.be/abc123'])
        ->assertUnauthorized();

    Bus::assertNotDispatched(PartyPlayYouTubeVideo::class);
});

it('returns 403 to a non-manager posting a video and broadcasts nothing', function () {
    Bus::fake();
    [$party, $member] = youtubeParty('vip');

    $this->actingAs($member)
        ->postJson(route('parties.youtube_play', $party), ['video' => 'https://youtu.be/abc123'])
        ->assertForbidden();

    Bus::assertNotDispatched(PartyPlayYouTubeVideo::class);
});

it('lets a manager broadcast a valid video', function (string $url) {
    Bus::fake();
    [$party, $member] = youtubeParty('owner');

    $this->actingAs($member)
        ->post(route('parties.youtube_play', $party), ['video' => $url])
        ->assertRedirect(route('parties.youtube', $party->code));

    Bus::assertDispatched(PartyPlayYouTubeVideo::class);
})->with([
    'short link' => 'https://youtu.be/Lp__P8VBR5o?si=teYIzjNQoHuD7SEU',
    'watch link' => 'https://www.youtube.com/watch?v=Lp__P8VBR5o',
    'mobile link' => 'https://m.youtube.com/watch?v=Lp__P8VBR5o',
]);

it('rejects bad video input with 422', function (string $url) {
    Bus::fake();
    [$party, $member] = youtubeParty('owner');

    $this->actingAs($member)
        ->postJson(route('parties.youtube_play', $party), ['video' => $url])
        ->assertUnprocessable();

    Bus::assertNotDispatched(PartyPlayYouTubeVideo::class);
})->with([
    'host-less' => 'not a url',
    'path only' => '/watch?v=abc',
    'non-youtube host' => 'https://example.com/watch?v=abc',
    'lookalike host' => 'https://youtube.com.evil.test/watch?v=abc',
    'no video id' => 'https://www.youtube.com/watch',
    'short link without id' => 'https://youtu.be/',
]);

it('lets guests load the tv, youtube and youtube player pages', function (string $route) {
    $this->withoutVite();
    [$party] = youtubeParty('owner');

    $this->get(route($route, $party))->assertOk();
})->with(['parties.tv', 'parties.youtube', 'parties.ytplayer']);
