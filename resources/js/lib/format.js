export const formatDuration = (ms) => {
    const total = Math.round((ms ?? 0) / 1000);
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};


export const requesterLabel = (entry) => {
    const name = entry?.requested_by?.name;
    return name ? `Requested by ${name}` : 'Fallback playlist';
};

export const formatPlayedAt = (iso, now = Date.now()) => {
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) {
        return '';
    }
    const seconds = Math.max(0, Math.round((now - then) / 1000));
    const units = [[86400, 'day'], [3600, 'hour'], [60, 'minute']];
    for (const [size, unit] of units) {
        if (seconds >= size) {
            const count = Math.floor(seconds / size);
            return `${count} ${unit}${count === 1 ? '' : 's'} ago`;
        }
    }
    return `${seconds} ${seconds === 1 ? 'second' : 'seconds'} ago`;
};
