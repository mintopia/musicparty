<?php

use App\Models\Party;
use App\Models\PartyMember;
use App\Models\Theme;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Services\Music\Fake\FakeMusicProvider;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeFallbackPlaylist;

const SHARED_IP = '203.0.113.50';
const GUESTS_BEHIND_ONE_IP = 70;

it('lets many guests behind one IP load pages, join, vote and request without a 429', function () {
    $party = Party::factory()->create(['provider_id' => 'fake', 'active' => true, 'code' => 'KXQB']);
    $theme = Theme::factory()->create();
    $song = UpcomingSong::factory()->for($party)->waiting()->create();
    $provider = app(FakeMusicProvider::class);
    $statuses = [];
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => SHARED_IP]);

    for ($guest = 1; $guest <= GUESTS_BEHIND_ONE_IP; $guest++) {
        $statuses['theme'][] = $this->get(route('theme.css', $theme->code))->status();
        $statuses['join'][] = $this->post(route('parties.join'), ['code' => 'KXQB'])->status();
    }

    for ($guest = 1; $guest <= GUESTS_BEHIND_ONE_IP; $guest++) {
        $user = User::factory()->create(['first_login' => false]);
        PartyMember::factory()->for($party)->for($user)->create();
        $provider->withTrack(FakeFallbackPlaylist::track("shared-{$guest}"));
        Sanctum::actingAs($user);

        $statuses['search'][] = $this->get(route('parties.search', [$party->code, 'q' => 'fake']))->status();
        $statuses['vote'][] = $this->postJson(route('api.v1.parties.upcomingsongs.vote.store', [$party, $song]), ['vote' => 1])->status();
        $statuses['request'][] = $this->postJson(route('api.v1.parties.upcomingsongs.store', [$party]), ['provider_track_id' => "shared-{$guest}"])->status();
    }

    $throttled = collect($statuses)->map(fn (array $codes): int => count(array_filter($codes, fn (int $code): bool => $code === 429)))->filter()->all();
    expect($throttled)->toBe([]);
});
