<?php

namespace App\Console\Commands;

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Party\FallbackPlaylistGate;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Players\SoloistPlayer;
use Illuminate\Support\Facades\Log;
use Throwable;

class PartyCheck extends PartyCommand
{
    protected $signature = 'party:check {code? : The Party code; every Live Party when omitted}';

    protected $description = "Report and repair a Party's health";

    protected function perform(): int
    {
        $code = $this->argument('code');

        if (is_string($code) && $code !== '') {
            $party = $this->resolveParty($code);

            return $party === null ? self::FAILURE : $this->check($party);
        }

        $status = self::SUCCESS;

        Party::query()->where('state', PartyState::Live)->each(function (Party $party) use (&$status): void {
            $status = max($status, $this->check($party));
        });

        return $status;
    }

    private function check(Party $party): int
    {
        $this->line("Party {$party->code}");
        Log::info("Checking Party {$party->code}.");

        try {
            $result = app(FallbackPlaylistGate::class)->check($party);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("  Fallback Playlist check failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $player = app(PartyPlayers::class)->for($party);
        $health = $player instanceof SoloistPlayer ? $player->health()->value : ($player === null ? 'no player' : 'n/a');
        $account = $party->user === null ? null : app(AuthorisesHost::class)->linkedAccountFor($party->user, $party->music_provider);

        $this->line("  Player health: {$health}");
        $this->line('  Host account: '.match (true) {
            $account === null => 'not linked',
            $account->needs_relink => 'needs re-linking',
            default => 'linked',
        });
        $this->line("  Playable Fallback Tracks: {$result->playable} of {$result->required}");

        return self::SUCCESS;
    }
}
