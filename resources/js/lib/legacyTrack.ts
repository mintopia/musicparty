import {legacyFallbackName} from '@/lib/decoration';
import type {LegacyTrack, LegacyUpcomingSong, TrackAnnotation, TrackDecoration} from '@/types';

const mergedClasses = (track: LegacyTrack): string =>
    [...new Set([track.css_classes ?? '', track.decoration?.class ?? ''].join(' ').split(/\s+/).filter(Boolean))].join(
        ' ',
    );

/**
 * Presents a legacy queue payload as the TrackAnnotation that TrackRow renders.
 * The decoration's displayName is deliberately dropped: legacy payloads use it for the fallback label only.
 */
export function legacyToAnnotation(track: LegacyTrack | LegacyUpcomingSong): TrackAnnotation {
    const vote = 'vote' in track ? track.vote : null;
    const decoration: TrackDecoration = {
        label: track.decoration?.label,
        accent: track.decoration?.accent,
        class: mergedClasses(track) || undefined,
    };

    return {
        provider_id: 'legacy',
        id: String(track.id ?? track.provider_track_id ?? track.name),
        name: track.name,
        duration_ms: track.length,
        explicit: false,
        external_url: null,
        provider_name: '',
        album: {id: '', name: track.album.name, image_url: track.album.image_url || null},
        artists: track.artists.map((artist, index) => ({id: String(index), name: artist.name})),
        queue: {
            in_queue: true,
            upcoming_id: track.id ?? null,
            score: track.score,
            vote,
            requested_by: track.user,
        },
        eligibility: {allowed: true, outcome: 'allow', reason: null},
        decoration,
    };
}

export const legacyFallbackLabel = (track: LegacyTrack): string | undefined => legacyFallbackName(track) ?? undefined;
