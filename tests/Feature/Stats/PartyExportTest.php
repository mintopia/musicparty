<?php

use App\Domain\Admin\Models\IntegrationToken;
use App\Domain\Admin\Models\Role;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Actions\ReopenParty;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Queue\RequestStatus;
use App\Models\Play;
use App\Models\Rating;
use App\Models\RequestVote;
use App\Models\TrackRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-03-01 20:00:00', 'UTC'));

    $this->party = Party::factory()->ended()->create([
        'code' => 'EXPT',
        'name' => 'Export Night',
        'user_id' => User::factory()->create(['nickname' => 'Owner'])->id,
        'created_at' => '2026-03-01 18:00:00',
    ]);
    $this->host = PartyMember::factory()->for($this->party)->host()->for(User::factory()->create(['nickname' => 'Hosty']))->create();
    $this->mod = PartyMember::factory()->for($this->party)->moderator()->for(User::factory()->create(['nickname' => 'Modo']))->create();
    $this->alice = PartyMember::factory()->for($this->party)->for(User::factory()->create(['nickname' => 'Alice']))->create();
});

function exportRequest(Party $party, PartyMember $requester, string $title, int $second, array $attributes = []): TrackRequest
{
    return TrackRequest::factory()->create([
        'party_id' => $party->id,
        'party_member_id' => $requester->id,
        'provider_track_id' => 'track-'.Str::slug($title),
        'title' => $title,
        'artists' => ['Artist '.$title],
        'album' => 'Album',
        'duration_ms' => 200000,
        'status' => RequestStatus::Played,
        'created_at' => Carbon::parse('2026-03-01 19:00:00', 'UTC')->addSeconds($second),
        ...$attributes,
    ]);
}

function exportPlay(TrackRequest $request, string $playedAt): Play
{
    return Play::factory()->create([
        'party_id' => $request->party_id,
        'track_request_id' => $request->id,
        'party_member_id' => $request->party_member_id,
        'provider_track_id' => $request->provider_track_id,
        'title' => $request->title,
        'artists' => $request->artists,
        'album' => $request->album,
        'duration_ms' => $request->duration_ms,
        'explicit' => false,
        'selection_mode' => 'deterministic',
        'selection_score' => 3,
        'played_at' => Carbon::parse($playedAt, 'UTC'),
    ]);
}

function seedExportData(): Play
{
    $first = exportRequest(test()->party, test()->alice, 'One', 1);
    $second = exportRequest(test()->party, test()->host, 'Two', 2, ['status' => RequestStatus::Rejected, 'rejection_reason' => 'Too loud']);
    $play = exportPlay($first, '2026-03-01 19:10:00');

    RequestVote::factory()->create(['track_request_id' => $first->id, 'party_member_id' => test()->host->id, 'value' => 1]);
    RequestVote::factory()->create(['track_request_id' => $first->id, 'party_member_id' => test()->mod->id, 'value' => 1]);
    RequestVote::factory()->create(['track_request_id' => $second->id, 'party_member_id' => test()->alice->id, 'value' => -1]);
    Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => test()->host->id, 'value' => 1]);
    Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => test()->alice->id, 'value' => -1]);
    Rating::factory()->create(['play_id' => $play->id, 'party_member_id' => test()->mod->id, 'value' => 1]);

    return $play;
}

function exportAs(User $user)
{
    Sanctum::actingAs($user);

    return test()->getJson('/api/v1/parties/EXPT/export');
}

function exportWithToken(array $abilities)
{
    IntegrationToken::factory()->withPlainText('mpi_export_token')->withAbilities($abilities)->create();
    app('auth')->forgetGuards();

    return test()->getJson('/api/v1/parties/EXPT/export', ['Authorization' => 'Bearer mpi_export_token']);
}

function shapeOf(mixed $value): mixed
{
    if (is_array($value)) {
        if (array_is_list($value)) {
            return $value === [] ? [] : [shapeOf($value[0])];
        }

        return array_map(shapeOf(...), $value);
    }

    return get_debug_type($value);
}

