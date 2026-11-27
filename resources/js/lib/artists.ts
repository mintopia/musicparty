import type {LegacyArtist} from '@/types';

export function combineArtists(artists: LegacyArtist[] | undefined): string {
    return (artists ?? []).map((artist) => artist.name).join(', ');
}
