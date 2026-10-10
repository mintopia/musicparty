<?php

namespace Tests\Fixtures\Mods;

use RuntimeException;

class ListenerMod extends BaseFixtureMod
{
    /** @var list<object> */
    public array $received = [];

    /**
     * @param  list<class-string>  $events
     */
    public function __construct(string $id = 'listener', private readonly array $events = [], private readonly bool $failing = false)
    {
        parent::__construct($id);
    }

    public function listeners(): array
    {
        $listeners = [];

        foreach ($this->events as $event) {
            $listeners[$event] = function (object $received) {
                if ($this->failing) {
                    throw new RuntimeException('listener exploded');
                }

                $this->received[] = $received;
            };
        }

        return $listeners;
    }
}
