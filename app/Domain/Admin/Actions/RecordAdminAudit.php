<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\AdminAuditEntry;
use App\Domain\Identity\Models\User;

class RecordAdminAudit
{
    /**
     * @param  array<string, scalar|null>|null  $meta
     */
    public function handle(User $admin, string $action, ?User $subject = null, ?array $meta = null): AdminAuditEntry
    {
        return AdminAuditEntry::query()->create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->nickname,
            'action' => $action,
            'subject_user_id' => $subject?->id,
            'subject_name' => $subject?->nickname,
            'meta' => $meta,
        ]);
    }
}
