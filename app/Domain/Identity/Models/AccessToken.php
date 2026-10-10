<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Laravel\Sanctum\PersonalAccessToken;

#[Table(name: 'personal_access_tokens')]
class AccessToken extends PersonalAccessToken
{
    public const LAST_USED_RESOLUTION_SECONDS = 60;

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->isRedundantLastUsedWrite()) {
            $this->discardChanges();

            return true;
        }

        return parent::save($options);
    }

    private function isRedundantLastUsedWrite(): bool
    {
        $previous = $this->getOriginal('last_used_at');

        if (! $this->exists || $previous === null || array_keys($this->getDirty()) !== ['last_used_at']) {
            return false;
        }

        return $this->asDateTime($previous)->gt(now()->subSeconds(self::LAST_USED_RESOLUTION_SECONDS));
    }
}
