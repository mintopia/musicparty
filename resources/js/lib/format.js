export const formatDuration = (ms) => {
    const total = Math.round((ms ?? 0) / 1000);
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};


export const requesterLabel = (entry) => {
    const name = entry?.requested_by?.name;
    return name ? `Requested by ${name}` : 'Fallback playlist';
};
