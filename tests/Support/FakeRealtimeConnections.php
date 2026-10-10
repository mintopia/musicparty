<?php

namespace Tests\Support;

use App\Support\Realtime\Contracts\RealtimeConnections;
use Closure;
use PHPUnit\Framework\Assert;

class FakeRealtimeConnections implements RealtimeConnections
{
    /** @var list<int> */
    public array $terminated = [];

    public ?Closure $onTerminate = null;

    public function terminateUser(int $userId): void
    {
        if ($this->onTerminate !== null) {
            ($this->onTerminate)($userId);
        }

        $this->terminated[] = $userId;
    }

    public function assertTerminated(int $userId): void
    {
        Assert::assertContains($userId, $this->terminated);
    }

    public function assertTerminatedOnce(int $userId): void
    {
        Assert::assertCount(1, array_keys($this->terminated, $userId, true));
    }

    public function assertNothingTerminated(): void
    {
        Assert::assertSame([], $this->terminated);
    }
}
