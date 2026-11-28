<?php

namespace App\Services;

use App\Models\ModData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ModDataStore
{
    /** @var array<string, array<int, array<string, mixed>>> */
    protected array $preloaded = [];

    /**
     * Loads one mod's data for many owners in a single query so per-owner reads need no further queries.
     *
     * @param  Collection<int, covariant Model>  $owners
     */
    public function preload(Collection $owners, string $modCode): void
    {
        $first = $owners->first();
        if ($first === null) {
            return;
        }
        $type = $first->getMorphClass();
        $ids = $owners->map(fn (Model $owner): mixed => $owner->getKey())->all();
        $loaded = array_fill_keys($ids, []);
        foreach (ModData::query()->where('mod_code', $modCode)->where('owner_type', $type)->whereIn('owner_id', $ids)->get() as $row) {
            $loaded[$row->owner_id][$row->key] = $row->value;
        }
        foreach ($loaded as $id => $data) {
            $this->preloaded[$this->preloadKey($type, $modCode)][$id] = $data;
        }
    }

    protected function preloadKey(string $type, string $modCode): string
    {
        return "{$type}:{$modCode}";
    }

    protected function unload(Model $owner, string $modCode): void
    {
        unset($this->preloaded[$this->preloadKey($owner->getMorphClass(), $modCode)][$owner->getKey()]);
    }

    public function get(Model $owner, string $modCode, string $key, mixed $default = null): mixed
    {
        $cached = $this->preloaded[$this->preloadKey($owner->getMorphClass(), $modCode)][$owner->getKey()] ?? null;
        if ($cached !== null) {
            return array_key_exists($key, $cached) ? $cached[$key] : $default;
        }

        $row = $this->query($owner, $modCode)->where('key', $key)->first();

        if ($row instanceof ModData) {
            return $row->value;
        }

        return $default;
    }

    public function set(Model $owner, string $modCode, string $key, mixed $value): void
    {
        $this->unload($owner, $modCode);
        ModData::updateOrCreate(
            ['mod_code' => $modCode, 'owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'key' => $key],
            ['value' => $value],
        );
    }

    public function forget(Model $owner, string $modCode, string $key): void
    {
        $this->unload($owner, $modCode);
        $this->query($owner, $modCode)->where('key', $key)->delete();
    }

    public function purge(Model $owner): void
    {
        ModData::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(Model $owner, string $modCode): array
    {
        $cached = $this->preloaded[$this->preloadKey($owner->getMorphClass(), $modCode)][$owner->getKey()] ?? null;
        if ($cached !== null) {
            return $cached;
        }

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
