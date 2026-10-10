export const estimateQueue = ({startedAt, nowPlaying, upNext, queue, mode, now}) => {
    const upcoming = [upNext, ...queue].filter((entry) => entry);
    const totalMs = upcoming.reduce((sum, entry) => sum + (entry.track.duration_ms ?? 0), 0);
    const empty = {upNextAt: null, totalMs, startsAt: {}};

    if (upNext === null || upNext === undefined) {
        return empty;
    }

    let cursor = now;
    if (nowPlaying) {
        const started = startedAt ? new Date(startedAt).getTime() : NaN;
        if (Number.isNaN(started)) {
            return empty;
        }
        cursor = Math.max(now, started + (nowPlaying.track.duration_ms ?? 0));
    }

    const startsAt = {};
    for (const entry of upcoming) {
        startsAt[entry.id] = cursor;
        cursor += entry.track.duration_ms ?? 0;
    }

    return {
        upNextAt: startsAt[upNext.id],
        totalMs,
        startsAt: mode === 'deterministic' ? startsAt : {},
    };
};
