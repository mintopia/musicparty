<?php

namespace App\Services\Dashboard;

use App\Exceptions\ProviderBackoffException;
use App\Exceptions\ProviderRelinkRequiredException;
use App\Models\Party;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Playback\PartyDashboardCache;
use App\Services\PlaybackControl;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PartyDevices
{
    public function __construct(private readonly PlaybackControl $control) {}

    /**
     * @return list<Device>
     */
    public function devices(Party $party): array
    {
        try {
            return Cache::remember(PartyDashboardCache::devicesKey($party), PartyDashboardCache::READ_SECONDS, fn (): array => $this->control->devices($party));
        } catch (ProviderBackoffException|ProviderRelinkRequiredException) {
            return [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Fetches the devices live, bypassing and refreshing the cache.
     *
     * @return list<array<string, mixed>>
     */
    public function refreshDevices(Party $party): array
    {
        $devices = $this->control->devices($party);
        Cache::put(PartyDashboardCache::devicesKey($party), $devices, PartyDashboardCache::READ_SECONDS);

        return array_map(fn (Device $device): array => $this->devicePresentation($party, $device), $devices);
    }

    /**
     * @return array{id: string|null, name: string, type: string, isActive: bool, isRestricted: bool, label: string, icon: string}
     */
    public function devicePresentation(Party $party, Device $device): array
    {
        $isEncore = $device->id !== null && $device->id === $party->recent_device_id && $party->playback_source === PlaybackSource::Encore;
        $base = $isEncore ? 'Encore' : ($device->type !== '' ? $device->type : 'Device');
        $label = match (true) {
            $device->isRestricted => "Can't be controlled remotely",
            $device->isActive => "{$base} · active now",
            default => $base,
        };

        return [
            'id' => $device->id,
            'name' => $device->name,
            'type' => $device->type,
            'isActive' => $device->isActive,
            'isRestricted' => $device->isRestricted,
            'label' => $label,
            'icon' => match ($device->type) {
                'Speaker' => 'ti-device-speaker',
                'Computer' => 'ti-device-laptop',
                'Smartphone' => 'ti-device-mobile',
                'TV', 'CastVideo' => 'ti-device-tv',
                default => 'ti-device-desktop',
            },
        ];
    }

    /**
     * @param  list<Device>  $usable
     */
    public function selectedDeviceId(Party $party, array $usable): ?string
    {
        foreach ($usable as $device) {
            if ($device->isActive) {
                return $device->id;
            }
        }
        foreach ([$party->device_id, $party->recent_device_id] as $preferred) {
            foreach ($usable as $device) {
                if ($preferred !== null && $device->id === $preferred) {
                    return $device->id;
                }
            }
        }

        return $usable[0]->id ?? null;
    }
}
