import type {ModSetting} from '@/types';

export const partyModsUrl = (code: string): string => `/parties/${code}/mods`;
export const adminModsUrl = (): string => '/admin/mods';

export type ModSettingValue = string | number | boolean | null;

export function initialSettingValue(setting: ModSetting): ModSettingValue {
    if (setting.type === 'secret') {
        return '';
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
