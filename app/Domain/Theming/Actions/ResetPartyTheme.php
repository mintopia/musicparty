<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Admin\SiteSettings;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Theming\Broadcast\ThemeUpdatedEvent;
use App\Domain\Theming\ThemeTokens;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ResetPartyTheme
{
    public function __construct(private readonly GetPartyTheme $getPartyTheme) {}

    /**
     * @return array{light: array<string, string>, dark: array<string, string>, font: string, logo_url: ?string, logo_dark_url: ?string, background_url: ?string, tv_layout: string, overrides: array{light: array<string, string>, dark: array<string, string>, font: ?string}}
     */
    public function handle(User $user, Party $party): array
    {
        Gate::forUser($user)->authorize('update', $party);

        $obsolete = array_values(array_filter([$party->theme_logo_path, $party->theme_background_path]));
        $party->forceFill([
            'theme' => null,
            'theme_logo_path' => null,
            'theme_background_path' => null,
            'tv_layout' => ThemeTokens::DEFAULT_TV_LAYOUT,
        ])->saveQuietly();
        Storage::disk(SiteSettings::disk())->delete($obsolete);

        ThemeUpdatedEvent::dispatch($party->code);

        return $this->getPartyTheme->handle($party);
    }
}
