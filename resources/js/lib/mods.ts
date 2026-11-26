import type {ModSetting} from '@/types';

export const partyModsUrl = (code: string): string => `/parties/${code}/mods`;
export const adminModsUrl = (): string => '/admin/mods';
export const partyModUrl = (code: string, mod: string): string => `/parties/${code}/mods/${mod}`;
export const adminModUrl = (mod: string): string => `/admin/mods/${mod}`;
export const partyModUsersUrl = (code: string): string => `/parties/${code}/mods/users`;
export const adminModUsersUrl = (): string => '/admin/mods/users';

export type ModSettingValue = string | number | boolean | null;

export function initialSettingValue(setting: ModSetting): ModSettingValue {
    if (setting.type === 'secret') {
        return '';
    }
    if (setting.type === 'user') {
        return setting.value === 0 || setting.value === null || setting.value === undefined
            ? null
            : (setting.value as number);
    }
    const raw = setting.has_value ? setting.value : setting.default;
    if (raw === undefined || raw === null) {
        return setting.type === 'boolean' ? false : setting.type === 'string' ? '' : null;
    }
    return raw as ModSettingValue;
}

export function submitSettingValue(setting: ModSetting, value: ModSettingValue): ModSettingValue {
    if (setting.type === 'secret') {
        return typeof value === 'string' && value !== '' ? value : null;
    }
    if (value === '' && setting.type !== 'string') {
        return null;
    }
    return value;
}

export function buildSettings(
    settings: ModSetting[],
    values: Record<string, ModSettingValue>,
): Record<string, ModSettingValue> {
    return Object.fromEntries(settings.map((s) => [s.code, submitSettingValue(s, values[s.code] ?? null)]));
}
