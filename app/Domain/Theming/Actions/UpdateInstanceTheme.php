<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Domain\Identity\Models\User;
use App\Domain\Theming\ThemeTokens;
use App\Models\InstanceTheme;

class UpdateInstanceTheme
{
    public function __construct(private readonly GetInstanceTheme $getInstanceTheme, private readonly RecordAdminAudit $recordAdminAudit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{light: array<string, string>, dark: array<string, string>, font: string}
     */
    public function handle(User $admin, array $data): array
    {
        $theme = $this->getInstanceTheme->handle();

        foreach (['light', 'dark'] as $scheme) {
            $values = $data[$scheme] ?? null;
            if (! is_array($values)) {
                continue;
            }
            foreach (ThemeTokens::keys() as $key) {
                if (ThemeTokens::isHex($values[$key] ?? null)) {
                    $theme[$scheme][$key] = strtolower($values[$key]);
                }
            }
        }

        $font = $data['font'] ?? null;
        if (is_string($font) && array_key_exists($font, ThemeTokens::FONTS)) {
            $theme['font'] = $font;
        }

        $record = InstanceTheme::query()->first() ?? new InstanceTheme;
        $record->tokens = $theme;
        $record->save();

        $this->recordAdminAudit->handle($admin, 'theme.updated', null, ['font' => $theme['font']]);

        return $theme;
    }
}
