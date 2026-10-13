// @vitest-environment node
import {build} from 'vite';
import {cssTarget} from '../../../vite.css-target';
import {describe, expect, it} from 'vitest';

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
                '~bootstrap': new URL('../../../node_modules/bootstrap', import.meta.url).pathname,
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
});
