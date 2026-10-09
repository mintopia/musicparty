<?php

namespace App\Domain\Admin\Actions;

use App\Domain\PartyLog\Actions\RecordPartyLogEntry;
use App\Models\AdminHostSession;
use App\Models\Party;
use App\Models\User;

class LeaveActAsHost
{
    public function __construct(private readonly RecordPartyLogEntry $log) {}

    public function handle(User $admin, Party $party): void
    {
        $deleted = AdminHostSession::query()->where('user_id', $admin->id)->where('party_id', $party->id)->delete();

        if ($deleted > 0) {
            $this->log->handle($party, $admin, 'act_as_host.left', true);
        }
    }
}
