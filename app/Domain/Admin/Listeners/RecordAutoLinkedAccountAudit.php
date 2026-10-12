<?php

namespace App\Domain\Admin\Listeners;

use App\Domain\Admin\Actions\RecordAdminAudit;
use App\Domain\Identity\Events\SocialAccountAutoLinked;

readonly class RecordAutoLinkedAccountAudit
{
    public function __construct(private RecordAdminAudit $recordAdminAudit) {}

    public function handle(SocialAccountAutoLinked $event): void
    {
        $this->recordAdminAudit->handle($event->user, 'account.auto_linked', $event->user, [
            'provider' => $event->account->provider?->code,
            'linked_account_id' => $event->account->id,
        ]);
    }
}
