import type {InertiaForm} from '@inertiajs/vue3';

export interface PartyFormValues {
    name: string | null;
    country: string | null;
    timezone: string | null;
    backup_playlist_id: string | null;
    history_playlist_id: string | null;
    device_id: string | null;
    active: boolean;
    allow_requests: boolean;
    downvotes: boolean;
    weighted: boolean;
    show_qrcode: boolean;
    flag_requests: boolean;
    explicit: boolean;
    poll: boolean;
    force: boolean;
    min_song_length: number | null;
    max_song_length: number | null;
    no_repeat_interval: number | null;
    downvotes_per_hour: number | null;
    max_requests: number | null;
}

export interface NamedOption {
    id: string;
    name: string;
}

export function initialPartyForm(party: PartyFormValues, playlists: NamedOption[], defaultCountry: string) {
    const listed = playlists.some((playlist) => playlist.id === party.backup_playlist_id);

    return {
        name: party.name ?? '',
        country: party.country ?? defaultCountry,
        timezone: party.timezone ?? '',
        backup_playlist_id: listed ? (party.backup_playlist_id as string) : 'other',
        custom_backup_playlist_id: listed ? '' : (party.backup_playlist_id ?? ''),
        history_playlist_id: party.history_playlist_id ?? 'none',
        device_id: party.device_id ?? '',
        active: party.active,
        allow_requests: party.allow_requests,
        downvotes: party.downvotes,
        weighted: party.weighted,
        show_qrcode: party.show_qrcode,
        flag_requests: party.flag_requests,
        explicit: party.explicit,
        poll: party.poll,
        force: party.force,
        min_song_length: party.min_song_length ?? '',
        max_song_length: party.max_song_length ?? '',
        no_repeat_interval: party.no_repeat_interval ?? '',
        downvotes_per_hour: party.downvotes_per_hour ?? '',
        max_requests: party.max_requests ?? '',
    };
}

export type PartyFormData = ReturnType<typeof initialPartyForm> & {provider_id?: string};
export type PartyFormState = InertiaForm<PartyFormData>;
export type SwitchField = 'active' | 'allow_requests' | 'downvotes' | 'weighted' | 'show_qrcode' | 'flag_requests';
export type LimitField =
    'min_song_length' | 'max_song_length' | 'no_repeat_interval' | 'downvotes_per_hour' | 'max_requests';
