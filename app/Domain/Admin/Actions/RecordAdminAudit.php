<?php

namespace App\Domain\Admin\Actions;

use App\Models\AdminAuditEntry;
use App\Models\User;

class RecordAdminAudit
{
    /**
     * @param  array<string, scalar|null>|null  $meta
     */
    public function handle(User $admin, string $action, ?User $subject = null, ?array $meta = null): AdminAuditEntry
    {
        return AdminAuditEntry::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_user_id' => $subject?->id,
            'meta' => $meta,
        ]);
    }
}
