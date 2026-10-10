<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Setting;
use App\Domain\Admin\SiteSettings;
use App\Domain\Identity\Models\User;
use App\Enums\SettingType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateSiteSettings
{
    public function __construct(
        private readonly SiteSettings $site,
        private readonly RecordAdminAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $admin, array $data): SiteSettings
    {
        $changed = [];
        $disk = Storage::disk(SiteSettings::disk());

        DB::transaction(function () use ($data, $disk, &$changed): void {
            foreach (SiteSettings::FIELDS as $field => $definition) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                $value = $data[$field];
                $previous = $this->site->get($field);

                if ($value instanceof UploadedFile) {
                    $value = $value->store('site', ['disk' => SiteSettings::disk()]);
                    if ($previous !== null) {
                        $disk->delete($previous);
                    }
                } elseif (in_array($field, SiteSettings::FILE_FIELDS, true)) {
                    continue;
                }

                $value = is_string($value) && $value !== '' ? $value : null;
                if ($value === $previous) {
                    continue;
                }

                $setting = Setting::query()->where('code', $definition['code'])->first() ?? new Setting;
                $setting->forceFill([
                    'code' => $definition['code'],
                    'name' => $definition['label'],
                    'encrypted' => false,
                    'hidden' => false,
                    'validation' => '',
                    'type' => SettingType::stString,
                    'value' => $value,
                ])->save();
                $changed[] = $field;
            }
        });

        if ($changed !== []) {
            $this->audit->handle($admin, 'settings.updated', null, ['fields' => implode(',', $changed)]);
        }

        return $this->site;
    }
}
