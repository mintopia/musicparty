<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Admin\SiteSettings;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Theming\Broadcast\ThemeUpdatedEvent;
use App\Domain\Theming\ThemeTokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class UpdatePartyTheme
{
    public function __construct(private readonly GetPartyTheme $getPartyTheme) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{light: array<string, string>, dark: array<string, string>, font: string, logo_url: ?string, logo_dark_url: ?string, background_url: ?string, tv_layout: string, overrides: array{light: array<string, string>, dark: array<string, string>, font: ?string}}
     */
    public function handle(User $user, Party $party, array $data): array
    {
        Gate::forUser($user)->authorize('update', $party);

        $overrides = $this->getPartyTheme->overrides($party);
        foreach (['light', 'dark'] as $scheme) {
            $values = $data[$scheme] ?? null;
            if (! is_array($values)) {
                continue;
            }
            foreach (ThemeTokens::PARTY_KEYS as $key) {
                if (! array_key_exists($key, $values)) {
                    continue;
                }
                if (ThemeTokens::isHex($values[$key])) {
                    $overrides[$scheme][$key] = strtolower($values[$key]);
                } elseif (blank($values[$key])) {
                    unset($overrides[$scheme][$key]);
                }
            }
        }

        if (array_key_exists('font', $data)) {
            if (is_string($data['font']) && array_key_exists($data['font'], ThemeTokens::FONTS)) {
                $overrides['font'] = $data['font'];
            } elseif (blank($data['font'])) {
                $overrides['font'] = null;
            }
        }

        $party->theme = $this->compact($overrides);

        $layout = $data['tv_layout'] ?? null;
        if (is_string($layout) && array_key_exists($layout, ThemeTokens::TV_LAYOUTS)) {
            $party->tv_layout = $layout;
        }

        $obsolete = [
            ...$this->applyAsset($party, 'theme_logo_path', 'logo', $data),
            ...$this->applyAsset($party, 'theme_background_path', 'background', $data),
        ];

        $party->saveQuietly();
        Storage::disk(SiteSettings::disk())->delete($obsolete);

        ThemeUpdatedEvent::dispatch($party->code);

        return $this->getPartyTheme->handle($party);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function applyAsset(Party $party, string $column, string $field, array $data): array
    {
        $previous = $party->{$column};
        $upload = $data[$field] ?? null;

        if ($upload instanceof UploadedFile) {
            $party->{$column} = $upload->store('parties/'.$party->code.'/theme', ['disk' => SiteSettings::disk()]) ?: null;
        } elseif (filter_var($data['remove_'.$field] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $party->{$column} = null;
        } else {
            return [];
        }

        return is_string($previous) && $previous !== '' ? [$previous] : [];
    }

    /**
     * @param  array{light: array<string, string>, dark: array<string, string>, font: ?string}  $overrides
     * @return ?array<string, mixed>
     */
    private function compact(array $overrides): ?array
    {
        $stored = array_filter([
            'light' => $overrides['light'],
            'dark' => $overrides['dark'],
            'font' => $overrides['font'],
        ], fn (mixed $value): bool => $value !== [] && $value !== null);

        return $stored === [] ? null : $stored;
    }
}
