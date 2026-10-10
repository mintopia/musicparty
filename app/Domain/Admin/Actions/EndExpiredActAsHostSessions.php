<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\AdminHostSession;
use App\Domain\Party\Actions\RecordPartyLogEntry;

class EndExpiredActAsHostSessions
{
    public function __construct(private readonly RecordPartyLogEntry $log) {}

    public function handle(): int
    {
        $ended = 0;

        AdminHostSession::query()->expired()->with(['user', 'party'])->each(function (AdminHostSession $session) use (&$ended): void {
            $session->getConnection()->transaction(function () use ($session, &$ended): void {
                $party = $session->party;

                if ($party === null || $session->newQuery()->whereKey($session->id)->delete() === 0) {
                    return;
                }

                ($this->log)($party, 'act_as_host.expired', $session->user, details: ['acting_as_host' => true]);
                $ended++;
            });
        });

        return $ended;
    }
}
