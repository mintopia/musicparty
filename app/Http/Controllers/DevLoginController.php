<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DevLoginController extends Controller
{
    public function __invoke(string $nickname): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $user = User::query()->where('nickname', $nickname)->first()
            ?? tap((new User)->forceFill(['nickname' => $nickname]))->save();
        $admin = Role::query()->where('code', 'admin')->first()
            ?? Role::query()->forceCreate(['code' => 'admin', 'name' => 'Admin']);
        $user->roles()->syncWithoutDetaching([$admin->id]);

        $user->forceFill(['first_login' => false])->save();

        Auth::login($user);

        return redirect()->route('home');
    }

    public static function enabled(): bool
    {
        return (bool) config('app.dev_login') && app()->environment(['local', 'testing']);
    }
}
