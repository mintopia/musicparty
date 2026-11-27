<?php

namespace App\Services\Dashboard;

use App\Models\SocialProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Throwable;

/**
 * Runs the checks behind the Admin dashboard's System health card.
 *
 * @phpstan-type Check array{key: string, label: string, status: 'ok'|'warn'|'error', detail: string, link: string|null}
 */
class SystemHealth
{
    public const string SCHEDULER_HEARTBEAT_KEY = 'admin:scheduler-heartbeat';

    private const int SCHEDULER_STALE_SECONDS = 180;

    public function __construct(
        protected MasterSupervisorRepository $masters,
        protected SupervisorRepository $supervisors,
        protected WorkloadRepository $workload,
        protected ReverbStatus $reverb,
    ) {}

    /**
     * @return list<Check>
     */
    public function checks(): array
    {
        return [$this->workers(), $this->scheduler(), $this->reverb(), $this->redis(), $this->database(), $this->spotifyCredentials()];
    }

    /**
     * @return Check
     */
    protected function workers(): array
    {
        $link = route('horizon.index');

        try {
            $masters = $this->masters->all();
            $workers = collect($this->supervisors->all())->sum(fn ($supervisor): int => (int) array_sum((array) ($supervisor->processes ?? [])));
            $waiting = collect($this->workload->get())->sum(fn ($queue): int => (int) $queue['length']);
        } catch (Throwable) {
            return $this->check('workers', 'Queue workers', 'error', 'Horizon cannot be reached, so queued jobs may not be running.', $link);
        }

        if ($masters === []) {
            return $this->check('workers', 'Queue workers', 'error', 'Horizon is not running, so queued jobs are not being processed.', $link);
        }
        if (collect($masters)->contains(fn ($master): bool => ($master->status ?? null) === 'paused')) {
            return $this->check('workers', 'Queue workers', 'warn', 'Horizon is paused, so queued jobs are waiting.', $link);
        }

        return $this->check('workers', 'Queue workers', 'ok', "Horizon is running with {$workers} ".($workers === 1 ? 'worker' : 'workers').", {$waiting} waiting ".($waiting === 1 ? 'job' : 'jobs').'.', $link);
    }

    /**
     * @return Check
     */
    protected function scheduler(): array
    {
        $beat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);
        if (! is_int($beat)) {
            return $this->check('scheduler', 'Scheduler', 'error', 'The scheduler has not reported in yet. Check that the scheduler is running.');
        }
        $ago = now()->createFromTimestamp($beat)->diffForHumans();
        if (now()->getTimestamp() - $beat > self::SCHEDULER_STALE_SECONDS) {
            return $this->check('scheduler', 'Scheduler', 'error', "Last ran {$ago}. Check that the scheduler is running.");
        }

        return $this->check('scheduler', 'Scheduler', 'ok', "Last ran {$ago}.");
    }

    /**
     * @return Check
     */
    protected function reverb(): array
    {
        $link = route('pulse');

        return $this->reverb->reachable()
            ? $this->check('reverb', 'Reverb', 'ok', 'Reverb is accepting connections.', $link)
            : $this->check('reverb', 'Reverb', 'error', 'Reverb is not answering, so live updates and Players cannot connect.', $link);
    }

    /**
     * @return Check
     */
    protected function redis(): array
    {
        try {
            $memory = Redis::connection()->command('info', ['memory']);
            $used = is_array($memory) ? (string) ($memory['used_memory_human'] ?? $memory['Memory']['used_memory_human'] ?? '') : '';
        } catch (Throwable) {
            return $this->check('redis', 'Redis', 'error', 'Redis is not answering, so queues, caching and live state are down.');
        }

        return $this->check('redis', 'Redis', 'ok', 'Connected'.($used !== '' ? ", {$used} in use." : '.'));
    }

    /**
     * @return Check
     */
    protected function database(): array
    {
        $start = hrtime(true);
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return $this->check('database', 'Database', 'error', 'The database is not answering.');
        }
        $ms = max(1, (int) round((hrtime(true) - $start) / 1_000_000));

        return $this->check('database', 'Database', 'ok', "Connected, answers in {$ms} ms.");
    }

    /**
     * @return Check
     */
    protected function spotifyCredentials(): array
    {
        $provider = SocialProvider::query()->where('code', 'spotify')->with('settings')->first();
        $link = $provider === null ? null : route('admin.settings.socialproviders.edit', $provider);
        $values = $provider === null ? collect() : $provider->settings->pluck('value', 'code');
        $missing = collect(['client_id' => 'client ID', 'client_secret' => 'client secret'])
            ->filter(fn (string $name, string $code): bool => trim((string) $values->get($code)) === '')
            ->values()
            ->all();
        if ($missing !== []) {
            return $this->check('spotify', 'Spotify app credentials', 'error', 'Missing '.implode(' and ', $missing).', so nobody can link Spotify.', $link);
        }

        return $this->check('spotify', 'Spotify app credentials', 'ok', 'Client ID and secret are set.');
    }

    /**
     * @return list<string>
     */
    public function missingSettings(SocialProvider $provider): array
    {
        $missing = [];
        foreach ($provider->settings as $setting) {
            if (str_contains((string) $setting->validation, 'required') && trim((string) $setting->value) === '') {
                $missing[] = strtolower($setting->name);
            }
        }

        return $missing;
    }

    /**
     * @return Check
     */
    private function check(string $key, string $label, string $status, string $detail, ?string $link = null): array
    {
        /** @var 'ok'|'warn'|'error' $status */
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail, 'link' => $link];
    }
}
