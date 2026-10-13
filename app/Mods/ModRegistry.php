<?php

namespace App\Mods;

use App\Mods\Contracts\Mod;
use Illuminate\Support\ServiceProvider;

class ModRegistry
{
    /** @var array<string, Mod> */
    protected array $mods = [];

    /** @var list<class-string<ServiceProvider>> */
    protected array $providers = [];

    public function __construct(protected string $path) {}

    /**
     * Mods are discovered by convention: app/Mods/<Name>/<Name>Mod.php is the manifest and
     * app/Mods/<Name>/<Name>ServiceProvider.php the provider. The scan runs once per process.
     */
    public function discover(): void
    {
        $this->mods = [];
        $this->providers = [];

        foreach (glob($this->path.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            $manifest = "App\\Mods\\{$name}\\{$name}Mod";
            if (! is_file("{$directory}/{$name}Mod.php") || ! is_a($manifest, Mod::class, true)) {
                continue;
            }
            $mod = new $manifest;
            $this->mods[$mod->code()] = $mod;

            $provider = "App\\Mods\\{$name}\\{$name}ServiceProvider";
            if (is_file("{$directory}/{$name}ServiceProvider.php") && is_a($provider, ServiceProvider::class, true)) {
                $this->providers[] = $provider;
            }
        }
    }

    /** @return array<string, Mod> */
    public function all(): array
    {
        return $this->mods;
    }

    public function find(string $code): ?Mod
    {
        return $this->mods[$code] ?? null;
    }

    /** @return list<class-string<ServiceProvider>> */
    public function providers(): array
    {
        return $this->providers;
    }
}
