<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;

class LeaveActAsHost
{
    public function __construct(private readonly RecordPartyLogEntry $log) {}

    public function handle(User $admin, Party $party): void
    {
        $deleted = AdminHostSession::query()->where('user_id', $admin->id)->where('party_id', $party->id)->delete();

        if ($deleted > 0) {
            ($this->log)($party, 'act_as_host.left', $admin, details: ['acting_as_host' => true]);
        }
    }
}
