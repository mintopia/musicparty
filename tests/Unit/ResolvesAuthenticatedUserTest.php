<?php

use App\Http\Concerns\ResolvesAuthenticatedUser;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

function authenticatedUserResolver(): object
{
    return new class
    {
        use ResolvesAuthenticatedUser;

        public function resolve(Request $request): User
        {
            return $this->resolveAuthenticatedUser($request);
        }
    };
}

it('returns the authenticated user', function () {
    $user = new User;
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    expect(authenticatedUserResolver()->resolve($request))->toBe($user);
});

it('throws an authentication exception without a user', function () {
    authenticatedUserResolver()->resolve(Request::create('/'));
})->throws(AuthenticationException::class);
