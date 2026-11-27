export interface SettingField {
    code: string;
    name: string;
    description: string | null;
    type: 'string' | 'boolean';
    required: boolean;
    value: string | boolean;
}

export interface SocialProviderRow {
    id: number;
    code: string;
    name: string;
    supports_auth: boolean;
    enabled: boolean;
    auth_enabled: boolean;
    needs_secret: boolean;
    login_return_url: string;
    linked_account_url: string;
}

export const copy: Record<string, {label: string; hint: string}> = {
    name: {label: 'Site name', hint: 'Shown in the sidebar, the browser tab and emails.'},
    terms: {label: 'Terms and Conditions URL', hint: 'Guests must agree to this on first login. Leave empty to skip.'},
    privacypolicy: {label: 'Privacy Policy URL', hint: 'Linked from the login page.'},
    defaultparty: {
        label: 'Default party code',
        hint: 'Four letters, no digits. Anyone who opens the site goes straight to this party.',
    },
    timezone: {
        label: 'Timezone',
        hint: 'Used by time-based Mods and Scheduled Requests. Parties can override it.',
    },
    'logo-light': {
        label: 'Logo for dark backgrounds',
        hint: 'A light version, used in the sidebar and on the login page.',
    },
    'logo-dark': {label: 'Logo for light backgrounds', hint: 'A dark version, used in emails and on light pages.'},
    'cover-image': {label: 'Login cover image', hint: 'The large photo beside the Discord button.'},
    favicon: {label: 'Favicon', hint: 'Shown in the browser tab. Square, at least 64 pixels.'},
    'provider-spotify-enabled': {label: 'Spotify', hint: 'Lets parties use Spotify as their Music Provider.'},
    'provider-fake-enabled': {
        label: 'Fake Music Provider',
        hint: 'For local development only. Turn off on a real instance.',
    },
};

export function labelFor(setting: SettingField): string {
    return copy[setting.code]?.label ?? setting.name;
}

export function hintFor(setting: SettingField): string | undefined {
    return copy[setting.code]?.hint ?? setting.description ?? undefined;
}