describe('content', function () {
    it('exports plays, requests, vote aggregates, ratings and member nicknames', function () {
        $play = seedExportData();

        $data = exportAs($this->host->user)->assertOk()->json();

        expect($data['schema_version'])->toBe(1)
            ->and($data['exported_at'])->toBe('2026-03-01T20:00:00+00:00')
            ->and($data['party'])->toMatchArray(['code' => 'EXPT', 'name' => 'Export Night', 'state' => 'ended', 'music_provider' => 'fake'])
            ->and(collect($data['members'])->pluck('nickname')->all())->toBe(['Hosty', 'Modo', 'Alice'])
            ->and(collect($data['members'])->pluck('role')->all())->toBe(['host', 'moderator', 'guest'])
            ->and($data['plays'])->toHaveCount(1)
            ->and($data['plays'][0])->toMatchArray(['id' => $play->id, 'title' => 'One', 'requested_by' => $this->alice->id, 'played_at' => '2026-03-01T19:10:00+00:00'])
            ->and(collect($data['requests'])->pluck('title')->all())->toBe(['One', 'Two'])
            ->and($data['requests'][1])->toMatchArray(['status' => 'rejected', 'rejection_reason' => 'Too loud'])
            ->and($data['votes'])->toBe([
                ['request_id' => $data['requests'][0]['id'], 'upvotes' => 2, 'downvotes' => 0, 'score' => 2],
                ['request_id' => $data['requests'][1]['id'], 'upvotes' => 0, 'downvotes' => 1, 'score' => -1],
            ])
            ->and($data['ratings'])->toBe([['play_id' => $play->id, 'likes' => 2, 'dislikes' => 1, 'score' => 1]]);
    });

    it('matches the committed export schema shape', function () {
        seedExportData();

        $shape = shapeOf(exportAs($this->host->user)->assertOk()->json());

        $request = [
            'id' => 'int', 'provider_track_id' => 'string', 'title' => 'string', 'artists' => ['string'], 'album' => 'string',
            'duration_ms' => 'int', 'explicit' => 'bool', 'status' => 'string', 'requested_by' => 'int',
            'requested_at' => 'string', 'rejection_reason' => 'null',
        ];

        expect($shape)->toBe([
            'schema_version' => 'int',
            'exported_at' => 'string',
            'party' => ['code' => 'string', 'name' => 'string', 'state' => 'string', 'music_provider' => 'string', 'selection_mode' => 'string', 'created_at' => 'string'],
            'members' => [['id' => 'int', 'nickname' => 'string', 'role' => 'string']],
            'requests' => [$request],
            'plays' => [[
                'id' => 'int', 'request_id' => 'int', 'provider_track_id' => 'string', 'title' => 'string', 'artists' => ['string'],
                'album' => 'string', 'duration_ms' => 'int', 'explicit' => 'bool', 'requested_by' => 'int',
                'selection_mode' => 'string', 'selection_score' => 'int', 'played_at' => 'string',
            ]],
            'votes' => [['request_id' => 'int', 'upvotes' => 'int', 'downvotes' => 'int', 'score' => 'int']],
            'ratings' => [['play_id' => 'int', 'likes' => 'int', 'dislikes' => 'int', 'score' => 'int']],
        ]);
    });

    it('exports an empty ended party with empty collections', function () {
        $data = exportAs($this->host->user)->assertOk()->json();

        expect($data['plays'])->toBe([])->and($data['requests'])->toBe([])->and($data['votes'])->toBe([])->and($data['ratings'])->toBe([]);
    });

    it('never leaks emails, tokens or credentials', function () {
        seedExportData();
        LinkedAccount::factory()->create(['user_id' => $this->host->user_id, 'external_id' => 'ext-9911', 'email' => 'hosty@secret.test']);
        $body = exportAs($this->host->user)->assertOk()->getContent();

        expect($body)->not->toContain('@secret.test')
            ->not->toContain('ext-9911')
            ->not->toContain('"email"')
            ->not->toContain('access_token')
            ->not->toContain('refresh_token')
            ->not->toContain('token_hash')
            ->not->toContain('"token"')
            ->not->toContain('password');
    });

    it('does not include data from other parties', function () {
        $other = Party::factory()->ended()->create();
        $member = PartyMember::factory()->for($other)->create();
        exportPlay(exportRequest($other, $member, 'Elsewhere', 5), '2026-03-01 19:30:00');

        $data = exportAs($this->host->user)->assertOk()->json();

        expect($data['plays'])->toBe([])->and($data['members'])->toHaveCount(3);
    });
});

describe('access', function () {
    it('allows the party host', function () {
        exportAs($this->host->user)->assertOk();
    });

    it('allows the party owner', function () {
        exportAs($this->party->user)->assertOk();
    });

    it('allows instance admins', function () {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->firstOrCreate(['code' => 'admin'], ['name' => 'Admin']));

        exportAs($admin)->assertOk();
    });

    it('allows integration tokens with the export ability', function () {
        exportWithToken(['export'])->assertOk()->assertJsonPath('schema_version', 1);
    });

    it('refuses moderators, members and users outside the party', function (string $who) {
        $user = match ($who) {
            'moderator' => $this->mod->user,
            'member' => $this->alice->user,
            'outsider' => User::factory()->create(),
        };

        exportAs($user)->assertForbidden();
    })->with(['moderator', 'member', 'outsider']);

    it('refuses read-only integration tokens', function () {
        exportWithToken(['read'])->assertForbidden();
    });

    it('refuses revoked integration tokens', function () {
        IntegrationToken::factory()->withPlainText('mpi_revoked')->withAbilities(['export'])->revoked()->create();

        $this->getJson('/api/v1/parties/EXPT/export', ['Authorization' => 'Bearer mpi_revoked'])->assertUnauthorized();
    });

    it('refuses anonymous callers', function () {
        $this->getJson('/api/v1/parties/EXPT/export')->assertUnauthorized();
    });

    it('returns not found for an unknown party', function () {
        Sanctum::actingAs($this->host->user);

        $this->getJson('/api/v1/parties/NOPE/export')->assertNotFound();
    });
});

describe('party state', function () {
    it('refuses live and paused parties with a conflict', function (PartyState $state) {
        $this->party->forceFill(['state' => $state])->save();

        exportAs($this->host->user)->assertStatus(409)->assertJsonStructure(['message']);
        exportWithToken(['export'])->assertStatus(409);
    })->with([PartyState::Live, PartyState::Paused]);

    it('refuses a reopened party until it ends again and then includes every play', function () {
        $first = exportRequest($this->party, $this->alice, 'Before', 1);
        exportPlay($first, '2026-03-01 19:05:00');

        app(ReopenParty::class)($this->host->user, $this->party->fresh());
        expect($this->party->fresh()->state)->toBe(PartyState::Paused);

        exportAs($this->host->user)->assertStatus(409);

        $second = exportRequest($this->party, $this->alice, 'After', 2);
        exportPlay($second, '2026-03-01 19:40:00');
        $this->party->fresh()->forceFill(['state' => PartyState::Ended])->save();

        $data = exportAs($this->host->user)->assertOk()->json();

        expect(collect($data['plays'])->pluck('title')->all())->toBe(['Before', 'After']);
    });
});
