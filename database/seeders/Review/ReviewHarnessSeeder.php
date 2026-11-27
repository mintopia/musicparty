<?php

namespace Database\Seeders\Review;

use App\Enums\AiReviewCriterion;
use App\Enums\AiReviewOutcome;
use App\Enums\SettingType;
use App\Models\AiReview;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\ProviderSetting;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\Theme;
use App\Models\User;
use App\Mods\AiRequestReview\AiRequestReviewMod;
use App\Mods\PartyMods;
use App\Services\Dashboard\SystemHealth;
use Database\Seeders\PartyMemberRoleSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SocialProvidersSeeder;
use Database\Seeders\ThemesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Review-only fixtures for reproducing the design-critique mockup frames. Not part of DatabaseSeeder.
 * Safe to run repeatedly; every record is keyed by a stable code, nickname or external id.
 */
class ReviewHarnessSeeder extends Seeder
{
    public const string PARTY_FLAG_OFF = 'REVFLG';

    public const string PARTY_NO_REVIEWS = 'REVEMP';

    public const string PARTY_WITH_ANSWERS = 'REVAIS';

    public const string PARTY_NO_AI_MOD = 'REVNOA';

    public const string PARTY_RELINK = 'REVRLK';

    public const string ADMIN_NICKNAME = 'review-admin';

    public const string RELINK_NICKNAME = 'review-relink';

    public const string TWO_LINKS_NICKNAME = 'review-two-links';

    public const string BOTH_SPOTIFY_NICKNAME = 'review-both-spotify';

    public function run(): void
    {
        $this->call([RolesSeeder::class, PartyMemberRoleSeeder::class, SocialProvidersSeeder::class, SettingsSeeder::class, ThemesSeeder::class]);
        Artisan::call('mods:sync');

        Model::unguarded(function (): void {
            $this->themes();
            $this->branding();
            $providers = $this->providers();
            $this->users($providers);
            $this->parties();
        });

        Cache::put(SystemHealth::SCHEDULER_HEARTBEAT_KEY, now()->getTimestamp(), 86400);
    }

    protected function themes(): void
    {
        $this->theme('StratLAN', true, true, [
            'primary' => '#e8590c',
            'nav-background' => '#14102a',
            'surface' => '#1b1834',
            'text' => '#f1eefc',
            'muted' => '#9a94b8',
            'border' => '#2f2a52',
            'success' => '#37b24d',
        ], ":root {\n    --mp-radius: 6px;\n}\n.navbar-brand {\n    letter-spacing: 0.08em;\n    text-transform: uppercase;\n}\n");
        $this->theme('Reference Theme', false, false, ['primary' => '#7048e8', 'nav-background' => '#212529'], '');
        $this->theme('Disposable Theme', false, false, ['primary' => '#0ca678', 'nav-background' => '#1c2b36'], '');
    }

    /**
     * @param  array<string, string>  $tokens
     */
    protected function theme(string $name, bool $active, bool $dark, array $tokens, string $css): void
    {
        $theme = Theme::query()->where('name', $name)->first() ?? new Theme;
        $theme->name = $name;
        $theme->readonly = false;
        $theme->dark_mode = $dark;
        $theme->tokens = $tokens;
        $theme->css = $css;
        $theme->active = $active;
        $theme->save();
    }

    protected function branding(): void
    {
        $values = [
            'logo-light' => 'https://placehold.co/240x64/ffffff/14102a.png?text=StratLAN',
            'logo-dark' => 'https://placehold.co/240x64/14102a/ffffff.png?text=StratLAN',
            'cover-image' => 'https://placehold.co/1600x900/14102a/e8590c.png?text=StratLAN',
            'favicon' => 'https://placehold.co/64x64/e8590c/ffffff.png?text=S',
        ];
        foreach ($values as $code => $value) {
            $setting = Setting::query()->where('code', $code)->firstOrFail();
            $setting->value = $value;
            $setting->save();
        }
    }

    /**
     * @return array<string, SocialProvider>
     */
    protected function providers(): array
    {
        $states = [
            'discord' => ['enabled' => true, 'auth_enabled' => true, 'secrets' => ['client_id' => 'review-client-id', 'client_secret' => 'review-client-secret']],
            'spotify' => ['enabled' => true, 'auth_enabled' => false, 'secrets' => ['client_id' => 'review-client-id', 'client_secret' => 'review-client-secret']],
            'steam' => ['enabled' => true, 'auth_enabled' => true, 'secrets' => ['client_secret' => '']],
        ];
        $providers = [];
        foreach ($states as $code => $state) {
            $provider = SocialProvider::query()->where('code', $code)->firstOrFail();
            $provider->enabled = $state['enabled'];
            $provider->auth_enabled = $state['auth_enabled'];
            $provider->save();
            foreach ($state['secrets'] as $settingCode => $value) {
                $setting = $provider->settings()->where('code', $settingCode)->first() ?? new ProviderSetting(['code' => $settingCode, 'name' => $settingCode, 'type' => SettingType::stString]);
                $setting->provider()->associate($provider);
                $setting->value = $value;
                $setting->save();
            }
            $providers[$code] = $provider;
        }

        return $providers;
    }

