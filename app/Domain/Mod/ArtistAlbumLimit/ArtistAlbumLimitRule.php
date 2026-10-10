<?php

namespace App\Domain\Mod\ArtistAlbumLimit;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Mod\Contracts\RequestRule;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\RuleVerdict;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Support\NameNormaliser;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;

readonly class ArtistAlbumLimitRule implements RequestRule
{
    /**
     * Stored requests and plays keep names only, so artists and albums match by normalised name.
     */
    public function judge(ModContext $context, PartyMember $member, TrackData $track): RuleVerdict
    {
        $maxPerArtist = max(0, (int) ($context->settings['max_per_artist'] ?? 0));
        $maxPerAlbum = max(0, (int) ($context->settings['max_per_album'] ?? 0));
        $cooldown = max(0, (int) ($context->settings['cooldown_seconds'] ?? 0));

        $artists = NameNormaliser::normaliseAll(array_map(fn ($artist): string => $artist->name, $track->artists));
        $album = NameNormaliser::normalise($track->album->name);
        $partyId = $context->party->id;

        if ($maxPerArtist > 0 || $maxPerAlbum > 0) {
            $waiting = TrackRequest::query()
                ->where('party_id', $partyId)
                ->whereIn('status', [RequestStatus::Pending, RequestStatus::Queued, RequestStatus::UpNext])
                ->get(['artists', 'album']);

            foreach ($artists as $artist) {
                if ($maxPerArtist > 0 && $waiting->filter(fn (TrackRequest $request): bool => in_array($artist, NameNormaliser::normaliseAll($request->artists), true))->count() >= $maxPerArtist) {
                    return RuleVerdict::reject("The Queue already holds {$maxPerArtist} track(s) by {$this->displayName($track, $artist)}.");
                }
            }

            if ($maxPerAlbum > 0 && $album !== '' && $waiting->filter(fn (TrackRequest $request): bool => NameNormaliser::normalise($request->getAttribute('album')) === $album)->count() >= $maxPerAlbum) {
                return RuleVerdict::reject("The Queue already holds {$maxPerAlbum} track(s) from {$track->album->name}.");
            }
        }

        if ($cooldown > 0) {
            $recent = Play::query()
                ->where('party_id', $partyId)
                ->where('played_at', '>=', now()->subSeconds($cooldown))
                ->get(['artists', 'album']);

            foreach ($artists as $artist) {
                if ($recent->contains(fn (Play $play): bool => in_array($artist, NameNormaliser::normaliseAll($play->artists), true))) {
                    return RuleVerdict::reject("{$this->displayName($track, $artist)} played recently; try again later.");
                }
            }

            if ($album !== '' && $recent->contains(fn (Play $play): bool => NameNormaliser::normalise($play->getAttribute('album')) === $album)) {
                return RuleVerdict::reject("{$track->album->name} played recently; try again later.");
            }
        }

        return RuleVerdict::accept();
    }

    private function displayName(TrackData $track, string $normalised): string
    {
        foreach ($track->artists as $artist) {
            if (NameNormaliser::normalise($artist->name) === $normalised) {
                return trim($artist->name);
            }
        }

        return $normalised;
    }
}
