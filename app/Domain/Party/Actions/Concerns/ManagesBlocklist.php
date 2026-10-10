<?php

namespace App\Domain\Party\Actions\Concerns;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\PartyRole;
use App\Domain\Party\Exceptions\BlocklistActionRefused;
use App\Domain\Party\FallbackPlaylistCheck;
use App\Domain\Party\Models\BlocklistEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Blocklist;
use App\Domain\Queue\BlocklistMatchType;

trait ManagesBlocklist
{
    /**
     * @throws BlocklistActionRefused
     */
    private function assertCanManage(User $actor, Party $party): void
    {
        $member = $party->memberFor($actor);

        if ($member === null || $member->banned || ! in_array($member->role, [PartyRole::Host, PartyRole::Moderator], true)) {
            throw BlocklistActionRefused::notAllowed();
        }
    }

    /**
     * @throws BlocklistActionRefused
     */
    private function assertValidPattern(BlocklistMatchType $type, bool $isRegex, string $value): void
    {
        if (! $isRegex) {
            return;
        }

        if (! $type->supportsRegex()) {
            throw BlocklistActionRefused::regexNotSupported();
        }

        if (! Blocklist::isValidPattern($value)) {
            throw BlocklistActionRefused::invalidRegex();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BlocklistEntry $entry): array
    {
        return [
            'match_type' => $entry->match_type->value,
            'value' => $entry->value,
            'is_regex' => $entry->is_regex,
            'is_enabled' => $entry->is_enabled,
            'notes' => $entry->notes,
        ];
    }

    private function revalidateFallbackPlaylist(User $actor, Party $party): ?FallbackPlaylistCheck
    {
        if ($party->fallback_playlist_id === null || $party->fallback_playlist_id === '') {
            return null;
        }

        $check = $this->gate->check($party);

        if ($check->passes()) {
            return null;
        }

        ($this->record)($party, 'party.fallback_playlist_invalid', $actor, details: [
            'playable' => $check->playable,
            'required' => $check->required,
        ]);

        return $check;
    }
}
