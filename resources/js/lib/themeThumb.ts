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
