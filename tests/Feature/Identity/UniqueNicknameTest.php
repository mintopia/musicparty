<?php

use App\Domain\Admin\Models\Role;
use App\Domain\Identity\Actions\CompleteSignup;
use App\Domain\Identity\Models\User;
use App\Rules\UniqueNickname;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('maintains the lowercase nickname key', function () {
    $user = User::factory()->create(['nickname' => '  DJ Fresh ']);

    expect($user->fresh()->nickname_key)->toBe('dj fresh');

    $user->nickname = 'Other';
    $user->save();

    expect($user->fresh()->nickname_key)->toBe('other');
});

it('refuses a duplicate nickname in another case at signup', function () {
    User::factory()->create(['nickname' => 'Alex']);
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => 'ALEX', 'terms' => '1'])
        ->assertSessionHasErrors(['nickname' => UniqueNickname::MESSAGE]);
});

it('refuses a 33 character nickname at signup', function () {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => str_repeat('a', 33), 'terms' => '1'])
        ->assertSessionHasErrors('nickname');
});

it('accepts a 32 character and a one character nickname at signup', function (string $nickname) {
    $user = User::factory()->firstLogin()->create();

    $this->actingAs($user)->post(route('login.signup.store'), ['nickname' => $nickname, 'terms' => '1'])
        ->assertSessionHasNoErrors();
})->with(['one' => 'a', 'max' => str_repeat('b', 32)]);

it('refuses a duplicate nickname in another case on profile update', function () {
    User::factory()->create(['nickname' => 'Alex']);
    $user = User::factory()->create(['nickname' => 'Sam']);

    $this->actingAs($user)->patch('/profile', ['nickname' => 'aLeX'])
        ->assertSessionHasErrors(['nickname' => UniqueNickname::MESSAGE]);
});

it('lets a user change the case of their own nickname', function () {
    $user = User::factory()->create(['nickname' => 'Sam']);

    $this->actingAs($user)->patch('/profile', ['nickname' => 'SAM'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->nickname)->toBe('SAM');
});

it('refuses a 33 character nickname on profile update', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['nickname' => str_repeat('a', 33)])
        ->assertSessionHasErrors('nickname');
});

describe('admin user update', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::query()->firstOrCreate(['code' => 'admin'], ['name' => 'Admin']));
    });

    function adminPayload(string $nickname): array
    {
        return ['nickname' => $nickname, 'roles' => [], 'agreed_terms' => true, 'completed_signup' => true, 'suspended' => false];
    }

    it('refuses a duplicate nickname in another case', function () {
        User::factory()->create(['nickname' => 'Alex']);
        $target = User::factory()->create(['nickname' => 'Sam']);

        $this->actingAs($this->admin)->patch(route('admin.users.update', $target), adminPayload('alex'))
            ->assertSessionHasErrors(['nickname' => UniqueNickname::MESSAGE]);
    });

    it('accepts the target own nickname in another case', function () {
        $target = User::factory()->create(['nickname' => 'Sam']);

        $this->actingAs($this->admin)->patch(route('admin.users.update', $target), adminPayload('SAM'))
            ->assertSessionHasNoErrors();

        expect($target->fresh()->nickname)->toBe('SAM');
    });

    it('refuses a 33 character nickname', function () {
        $target = User::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.users.update', $target), adminPayload(str_repeat('a', 33)))
            ->assertSessionHasErrors('nickname');
    });
});

it('turns a unique index race into a validation error on nickname', function () {
    $first = User::factory()->firstLogin()->create();
    $second = User::factory()->firstLogin()->create();

    (new CompleteSignup)($first, 'Racer');

    expect(fn () => (new CompleteSignup)($second, 'racer'))
        ->toThrow(ValidationException::class);

    expect($second->fresh()->first_login)->toBeTrue();
});

it('returns 422 on nickname when the index is hit after validation passed', function () {
    $user = User::factory()->firstLogin()->create();

    User::saving(function (User $saving): void {
        if ($saving->nickname === 'Racer') {
            DB::table('users')->insert(['nickname' => 'racer', 'nickname_key' => 'racer']);
        }
    });

    $this->actingAs($user)->postJson(route('login.signup.store'), ['nickname' => 'Racer', 'terms' => '1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nickname' => UniqueNickname::MESSAGE]);
});
