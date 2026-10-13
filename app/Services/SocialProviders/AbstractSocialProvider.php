<?php

namespace App\Services\SocialProviders;

use App\Enums\SettingType;
use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use App\Services\Contracts\SocialProviderContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

abstract class AbstractSocialProvider implements SocialProviderContract
{
    protected string $name;

    protected string $code;

    protected string $socialiteProviderCode;

    protected bool $supportsAuth = false;

    protected bool $canBeRenamed = false;

    protected string $redirectUrl;

    public function __construct(protected ?SocialProvider $provider = null, ?string $redirectUrl = null)
    {
        $this->redirectUrl = $redirectUrl ?? $this->defaultRedirectUrl();
    }

    protected function defaultRedirectUrl(): string
    {
        if (Auth::guest() && $this->provider && $this->provider->auth_enabled) {
            return route('login.return', $this->code);
        }

        return route('linkedaccounts.store', $this->code);
    }

    protected function requireProvider(): SocialProvider
    {
        return $this->provider ?? throw new SocialProviderException('Social provider has not been installed');
    }

    protected function getSetting(string $code): string
    {
        return (string) $this->requireProvider()->getSetting($code);
    }

    /**
     * @return array<string, object{name: string, validation?: string, encrypted?: bool, description?: string, type?: SettingType, value?: mixed}>
     */
    public function configMapping(): array
    {
        return [
            'client_id' => (object) [
                'name' => 'Client ID',
                'validation' => 'required|string',
            ],
            'client_secret' => (object) [
                'name' => 'Client Secret',
                'validation' => 'required|string',
                'encrypted' => true,
            ],
        ];
    }

    public function install(): SocialProvider
    {
        $provider = SocialProvider::whereCode($this->code)->first();
        $this->provider = $provider;
        if ($provider === null) {
            $provider = new SocialProvider;
            $this->provider = $provider;
            $provider->name = $this->name;
            $provider->code = $this->code;
            $provider->provider_class = static::class;
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

        return $provider;
    }

    public function installSettings(): void
    {
        foreach ($this->configMapping() as $code => $config) {
            $setting = $this->requireProvider()->settings()->whereCode($code)->first();
            $isNew = false;
            if (! $setting) {
                $isNew = true;
                $setting = new ProviderSetting;
                $setting->provider()->associate($this->requireProvider());
                $setting->code = $code;
            }
            $setting->name = $config->name;
            $setting->validation = $config->validation ?? null;
            $setting->encrypted = $config->encrypted ?? false;
            $setting->description = $config->description ?? null;
            $setting->type = $config->type ?? SettingType::stString;
            if ($isNew) {
                $setting->value = $config->value ?? null;
            }
            if ($setting->isDirty()) {
                $setting->save();
            }
        }
    }

    public function redirect(): RedirectResponse
    {
        return $this->getSocialiteProvider()->redirect();
    }

    protected function getSocialiteProvider(): AbstractProvider
    {
        $driver = Socialite::driver($this->socialiteProviderCode);
        if (! $driver instanceof AbstractProvider) {
            throw new SocialProviderException('Unexpected Socialite provider');
        }

        return $driver;
    }

    public function user(?User $localUser = null)
    {
        $localUser ??= Auth::user();
        $remoteUser = $this->getSocialiteProvider()->user();
        if (! $remoteUser instanceof SocialiteUser) {
            throw new SocialProviderException('Unexpected Socialite user');
        }

        DB::transaction(function () use ($localUser, $remoteUser) {
            // Find the account
            $account = $this->requireProvider()->accounts()->whereExternalId($remoteUser->getId())->first();
            if ($account && ($localUser !== null && $localUser->id != $account->user_id)) {
                throw new SocialProviderException('Account is already associated with another user');
            }

            $emailVerified = $remoteUser->getEmail() && $this->emailIsVerified($remoteUser);

            if ($remoteUser->getEmail()) {
                if ($localUser) {
                    $takenByAnotherUser = LinkedAccount::whereEmail($remoteUser->getEmail())
                        ->where('user_id', '!=', $localUser->id)
                        ->exists();
                    if ($takenByAnotherUser) {
                        throw new SocialProviderException('Account is already associated with another user');
                    }
                } elseif ($account === null && $emailVerified) {
                    $verifiedMatch = LinkedAccount::whereEmail($remoteUser->getEmail())
                        ->whereNotNull('email_verified_at')
                        ->first();
                    if ($verifiedMatch) {
                        $localUser = $verifiedMatch->user;
                    }
                }
            }

            if ($localUser === null) {
                if ($account) {
                    $localUser = $account->user;
                } elseif (! $this->requireProvider()->auth_enabled) {
                    throw new SocialProviderException('Unable to login with this account');
                } else {
                    $localUser = new User;
                    $localUser->nickname = $remoteUser->getNickname() ?? throw new SocialProviderException('Unable to login with this account');
                    $localUser->save();
                }
            }

            if ($account === null) {
                $account = new LinkedAccount;
                $account->provider()->associate($this->requireProvider());
                $account->user()->associate($localUser);
                $account->external_id = $remoteUser->getId();
                $account->save();
            }

            $this->updateAccount($account, $remoteUser);
            $account->email_verified_at = $emailVerified ? now() : null;
            $account->save();
            $localUser->save();
        });

        $localUser ??= $this->requireProvider()->accounts()->whereExternalId($remoteUser->getId())->with('user')->firstOrFail()->user;

        return $localUser;
    }

    protected function emailIsVerified(SocialiteUser $remoteUser): bool
    {
        return false;
    }

    protected function updateAccount(LinkedAccount $account, SocialiteUser $remoteUser): void
    {
        $account->avatar_url = $remoteUser->getAvatar();
        $account->refresh_token = $remoteUser->refreshToken;
        $account->access_token = $remoteUser->token;
        $account->name = $remoteUser->getNickname();
        $account->email = $remoteUser->getEmail();
    }
}
