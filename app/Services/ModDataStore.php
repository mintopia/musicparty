<?php

namespace App\Services;

use App\Models\ModData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModDataStore
{
    public function get(Model $owner, string $modCode, string $key, mixed $default = null): mixed
    {
        $row = $this->query($owner, $modCode)->where('key', $key)->first();

        return $row === null ? $default : $row->value;
    }

    public function set(Model $owner, string $modCode, string $key, mixed $value): void
    {
        ModData::updateOrCreate(
            ['mod_code' => $modCode, 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'key' => $key],
            ['value' => $value],
        );
    }

    public function forget(Model $owner, string $modCode, string $key): void
    {
        $this->query($owner, $modCode)->where('key', $key)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(Model $owner, string $modCode): array
    {
        return $this->query($owner, $modCode)->pluck('value', 'key')->all();
    }

    /**
     * @return Builder<ModData>
     */
    protected function query(Model $owner, string $modCode): Builder
    {
        return ModData::query()
            ->where('mod_code', $modCode)
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey());
    }
}
