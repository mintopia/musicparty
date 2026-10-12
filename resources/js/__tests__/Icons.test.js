import {readFileSync, readdirSync, statSync} from 'node:fs';
import {join} from 'node:path';
import {describe, expect, it} from 'vitest';
import {iconPaths} from '../lib/icons';

const root = join(__dirname, '..');

const sourceFiles = (dir) =>
    readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) {
            return entry === '__tests__' ? [] : sourceFiles(path);
        }
        return /\.(vue|js)$/.test(entry) ? [path] : [];
    });

describe('icon map', () => {
    it('defines each icon once', () => {
        const source = readFileSync(join(root, 'lib/icons.js'), 'utf8');
        const keys = [...source.matchAll(/^ {4}'?([\w-]+)'?: \[/gm)].map((match) => match[1]);

        expect(keys.length).toBe(Object.keys(iconPaths).length);
        expect(new Set(keys).size).toBe(keys.length);
    });

    it('contains every icon used under resources/js', () => {
        const used = new Set();
        for (const file of sourceFiles(root)) {
            const source = readFileSync(file, 'utf8');
            for (const match of source.matchAll(/<Icon[^>]*?\sname="([\w-]+)"/g)) {
                used.add(match[1]);
            }
            for (const match of source.matchAll(/\bicon: '([\w-]+)'/g)) {
                used.add(match[1]);
            }
            for (const match of source.matchAll(/:name="[^"]*"/g)) {
                for (const literal of match[0].matchAll(/[?:]\s*'([\w-]+)'/g)) {
                    used.add(literal[1]);
                }
            }
        }

        expect([...used].filter((name) => !(name in iconPaths))).toEqual([]);
    });

    it.each(['musicSearch', 'playlist-add', 'brandSpotify', 'heart', 'previous'])('has the v2 icon %s', (name) => {
        expect(iconPaths[name]).toBeInstanceOf(Array);
    });
});
