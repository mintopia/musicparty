<?php

namespace App\Support\Realtime;

use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

class PlayerConnections
{
    private const int TTL_SECONDS = 86400;

    public function register(string $socketId, string $code, int $tokenId): void
    {
        $code = strtoupper($code);

        Cache::put($this->socketKey($socketId), ['code' => $code, 'token_id' => $tokenId], self::TTL_SECONDS);

        $sockets = $this->sockets($code);
        $sockets[] = $socketId;
        Cache::put($this->partyKey($code), array_values(array_unique($sockets)), self::TTL_SECONDS);
    }

    public function accepts(string $socketId, string $code): bool
    {
        $connection = Cache::get($this->socketKey($socketId));

        if (! is_array($connection) || $connection['code'] !== strtoupper($code)) {
            return false;
        }

        if (! $this->isValidToken((int) $connection['token_id'])) {
            return false;
        }

        Cache::put($this->socketKey($socketId), $connection, self::TTL_SECONDS);

        return true;
    }

    public function hasValidConnection(string $code): bool
    {
        $code = strtoupper($code);

        foreach ($this->sockets($code) as $socketId) {
            $connection = Cache::get($this->socketKey($socketId));

            if (is_array($connection) && $this->isValidToken((int) $connection['token_id'])) {
                return true;
            }
        }

        return false;
    }

    public function forgetToken(string $code, int $tokenId): void
    {
        $code = strtoupper($code);
        $remaining = [];

        foreach ($this->sockets($code) as $socketId) {
            $connection = Cache::get($this->socketKey($socketId));

            if (is_array($connection) && (int) $connection['token_id'] === $tokenId) {
                Cache::forget($this->socketKey($socketId));

                continue;
            }

            $remaining[] = $socketId;
        }

        Cache::put($this->partyKey($code), $remaining, self::TTL_SECONDS);
    }

    private function isValidToken(int $tokenId): bool
    {
        $token = PersonalAccessToken::query()->find($tokenId);

        return $token !== null && ($token->expires_at === null || $token->expires_at->isFuture());
    }

    /**
     * @return list<string>
     */
    private function sockets(string $code): array
    {
        $sockets = Cache::get($this->partyKey($code), []);

        return is_array($sockets) ? array_values($sockets) : [];
    }

    private function socketKey(string $socketId): string
    {
        return 'player-socket:'.$socketId;
    }

    private function partyKey(string $code): string
    {
        return 'player-sockets:'.$code;
    }
}
