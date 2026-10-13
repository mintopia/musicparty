<?php

namespace App\Services\Playback;

use App\Models\Party;

class PushedStateRouter
{
    /**
     * @param  iterable<PushedStateMapper>  $mappers
     */
    public function __construct(protected iterable $mappers) {}

    /**
     * Splits a channel into its mapper and Party code, or null when no mapper owns the channel.
     *
     * @return array{PushedStateMapper, string}|null
     */
    public function route(string $channel): ?array
    {
        foreach ($this->mappers as $mapper) {
            $prefix = $mapper->channelPrefix();
            if (str_starts_with($channel, $prefix) && strlen($channel) > strlen($prefix)) {
                return [$mapper, substr($channel, strlen($prefix))];
            }
        }

        return null;
    }

    public function findParty(string $code): ?Party
    {
        return Party::where('code', $code)->first();
    }
}
