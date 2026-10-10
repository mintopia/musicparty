export const AA_THRESHOLD = 4.5;

const HEX = /^#([0-9a-f]{6})$/i;

const channel = (value) => {
    const s = value / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
};

export const luminance = (hex) => {
    if (typeof hex !== 'string' || !HEX.test(hex)) {
        return null;
    }
    const n = parseInt(hex.slice(1), 16);
    return 0.2126 * channel((n >> 16) & 255) + 0.7152 * channel((n >> 8) & 255) + 0.0722 * channel(n & 255);
};

export const contrastRatio = (a, b) => {
    const la = luminance(a);
    const lb = luminance(b);
    if (la === null || lb === null) {
        return null;
    }
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
};

const PAIRS = [
    ['text', 'background'],
    ['text', 'surface'],
];

export const contrastWarnings = (theme) => {
    const warnings = [];
    for (const scheme of ['light', 'dark']) {
        const colours = theme?.[scheme] ?? {};
        for (const [fg, bg] of PAIRS) {
            const ratio = contrastRatio(colours[fg], colours[bg]);
            if (ratio !== null && ratio < AA_THRESHOLD) {
                warnings.push({scheme, pair: `${fg}/${bg}`, ratio: Math.round(ratio * 100) / 100});
            }
        }
    }
    return warnings;
};

export const describeWarning = (warning) => {
    const [fg, bg] = warning.pair.split('/');
    const scheme = warning.scheme === 'dark' ? 'Dark' : 'Light';
    return `${scheme}: ${fg} on ${bg} ${Number(warning.ratio).toFixed(1)}:1, needs ${AA_THRESHOLD}:1`;
};
