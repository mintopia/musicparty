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
            $session = AdminHostSession::query()->firstOrNew(['user_id' => $admin->id, 'party_id' => $party->id]);
            $isNew = ! $session->exists || $session->expires_at?->isPast() === true;

            $session->expires_at = now()->addMinutes(config()->integer('musicparty.act_as_host_ttl_minutes'));
            $session->save();

            if ($isNew) {
                ($this->log)($party, 'act_as_host.entered', $admin, details: ['acting_as_host' => true]);
            }
        });
    }
}
