// @vitest-environment node
import {build} from 'vite';
import {cssTarget} from '../../../vite.css-target';
import {describe, expect, it} from 'vitest';

const deckSources = import.meta.glob<string>('../components/Dashboard/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});
const appCssEntry = 'virtual:app-css';

async function builtCss(input: string): Promise<string> {
    const result = await build({
        configFile: false,
        logLevel: 'silent',
        plugins: [
            {
                name: 'app-css-entry',
                resolveId: (id) => (id === appCssEntry ? `\0${appCssEntry}` : null),
                load: (id) =>
                    id === `\0${appCssEntry}`
                        ? "import '~tabler/dist/css/tabler.css'; import '/resources/sass/app.scss';"
                        : null,
            },
        ],
        resolve: {
            alias: {
                '~tabler': new URL('../../../node_modules/@tabler/core', import.meta.url).pathname,
            },
        },
        build: {
            write: false,
            cssMinify: 'lightningcss',
            cssTarget,
            rollupOptions: {input},
        },
    });
    const outputs = Array.isArray(result)
        ? result.flatMap((r) => ('output' in r ? r.output : []))
        : 'output' in result
          ? result.output
          : [];
    return outputs.map((o) => ('source' in o ? String(o.source) : '')).join('\n');
}

describe('design token defaults in the built CSS', () => {
    it('resolves colour defaults per theme scope, never once at :root', async () => {
        const css = await builtCss('resources/sass/_tokens.scss');

        expect(css).not.toMatch(/--mp-[a-z-]*:[^;}]*lightningcss/);
        for (const token of ['nav-background', 'surface', 'text', 'border']) {
            expect(css).not.toMatch(new RegExp(`(^|[},])\\s*:(root|host)[^{]*\\{[^}]*--mp-${token}\\s*:`));
        }
        expect(css).toMatch(/\[data-bs-theme=dark\][^{]*\{[^}]*--mp-default-surface:/);
    });

    it('leaves no frozen light/dark fallbacks anywhere in the app stylesheet', async () => {
        const css = await builtCss(appCssEntry);

        expect(css).not.toContain('--lightningcss-');
    });

    it('declares per-mode muted and text variants, and re-resolves secondary text in the dark scope', async () => {
        const css = await builtCss(appCssEntry);

        expect(css).toMatch(/\[data-bs-theme=dark\][^{]*\{[^}]*--mp-default-muted:/);
        expect(css).toMatch(/\[data-bs-theme=dark\][^{]*\{[^}]*--mp-default-primary-text:/);
        expect(css).toMatch(/\[data-bs-theme=dark\][^{]*\{[^}]*--tblr-secondary-color:/);
        expect(css).not.toMatch(/(^|[},])\s*:(root|host)[^{]*\{[^}]*--mp-(primary|muted|danger|warning|success)\s*:/);
    });

    it('self-hosts Inter instead of importing it from a CDN', async () => {
        const css = await builtCss(appCssEntry);

        expect(css).not.toContain('rsms.me');
        expect(css).toMatch(/font-family:\s*"?Inter Variable"?/);
        expect(css).toMatch(/font-display:\s*swap/);
    });
});

describe('dashboard deck component styles', () => {
    const deckComponents = [
        'PlayerUnit',
        'UpNextDeck',
        'DevicePicker',
        'SeekBar',
        'StartPlaybackPanel',
        'HealthGrid',
        'NeedsAttention',
        'QuickLinks',
    ];

    it.each(deckComponents)('%s uses tokens, not raw colour literals', (name) => {
        const source = deckSources[`../components/Dashboard/${name}.vue`];
        const style = source.slice(source.indexOf('<style'));

        expect(style).not.toMatch(/rgba?\(|#[0-9a-f]{3,8}\b/i);
        expect(style).not.toMatch(/color:[^;]*!important/);
    });
});