    /**
     * @param  array<string, SocialProvider>  $providers
     */
    protected function users(array $providers): void
    {
        $admin = $this->user(self::ADMIN_NICKNAME);
        $this->link($admin, $providers['discord'], 'review-admin-discord');
        $this->link($admin, $providers['spotify'], 'review-admin-spotify', true);
        $admin->roles()->syncWithoutDetaching(Role::query()->whereIn('code', ['admin', 'create-party'])->pluck('id'));

        $relink = $this->user(self::RELINK_NICKNAME);
        $this->link($relink, $providers['discord'], 'review-relink-discord');
        $this->link($relink, $providers['spotify'], 'review-relink-spotify', true, needsRelink: true);
        $relink->roles()->syncWithoutDetaching(Role::query()->where('code', 'create-party')->pluck('id'));

        $twoLinks = $this->user(self::TWO_LINKS_NICKNAME);
        $this->link($twoLinks, $providers['discord'], 'review-two-links-discord');
        $this->link($twoLinks, $providers['steam'], 'review-two-links-steam');

        $both = $this->user(self::BOTH_SPOTIFY_NICKNAME);
        $this->link($both, $providers['discord'], 'review-both-spotify-discord');
        $this->link($both, $providers['spotify'], 'review-both-spotify-account', true);
        $this->link($both, $providers['spotify'], 'review-both-spotify-search', true);
    }

    protected function user(string $nickname): User
    {
        return User::query()->firstOrCreate(['nickname' => $nickname]);
    }

    protected function link(User $user, SocialProvider $provider, string $externalId, bool $tokens = false, bool $needsRelink = false): void
    {
        $account = LinkedAccount::query()->firstOrNew(['social_provider_id' => $provider->id, 'external_id' => $externalId]);
        $account->user_id = $user->id;
        $account->name = $externalId;
        $account->email = "{$externalId}@review.test";
        if ($tokens) {
            $account->access_token = 'review-access-token';
            $account->refresh_token = 'review-refresh-token';
            $account->access_token_expires_at = $needsRelink ? now()->subDay() : now()->addYear();
        }
        $account->needs_relink_at = $needsRelink ? ($account->needs_relink_at ?? now()) : null;
        $account->save();
    }

    protected function parties(): void
    {
        $owner = User::query()->where('nickname', self::ADMIN_NICKNAME)->firstOrFail();
        $relinkOwner = User::query()->where('nickname', self::RELINK_NICKNAME)->firstOrFail();

        $flagOff = $this->party(self::PARTY_FLAG_OFF, 'Review: Flagging Off', $owner, ['flag_requests' => false]);
        $empty = $this->party(self::PARTY_NO_REVIEWS, 'Review: No AI Reviews', $owner, ['flag_requests' => true]);
        $answers = $this->party(self::PARTY_WITH_ANSWERS, 'Review: AI Answers', $owner, ['flag_requests' => true]);
        $noMod = $this->party(self::PARTY_NO_AI_MOD, 'Review: No AI Mod', $owner, ['flag_requests' => true]);
        $relink = $this->party(self::PARTY_RELINK, 'Review: Spotify Re-link', $relinkOwner, ['flag_requests' => true], provider: 'spotify');

        $mods = app(PartyMods::class);
        foreach ([$flagOff, $empty, $answers, $relink] as $party) {
            $mods->enable($party, AiRequestReviewMod::CODE);
        }
        $mods->disable($noMod, AiRequestReviewMod::CODE);

        $this->reviews($answers, $owner);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function party(string $code, string $name, User $owner, array $attributes, string $provider = 'fake'): Party
    {
        $party = Party::query()->where('code', $code)->first() ?? new Party;
        $party->forceFill($attributes + ['code' => $code, 'name' => $name, 'user_id' => $owner->id, 'provider_id' => $provider, 'active' => true]);
        $party->save();

        return $party;
    }

    protected function reviews(Party $party, User $requester): void
    {
        $rows = [
            ['review-track-1', 'Fake Track 1', AiReviewOutcome::Allow, AiReviewCriterion::None, 4.5, 0.93],
            ['review-track-2', 'Fake Track 2', AiReviewOutcome::Flag, AiReviewCriterion::Vibe, 2.0, 0.71],
            ['review-track-3', 'Fake Track 3', AiReviewOutcome::Reject, AiReviewCriterion::Explicit, 1.0, 0.88],
            ['review-track-4', 'Fake Track 4', AiReviewOutcome::Flag, AiReviewCriterion::Troll, 1.5, 0.64],
            ['review-track-5', 'Fake Track 5', AiReviewOutcome::Reject, AiReviewCriterion::Guidelines, 0.5, 0.97],
        ];
        foreach ($rows as $index => [$trackId, $title, $outcome, $criterion, $score, $confidence]) {
            $review = AiReview::query()->firstOrNew(['party_id' => $party->id, 'track_id' => $trackId]);
            $review->forceFill([
                'user_id' => $requester->id,
                'track_provider' => 'fake',
                'track_title' => $title,
                'track_artists' => 'Fake Artist '.($index + 1),
                'driver' => 'decision-model',
                'model' => 'jev-latest',
                'endpoint_host' => 'api.typesafe.ai',
                'request' => ['model' => 'jev-latest', 'state' => '{}', 'questions' => []],
                'response' => ['answers' => [
                    'suitability' => ['score' => $score, 'confidence' => $confidence],
                    'criterion' => ['type' => 'choice', 'choice' => $criterion->value, 'probabilities' => [$criterion->value => $confidence]],
                ]],
                'outcome' => $outcome,
                'reason' => $outcome === AiReviewOutcome::Allow ? null : $criterion->description(),
                'score' => $score,
                'confidence' => $confidence,
                'criterion' => $criterion,
                'criterion_confidence' => $confidence,
                'latency_ms' => 380 + $index * 40,
                'prompt_tokens' => 120,
                'completion_tokens' => 8,
                'total_tokens' => 128,
                'created_at' => $review->created_at ?? now()->subMinutes(5 - $index),
            ]);
            $review->save();
        }
    }
}
