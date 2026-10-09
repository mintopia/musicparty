export const formatDuration = (ms) => {
    const total = Math.round((ms ?? 0) / 1000);
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
};
