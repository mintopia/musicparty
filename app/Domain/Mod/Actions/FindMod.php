<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\ModRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class FindMod
{
    public function __construct(private ModRegistry $registry) {}

    public function __invoke(string $modId): Mod
    {
        return $this->registry->find($modId) ?? throw new NotFoundHttpException('Mod not found.');
    }
}
