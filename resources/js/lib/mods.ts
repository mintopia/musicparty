import type {ModSetting, PartyMod} from '@/types';

export interface ModDraft {
    enabled: boolean;
    settings: Record<string, ModSettingValue>;
}

const MOD_GROUP_BY_CODE: Record<string, string> = {
    'ai-request-review': 'Moderation',
    trust: 'Moderation',
    whamageddon: 'Fun',
};

export interface ModStat {
    label: string;
    value: string;
    tone?: 'success' | 'warning';
}

export interface ModStatus {
    label: string;
    tone: 'success' | 'warning' | 'muted';
}

const MOD_ICON_BY_CODE: Record<string, string> = {
    'ai-request-review': 'ti-robot',
    trust: 'ti-heart-handshake',
    whamageddon: 'ti-snowflake',
};

export const AI_REVIEW_MOD = 'ai-request-review';

export const modIcon = (code: string): string => MOD_ICON_BY_CODE[code] ?? 'ti-puzzle';

const MOD_GROUP_ORDER = ['Moderation', 'Fun', 'Other'];

export function groupMods<T extends {code: string}>(mods: T[]): {name: string; mods: T[]}[] {
    const groups = new Map<string, T[]>();
    for (const mod of mods) {
        const name = MOD_GROUP_BY_CODE[mod.code] ?? 'Other';
        groups.set(name, [...(groups.get(name) ?? []), mod]);
    }
    return MOD_GROUP_ORDER.filter((name) => groups.has(name)).map((name) => ({name, mods: groups.get(name) ?? []}));
}

export function initialModDraft(mod: PartyMod): ModDraft {
    return {
        enabled: mod.enabled,
        settings: Object.fromEntries(mod.settings.map((s) => [s.code, initialSettingValue(s)])),
    };
}

export function cloneDraft(draft: ModDraft): ModDraft {
    return JSON.parse(JSON.stringify(draft)) as ModDraft;
}

export function isModDirty(draft: ModDraft, baseline: ModDraft): boolean {
    return JSON.stringify(draft) !== JSON.stringify(baseline);
}

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

export function settingByCode(settings: ModSetting[], code: string): ModSetting | undefined {
    return settings.find((s) => s.code === code);
}

export function hasSavedApiKey(settings: ModSetting[]): boolean {
    return settingByCode(settings, 'api_key')?.has_value === true;
}

export function aiReviewStats(settings: ModSetting[], values: Record<string, ModSettingValue>): ModStat[] {
    const driver = settingByCode(settings, 'driver');
    const engine = driver?.options?.[String(values.driver ?? '')] ?? '';
    const saved = hasSavedApiKey(settings);
    return [
        {label: 'Engine', value: engine.replace(/\s*\(.*\)$/, '').trim() || 'Not set'},
        saved
            ? {label: 'API key', value: 'Saved', tone: 'success'}
            : {label: 'API key', value: 'Not set', tone: 'warning'},
    ];
}
