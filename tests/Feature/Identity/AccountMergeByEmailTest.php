<?php

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Events\SocialAccountAutoLinked;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $userState
 */
function existingUserWithTwitchEmail(string $email = 'Rave@Example.test', array $userState = []): User
{
    $user = User::factory()->create(['nickname' => 'established', ...$userState]);
    LinkedAccount::factory()->for($user)->create([
        'social_provider_id' => SocialProvider::query()->where('code', 'twitch')->value('id'),
        'external_id' => 'twitch-1',
        'email' => $email,
    ]);

    return $user;
}

it('merges a login whose email matches another provider, audits it and flashes info', function () {
    $provider = seedDiscord();
    $user = existingUserWithTwitchEmail('  Rave@Example.test ');
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'))
        ->assertRedirect()
        ->assertSessionHas('info', 'Your Discord account was added to your existing account');

    expect(User::query()->count())->toBe(1)
        ->and(auth()->id())->toBe($user->id)
        ->and($user->accounts()->where('social_provider_id', $provider->id)->where('external_id', '1001')->exists())->toBeTrue()
        ->and(AdminAuditEntry::query()->where('action', 'account.auto_linked')->where('subject_user_id', $user->id)->count())->toBe(1);
});

it('merges into an admin account too', function () {
    seedDiscord();
    $admin = existingUserWithTwitchEmail();
    $admin->roles()->attach(DB::table('roles')->insertGetId(['code' => 'admin', 'name' => 'admin', 'created_at' => now(), 'updated_at' => now()]));
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'));

    expect(User::query()->count())->toBe(1)->and(auth()->id())->toBe($admin->id);
});

it('dispatches the auto-linked event on merge only', function () {
    Event::fake([SocialAccountAutoLinked::class]);
    seedDiscord();
    existingUserWithTwitchEmail('someone-else@example.test');
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'));

    Event::assertNotDispatched(SocialAccountAutoLinked::class);
});

it('does not merge when the provider says the email is unverified', function () {
    seedDiscord();
    existingUserWithTwitchEmail();
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser()->setRaw(['verified' => false]));

    $this->get(route('login.return', 'discord'))->assertSessionMissing('info');

    expect(User::query()->count())->toBe(2)
        ->and(AdminAuditEntry::query()->where('action', 'account.auto_linked')->exists())->toBeFalse();
});

it('merges when the provider explicitly says the email is verified', function () {
    seedDiscord();
    existingUserWithTwitchEmail();
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser()->setRaw(['verified' => true]));

    $this->get(route('login.return', 'discord'));

    expect(User::query()->count())->toBe(1);
});

it('creates a new user and logs a warning when two users match the email', function () {
    seedDiscord();
    existingUserWithTwitchEmail();
    $other = User::factory()->create();
    LinkedAccount::factory()->for($other)->create([
        'social_provider_id' => SocialProvider::query()->where('code', 'google')->value('id'),
        'external_id' => 'google-1',
        'email' => 'rave@example.test',
    ]);
    Log::shouldReceive('warning')->once();
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'))->assertSessionMissing('info');

    expect(User::query()->count())->toBe(3);
});

it('refuses the login when the matched user is suspended', function () {
    seedDiscord();
    existingUserWithTwitchEmail(userState: ['suspended' => true]);
    mockDriver()->shouldReceive('user')->once()->andReturn(remoteUser());

    $this->get(route('login.return', 'discord'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'suspended'));

    $this->assertGuest();
    expect(LinkedAccount::query()->count())->toBe(1);
});

it('creates a new user when the provider returns no email', function () {
    seedDiscord();
    existingUserWithTwitchEmail();
    $remote = remoteUser();
    $remote->map(['email' => null]);
    mockDriver()->shouldReceive('user')->once()->andReturn($remote);

    $this->get(route('login.return', 'discord'));

    expect(User::query()->count())->toBe(2);
});

it('keeps the normalised email in step with the email', function () {
    $account = LinkedAccount::factory()->create(['email' => '  MiXed@Example.TEST ']);

    expect($account->email_normalised)->toBe('mixed@example.test');

    $account->forceFill(['email' => null])->save();

    expect($account->fresh()->email_normalised)->toBeNull();
});
