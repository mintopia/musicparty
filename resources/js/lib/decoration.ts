import type {LegacyTrack} from '@/types';

export const legacyRowClasses = (track: LegacyTrack): string => {
    const classes = (track.css_classes ?? '').split(/\s+/).filter(Boolean);

    for (const extra of (track.decoration?.class ?? '').split(/\s+/).filter(Boolean)) {
        if (!classes.includes(extra)) {
            classes.push(extra);
        }
    }

    return ['row', 'g-2', 'align-items-center', ...classes].join(' ');
};

export const legacyFallbackName = (track: LegacyTrack): string | null =>
    track.decoration?.displayName ?? track.fallback_name;

export const legacyAccentStyle = (track: LegacyTrack): Record<string, string> | undefined =>
    track.decoration?.accent ? {borderLeft: `4px solid ${track.decoration.accent}`} : undefined;
