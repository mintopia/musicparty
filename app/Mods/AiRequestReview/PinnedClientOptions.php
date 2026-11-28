<?php

namespace App\Mods\AiRequestReview;

class PinnedClientOptions
{
    /** @var array<string, mixed> */
    private array $options = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function use(array $options): void
    {
        $this->options = $options;
    }

    public function clear(): void
    {
        $this->options = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return $this->options;
    }
}
