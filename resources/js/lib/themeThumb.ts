export function themeThumbStyle(theme: {dark_mode: boolean; tokens: Record<string, string>}): Record<string, string> {
    const dark = theme.dark_mode;
    return {
        '--t-nav': theme.tokens['nav-background'] ?? '#182433',
        '--t-bg': dark ? '#101820' : '#f6f8fb',
        '--t-surf': theme.tokens.surface ?? (dark ? '#182433' : '#ffffff'),
        '--t-pri': theme.tokens.primary ?? '#0054a6',
        '--t-text': theme.tokens.text ?? (dark ? '#e6e7e9' : '#182433'),
    };
}

const HUES: [number, string][] = [
    [15, 'Red'],
    [45, 'Orange'],
    [70, 'Yellow'],
    [165, 'Green'],
    [195, 'Teal'],
    [255, 'Blue'],
    [290, 'Purple'],
    [335, 'Pink'],
    [360, 'Red'],
];

export function colourName(hex: string | undefined): string {
    const match = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex ?? '');
    if (!match) {
        return 'Blue';
    }
    const [r, g, b] = [match[1], match[2], match[3]].map((part) => parseInt(part, 16) / 255);
    const max = Math.max(r, g, b);
    const delta = max - Math.min(r, g, b);
    if (delta < 0.08) {
        return 'Grey';
    }
    const hue =
        (60 * (max === r ? ((g - b) / delta) % 6 : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4) + 360) % 360;
    return HUES.find(([limit]) => hue < limit)?.[1] ?? 'Red';
}
