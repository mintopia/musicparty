<?php

namespace App\Domain\Admin\Actions;

use App\Domain\PartyLog\Actions\RecordPartyLogEntry;
use App\Models\AdminHostSession;
use App\Models\Party;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class EnterActAsHost
{
    public function __construct(private readonly RecordPartyLogEntry $log) {}

    public function handle(User $admin, Party $party): void
    {
        if (! $admin->hasRole('admin')) {
            throw new AuthorizationException;
        }

        $session = AdminHostSession::query()->firstOrCreate(['user_id' => $admin->id, 'party_id' => $party->id]);

        if ($session->wasRecentlyCreated) {
            $this->log->handle($party, $admin, 'act_as_host.entered', true);
        }
    }
}
