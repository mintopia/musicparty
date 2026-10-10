<?php

namespace Tests\Fixtures\Playback;

use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\WebSocketConnection;

class StubReverbConnection extends Connection
{
    public int $disconnects = 0;

    public function __construct()
    {
        parent::__construct(
            new class implements WebSocketConnection
            {
                public function id(): string
                {
                    return 'stub';
                }

                public function send(mixed $message): void {}

                public function close(mixed $message = null): void {}
            },
            new Application('stub', 'key', 'secret', 60, 30, ['*'], 10_000),
            null,
        );
    }

    public function disconnect(): void
    {
        $this->disconnects++;
    }

    public function identifier(): string
    {
        return 'stub';
    }

    public function id(): string
    {
        return 'stub';
    }

    public function send(string $message): void {}

    public function control(string $type = 'ping'): void {}

    public function terminate(): void {}
}
