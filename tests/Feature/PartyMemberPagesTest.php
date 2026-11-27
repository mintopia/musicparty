<?php

use App\Enums\AiReviewCriterion;
use App\Models\AiReview;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\PlayedSong;
use App\Models\UpcomingSong;
use App\Models\User;
use App\Models\Vote;
use Database\Seeders\PartyMemberRoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PartyMemberRoleSeeder::class);
    $this->owner = User::factory()->create();
    $this->party = Party::withoutEvents(fn () => Party::forceCreate([
        'code' => fake()->unique()->lexify('??????'),
        'name' => 'Members Party',
        'user_id' => $this->owner->id,
    ]));
    $this->target = PartyMember::factory()->for($this->party)->for(User::factory()->create(['nickname' => 'Target']))
        ->create(['role_id' => PartyMemberRole::whereCode('user')->firstOrFail()->id]);
    $this->plain = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($this->plain)
        ->create(['role_id' => PartyMemberRole::whereCode('user')->firstOrFail()->id]);
});

it('renders the filtered members index for a manager', function () {
    $this->actingAs($this->owner)
        ->get(route('parties.users.index', [$this->party->code, 'q' => 'Targ role:user', 'per_page' => 5]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Index')
            ->where('party.canManage', true)
            ->where('filters.q', 'Targ role:user')
            ->where('filters.per_page', '5')
            ->where('members.data.0.nickname', 'Target')
            ->where('members.data.0.url', route('parties.users.show', [$this->party->code, $this->target->id]))
            ->has('members.data', 1)
            ->has('roles', 5)
            ->where('roles.0.code', 'owner')
            ->where('roles.1.code', 'moderator')
            ->where('roles.3.count', 2)
            ->where('memberTotal', 2)
            ->has('playerState'));
});

it('filters members by typed role, legacy params and plain words, with per-member counts', function () {
    $song = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id, 'flagged_at' => now()]);
    UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id]);
    Vote::factory()->create(['upcoming_song_id' => $song->id, 'user_id' => $this->target->user_id]);
    $get = fn (array $query) => $this->actingAs($this->owner)->get(route('parties.users.index', [$this->party->code, ...$query]));

    $get(['q' => 'role:user'])->assertInertia(fn (Assert $page) => $page->has('members.data', 2));
    $get(['q' => 'role:VIP'])->assertInertia(fn (Assert $page) => $page->has('members.data', 0));
    $get(['q' => 'targ'])->assertInertia(fn (Assert $page) => $page
        ->has('members.data', 1)
        ->where('members.data.0.requests', 2)
        ->where('members.data.0.votes', 1)
        ->where('members.data.0.flagged', 1)
        ->where('members.data.0.isYou', false));
    $get(['nickname' => 'Targ', 'role' => 'user'])->assertInertia(fn (Assert $page) => $page
        ->where('filters.q', 'Targ role:user')
        ->has('members.data', 1));
});

it('renders member requests, votes and flags scoped to the party and member', function () {
    $played = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id]);
    PlayedSong::factory()->create(['upcoming_song_id' => $played->id, 'song_id' => $played->song_id, 'party_id' => $this->party->id]);
    $flagged = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id, 'flagged_at' => now(), 'flag_reason' => 'Looks wrong']);
    $reviewed = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id, 'flagged_at' => now()]);
    $review = AiReview::factory()->create([
        'party_id' => $this->party->id,
        'upcoming_song_id' => $reviewed->id,
        'criterion' => AiReviewCriterion::Vibe,
        'criterion_confidence' => 0.82,
    ]);
    $waiting = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id]);
    $otherParty = Party::withoutEvents(fn () => Party::forceCreate(['code' => 'OTHR', 'name' => 'Other', 'user_id' => $this->owner->id]));
    UpcomingSong::factory()->for($otherParty)->create(['user_id' => $this->target->user_id, 'flagged_at' => now()]);
    UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->plain->id]);
    $voted = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->plain->id]);
    Vote::factory()->up()->create(['upcoming_song_id' => $voted->id, 'user_id' => $this->target->user_id]);
    Vote::factory()->create(['upcoming_song_id' => $voted->id, 'user_id' => $this->plain->id]);

    $this->actingAs($this->owner)
        ->get(route('parties.users.show', [$this->party->code, $this->target->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Show')
            ->where('member.nickname', 'Target')
            ->where('member.roleCode', 'user')
            ->where('member.requestCount', 4)
            ->where('member.voteCount', 1)
            ->where('member.flaggedCount', 2)
            ->where('member.canBan', true)
            ->where('party.canManage', true)
            ->has('requests', 4)
            ->where('requests', fn ($requests) => collect($requests)->pluck('status', 'id')->all() === [
                $played->id => 'played',
                $flagged->id => 'flagged',
                $reviewed->id => 'flagged',
                $waiting->id => 'waiting',
            ])
            ->has('votes', 1)
            ->where('votes.0.value', 1)
            ->where('votes.0.url', route('parties.songs.show', [$this->party->code, $voted->id]))
            ->has('flags', 2)
            ->where('flags', function ($flags) use ($flagged, $reviewed, $review) {
                $flags = collect($flags)->keyBy('id');

                return $flags[$reviewed->id]['reason'] === 'AI review: vibe, 82% confident.'
                    && $flags[$reviewed->id]['reviewUrl'] === route('parties.ai-reviews.show', [$this->party->code, $review->id])
                    && $flags[$reviewed->id]['outcome'] === 'Waiting for approval'
                    && $flags[$flagged->id]['reason'] === 'Looks wrong'
                    && $flags[$flagged->id]['reviewUrl'] === null;
            }));
});

