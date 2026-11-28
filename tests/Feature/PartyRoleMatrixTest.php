<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

require_once __DIR__.'/../Support/DashboardFixtures.php';

beforeEach(function () {
    $this->party = dashboardParty();
    $this->request = dashboardRequest($this->party);
});

function matrixActor(string $actor): User
{
    $party = test()->party;

    return match ($actor) {
        'creator without row' => $party->user,
        'site admin' => dashboardUser($party, 'admin'),
        'non-member' => dashboardUser($party, null),
        default => dashboardUser($party, $actor),
    };
}

function matrixChannel(string $name, User $user, $party): bool
{
    $callback = Broadcast::driver()->getChannels()->get($name);

    return (bool) $callback($user, $party);
}

dataset('actors', [
    'creator without row' => ['creator without row', 'owner'],
    'owner' => ['owner', 'owner'],
    'moderator' => ['moderator', 'moderator'],
    'vip' => ['vip', 'vip'],
    'user' => ['user', 'user'],
    'banned' => ['banned', 'banned'],
    'site admin' => ['site admin', null],
    'non-member' => ['non-member', null],
]);

it('answers the role code for every actor', function (string $actor, ?string $code) {
    $user = matrixActor($actor);

    expect($this->party->roleCodeFor($user))->toBe($code)
        ->and($this->party->roleCodeFor(null))->toBeNull();
})->with('actors');

it('grants every guarded action exactly as the matrix says', function (string $actor, bool $manage, bool $moderate) {
    $user = matrixActor($actor);
    $admin = $actor === 'site admin';

    expect($this->party->canBeManagedBy($user))->toBe($manage)
        ->and($this->party->canBeModeratedBy($user))->toBe($moderate)
        ->and($user->can('update', $this->party))->toBe($manage || $admin)
        ->and($user->can('player', $this->party))->toBe($manage || $admin)
        ->and($user->can('moderate', $this->party))->toBe($moderate || $admin)
        ->and($user->can('update', $this->request))->toBe($moderate)
        ->and($user->can('approve', $this->request))->toBe($moderate)
        ->and($user->can('reject', $this->request))->toBe($moderate)
        ->and($user->can('delete', $this->request))->toBe($moderate)
        ->and(matrixChannel('party.{party}.owner', $user, $this->party))->toBe($manage)
        ->and(matrixChannel('party.{party}.managers', $user, $this->party))->toBe($moderate || $admin)
        ->and(matrixChannel('webplayer.{party}', $user, $this->party))->toBe($manage || $admin);
})->with([
    'creator without row' => ['creator without row', true, true],
    'owner' => ['owner', true, true],
    'moderator' => ['moderator', false, true],
    'vip' => ['vip', false, false],
    'user' => ['user', false, false],
    'banned' => ['banned', false, false],
    'site admin' => ['site admin', false, false],
    'non-member' => ['non-member', false, false],
]);
