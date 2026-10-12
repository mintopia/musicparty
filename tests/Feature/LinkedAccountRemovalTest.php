<?php

use App\Models\LinkedAccount;
use App\Models\User;

function userWithTwoAccounts(): array
{
    $user = User::factory()->create();
    $accounts = LinkedAccount::factory()->count(2)->create(['user_id' => $user->id]);

    return [$user, $accounts[0]];
}

it('shows a confirmation to the owner of the linked account', function () {
    $this->withoutVite();
    [$user, $account] = userWithTwoAccounts();

    $this->actingAs($user)
        ->get(route('linkedaccounts.delete', $account))
        ->assertOk()
        ->assertSee(route('linkedaccounts.destroy', $account), false);
});

it('removes the linked account for its owner', function () {
    [$user, $account] = userWithTwoAccounts();

    $this->actingAs($user)
        ->delete(route('linkedaccounts.destroy', $account))
        ->assertRedirect(route('user.profile'))
        ->assertSessionHas('successMessage');

    expect(LinkedAccount::find($account->id))->toBeNull();
});

it('refuses to remove the only linked account', function () {
    $user = User::factory()->create();
    $account = LinkedAccount::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('linkedaccounts.destroy', $account))
        ->assertRedirect(route('user.profile'))
        ->assertSessionHas('errorMessage');

    expect(LinkedAccount::find($account->id))->not->toBeNull();
});

it('does not show the confirmation for the only linked account', function () {
    $user = User::factory()->create();
    $account = LinkedAccount::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('linkedaccounts.delete', $account))
        ->assertRedirect(route('user.profile'))
        ->assertSessionHas('errorMessage');
});

it('stops another user viewing the confirmation or removing the account', function () {
    [, $account] = userWithTwoAccounts();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('linkedaccounts.delete', $account))->assertForbidden();
    $this->actingAs($stranger)->delete(route('linkedaccounts.destroy', $account))->assertForbidden();

    expect(LinkedAccount::find($account->id))->not->toBeNull();
});

it('keeps the link flow on the return route', function () {
    expect(route('linkedaccounts.store', 'discord', false))->toBe('/profile/accounts/discord/return')
        ->and(route('linkedaccounts.delete', 5, false))->toBe('/profile/accounts/5/delete');
});
