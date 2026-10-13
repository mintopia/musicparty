// @vitest-environment node
import {build} from 'vite';
import path from 'path';
import {describe, expect, it} from 'vitest';

async function builtTokensCss(): Promise<string> {
    const result = await build({
        configFile: false,
        logLevel: 'silent',
        root: path.resolve(import.meta.dirname, '../../..'),
        build: {
            write: false,
            cssMinify: 'lightningcss',
            rollupOptions: {input: 'resources/sass/_tokens.scss'},
        },
    });
    const outputs = Array.isArray(result) ? result.flatMap((r) => ('output' in r ? r.output : [])) : 'output' in result ? result.output : [];
    return outputs.map((o) => ('source' in o ? String(o.source) : '')).join('\n');
}

describe('design token defaults in the built CSS', () => {
    it('resolves colour defaults per theme scope, never once at :root', async () => {
        const css = await builtTokensCss();

        expect(css).not.toMatch(/--mp-[a-z-]*:[^;}]*lightningcss/);
        for (const token of ['nav-background', 'surface', 'text', 'border']) {
            expect(css).not.toMatch(new RegExp(`(^|[},])\\s*:(root|host)[^{]*\\{[^}]*--mp-${token}\\s*:`));
        }
        expect(css).toMatch(/\[data-bs-theme=dark\][^{]*\{[^}]*--mp-default-surface:/);
    });
});