it('lets a moderator view a member read-only but not edit or ban', function () {
    $moderator = User::factory()->create();
    PartyMember::factory()->for($this->party)->for($moderator)
        ->create(['role_id' => PartyMemberRole::whereCode('moderator')->firstOrFail()->id]);

    $this->actingAs($moderator)
        ->get(route('parties.users.show', [$this->party->code, $this->target->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('party.canManage', false)->where('member.canBan', false));
    $this->actingAs($moderator)->get(route('parties.users.edit', [$this->party->code, $this->target->id]))->assertForbidden();
    $this->actingAs($moderator)
        ->patch(route('parties.users.update', [$this->party->code, $this->target->id]), ['role' => 'banned'])
        ->assertForbidden();
    expect($this->target->fresh()->role->code)->toBe('user');
});

it('bans a member through a role change and leaves waiting requests alone', function () {
    $song = UpcomingSong::factory()->for($this->party)->create(['user_id' => $this->target->user_id]);

    $this->actingAs($this->owner)
        ->patch(route('parties.users.update', [$this->party->code, $this->target->id]), ['role' => 'banned'])
        ->assertRedirect(route('parties.users.show', [$this->party->code, $this->target->id]));

    expect($this->target->fresh()->role->code)->toBe('banned')
        ->and(UpcomingSong::find($song->id))->not->toBeNull();
});

it('refuses to ban an owner', function () {
    $ownerMember = PartyMember::factory()->for($this->party)->for($this->owner)
        ->create(['role_id' => PartyMemberRole::whereCode('owner')->firstOrFail()->id]);

    $this->actingAs($this->owner)
        ->patch(route('parties.users.update', [$this->party->code, $ownerMember->id]), ['role' => 'banned'])
        ->assertForbidden();
    $this->actingAs($this->owner)
        ->get(route('parties.users.show', [$this->party->code, $ownerMember->id]))
        ->assertInertia(fn (Assert $page) => $page->where('member.canBan', false));
    expect($ownerMember->fresh()->role->code)->toBe('owner');
});

it('renders the edit form and flags self-editing', function () {
    $this->actingAs($this->owner)
        ->get(route('parties.users.edit', [$this->party->code, $this->target->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Parties/Members/Edit')
            ->where('isSelf', false)
            ->where('party.canManage', true)
            ->has('roles', 5)
            ->where('roles.0.code', 'owner')
            ->where('roles.1.code', 'moderator')
            ->where('roles.4.code', 'banned'));

    $self = PartyMember::factory()->for($this->party)->for($this->owner)
        ->create(['role_id' => PartyMemberRole::whereCode('owner')->firstOrFail()->id]);

    $this->actingAs($this->owner)
        ->get(route('parties.users.edit', [$this->party->code, $self->id]))
        ->assertInertia(fn (Assert $page) => $page->where('isSelf', true)->where('member.roleCode', 'owner'));
});

it('forbids non-managers from every member page', function (string $route) {
    $params = $route === 'parties.users.index' ? [$this->party->code] : [$this->party->code, $this->target->id];

    $this->actingAs($this->plain)->get(route($route, $params))->assertForbidden();
})->with(['parties.users.index', 'parties.users.show', 'parties.users.edit']);

it('redirects to the member with a flash after update', function () {
    $this->actingAs($this->owner)
        ->patch(route('parties.users.update', [$this->party->code, $this->target->id]), ['role' => 'vip'])
        ->assertRedirect(route('parties.users.show', [$this->party->code, $this->target->id]))
        ->assertSessionHas('successMessage', 'The user has been updated');

    expect($this->target->fresh()->role->code)->toBe('vip');
});
