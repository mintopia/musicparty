<?php

namespace App\Services\SocialProviders;

use App\Enums\SettingType;
use App\Exceptions\SocialProviderException;
use App\Models\EmailAddress;
use App\Models\LinkedAccount;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Contracts\SocialProviderContract;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

abstract class AbstractSocialProvider implements SocialProviderContract
{
    protected string $name;
    protected string $code;
    protected string $socialiteProviderCode;

    protected bool $supportsAuth = false;
    protected bool $canBeRenamed = false;

    public function __construct(protected ?SocialProvider $provider = null, protected ?string $redirectUrl = null)
    {
        $this->resolveRedirectUrl();
    }

    protected function resolveRedirectUrl(?string $redirectUrl = null): void
    {
        if ($this->redirectUrl !== null) {
            return;
        }
        if (Auth::guest() && $this->provider && $this->provider->auth_enabled) {
            // Probably login
            $this->redirectUrl = route('login.return', $this->code);
        } else {
            $this->redirectUrl = route('linkedaccounts.store', $this->code);
        }
    }

    public function configMapping(): array
    {
        return [
            'client_id' => (object)[
                'name' => 'Client ID',
                'validation' => 'required|string',
            ],
            'client_secret' => (object)[
                'name' => 'Client Secret',
                'validation' => 'required|string',
                'encrypted' => true,
            ],
        ];
    }

    public function install(): SocialProvider
    {
        $this->provider = SocialProvider::whereCode($this->code)->first();
        if (!$this->provider) {
            $provider = new SocialProvider();
            $this->provider = $provider;
            $provider->name = $this->name;
            $provider->code = $this->code;
            $provider->provider_class = get_called_class();
            $provider->supports_auth = $this->supportsAuth;
            $provider->enabled = false;
            $provider->auth_enabled = false;
            $provider->can_be_renamed = $this->canBeRenamed;

            DB::transaction(function () use ($provider) {
                $provider->save();
                $this->installSettings();
            });

            $provider->save();
        }
        return $this->provider;
    }

    public function installSettings(): void
    {
        foreach ($this->configMapping() as $code => $config) {
            $setting = $this->provider->settings()->whereCode($code)->first();
            if (!$setting) {
                $setting = new ProviderSetting();
                $setting->provider()->associate($this->provider);
                $setting->code = $code;
                // Only set value initially
                $setting->value = $config->value ?? null;
            }
            $setting->name = $config->name;
            $setting->validation = $config->validation ?? null;
            $setting->encrypted = $config->encrypted ?? false;
            $setting->description = $config->description ?? null;
            $setting->type = $config->type ?? SettingType::stString;
            if ($setting->isDirty()) {
                $setting->save();
            }
        }
    }

    public function redirect(): RedirectResponse
    {
        return $this->getSocialiteProvider()->redirect();
    }

    protected function getSocialiteProvider()
    {
        return Socialite::driver($this->socialiteProviderCode);
    }

    public function user(?User $localUser = null)
    {
        if ($localUser === null) {
            $localUser = Auth::user();
        }
        $remoteUser = $this->getSocialiteProvider()->user();

        DB::transaction(function () use ($localUser, $remoteUser) {
            // Find the account
            $account = $this->provider->accounts()->whereExternalId($remoteUser->getId())->first();
            if ($account && ($localUser !== null && $localUser->id != $account->user_id)) {
                throw new SocialProviderException('Account is already associated with another user');
            }

            if ($remoteUser->getEmail()) {
                $existingAccount = LinkedAccount::whereEmail($remoteUser->getEmail())->first();
                if ($existingAccount) {
                    if ($localUser && $existingAccount->user_id != $localUser->id) {
                        throw new SocialProviderException('Account is already associated with another user');
                    } else {
                        $localUser = $existingAccount->user;
                    }
                }
            }

            if ($localUser === null) {
                if ($account) {
                    $localUser = $account->user;
                } elseif (!$this->provider->auth_enabled) {
                    throw new SocialProviderException('Unable to login with this account');
                } else {
                    $localUser = new User;
                    $localUser->nickname = $remoteUser->getNickname();
                    $localUser->save();
                }
            }

            if ($account === null) {
                $account = new LinkedAccount;
                $account->provider()->associate($this->provider);
                $account->user()->associate($localUser);
                $account->external_id = $remoteUser->getId();
                $account->save();
            }

            $this->updateAccount($account, $remoteUser);
            $account->save();
            $localUser->save();
        });

        if ($localUser === null) {
            $localUser = $this->provider->accounts()->whereExternalId($remoteUser->getId())->with('user')->first()->user;
        }
        return $localUser;
    }

    protected function updateAccount(LinkedAccount $account, $remoteUser): void
    {
        $account->avatar_url = $remoteUser->getAvatar();
        $account->refresh_token = $remoteUser->refreshToken;
        $account->access_token = $remoteUser->token;
        $account->name = $remoteUser->getNickname();
        $account->email = $remoteUser->getEmail();
    }
}
