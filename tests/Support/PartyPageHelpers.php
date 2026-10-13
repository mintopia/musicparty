<?php

use App\Enums\SettingType;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\PartyMember;
use App\Models\PartyMemberRole;
use App\Models\ProviderSetting;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Support\Str;

function pageSearchLinkAccount(User $user, string $providerCode, string $token): void
{
    $provider = SocialProvider::query()->where('code', $providerCode)->first() ?? SocialProvider::factory()->create(['code' => $providerCode]);
    foreach (['client_id', 'client_secret'] as $code) {
        $setting = new ProviderSetting;
        $setting->forceFill(['code' => $code, 'name' => $code, 'type' => SettingType::stString, 'value' => 'test']);
        $setting->provider()->associate($provider);
        $setting->save();
    }
    LinkedAccount::factory()->create([
        'user_id' => $user->id,
        'social_provider_id' => $provider->id,
        'access_token' => $token,
        'refresh_token' => 'refresh',
        'access_token_expires_at' => now()->addHour(),
    ]);
}

function pageSearchParty(User $owner, array $attributes = []): Party
{
    $party = new Party;
    $party->forceFill(array_merge(['code' => strtoupper(Str::random(4)), 'name' => 'Search Party', 'user_id' => $owner->id, 'provider_id' => 'spotify', 'active' => false, 'allow_requests' => true, 'explicit' => true], $attributes));
    Party::withoutEvents(fn () => $party->save());

    return $party;
}

function pageSearchMember(Party $party, string $roleCode = 'member'): PartyMember
{
    $role = PartyMemberRole::query()->where('code', $roleCode)->first() ?? PartyMemberRole::query()->forceCreate(['code' => $roleCode, 'name' => ucfirst($roleCode)]);
    $member = new PartyMember;
    $member->party_id = $party->id;
    $member->user_id = User::factory()->create(['first_login' => false])->id;
    $member->role_id = $role->id;
    $member->save();

    return $member;
}
