<?php

namespace App\Domain\Playback\Players;

final class SoloistCommands
{
    /**
     * @return array<string, mixed>
     */
    public static function getState(): array
    {
        return self::command('get_state');
    }

    /**
     * @return array<string, mixed>
     */
    public static function play(): array
    {
        return self::command('play');
    }

    /**
     * @return array<string, mixed>
     */
    public static function pause(): array
    {
        return self::command('pause');
    }

    /**
     * @return array<string, mixed>
     */
    public static function next(): array
    {
        return self::command('next');
    }

    /**
     * @return array<string, mixed>
     */
    public static function seek(int $positionMs): array
    {
        return self::command('seek', ['position_ms' => $positionMs]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function volume(int $level): array
    {
        return self::command('set_volume', ['volume' => $level]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function addToQueue(string $providerTrackId): array
    {
        return self::command('add_to_queue', ['uri' => "spotify:track:{$providerTrackId}"]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function command(string $name, array $arguments = []): array
    {
        return ['type' => 'command', 'command' => $name, ...$arguments];
    }
}
