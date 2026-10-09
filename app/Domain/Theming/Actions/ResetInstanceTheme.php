<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Domain\Theming\ThemeTokens;
use App\Models\InstanceTheme;
use App\Models\User;

class ResetInstanceTheme
{
    public function __construct(private readonly RecordAdminAudit $recordAdminAudit) {}

    /**
     * @return array{light: array<string, string>, dark: array<string, string>, font: string}
     */
    public function handle(User $admin): array
    {
        InstanceTheme::query()->delete();
        $this->recordAdminAudit->handle($admin, 'theme.reset');

        return ThemeTokens::defaults();
    }
}
