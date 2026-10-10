<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use Illuminate\Auth\Access\AuthorizationException;

class EnterActAsHost
{
    public function __construct(private readonly RecordPartyLogEntry $log) {}

    public function handle(User $admin, Party $party): void
    {
        if (! $admin->hasRole('admin')) {
            throw new AuthorizationException;
        }

        $admin->getConnection()->transaction(function () use ($admin, $party): void {
            $session = AdminHostSession::query()->firstOrCreate(['user_id' => $admin->id, 'party_id' => $party->id]);

            if ($session->wasRecentlyCreated) {
                ($this->log)($party, 'act_as_host.entered', $admin, details: ['acting_as_host' => true]);
            }
        });
    }
}
