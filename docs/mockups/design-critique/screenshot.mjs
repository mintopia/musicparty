#!/usr/bin/env node
// Captures every [data-frame] in every mockup page to screenshots/<page>-<frame-id>.png at 1:1.
// Usage: node screenshot.mjs [page.html ...]   (default: every *.html in this folder)
// Drives /usr/bin/google-chrome headless over the DevTools protocol; needs Node 22+ (global WebSocket).
import {spawn} from 'node:child_process';
import {createServer} from 'node:http';
import {mkdtempSync, readdirSync, readFileSync, rmSync, mkdirSync, writeFileSync, existsSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, extname, basename, dirname, resolve, sep} from 'node:path';
import {fileURLToPath} from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const outDir = join(root, 'screenshots');
const chromePath = process.env.CHROME ?? '/usr/bin/google-chrome';
const types = {'.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.woff2': 'font/woff2'};

const pages = process.argv.length > 2
    ? process.argv.slice(2).map((p) => basename(p))
    : readdirSync(root).filter((f) => f.endsWith('.html') && f !== 'index.html').sort();

const server = createServer((req, res) => {
    const path = resolve(root, '.' + decodeURIComponent(new URL(req.url, 'http://x').pathname));
    if (!path.startsWith(root + sep) || !existsSync(path)) {
        res.writeHead(404).end();
        return;
    }
    res.writeHead(200, {'content-type': types[extname(path)] ?? 'application/octet-stream'});
    res.end(readFileSync(path));
});
await new Promise((ok) => server.listen(0, '127.0.0.1', ok));
const origin = `http://127.0.0.1:${server.address().port}`;

const profile = mkdtempSync(join(tmpdir(), 'mockshot-'));
const chrome = spawn(chromePath, ['--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`,
    '--no-first-run', '--hide-scrollbars', '--force-color-profile=srgb', 'about:blank'], {stdio: ['ignore', 'ignore', 'pipe']});
const wsUrl = await new Promise((ok, fail) => {
    let buf = '';
    chrome.stderr.on('data', (d) => {
        buf += d;
        const m = buf.match(/DevTools listening on (ws:\/\/\S+)/);
        if (m) ok(m[1]);
    });
    chrome.on('exit', () => fail(new Error('Chrome exited before DevTools was ready:\n' + buf)));
});

const ws = new WebSocket(wsUrl);
await new Promise((ok) => ws.addEventListener('open', ok, {once: true}));
let nextId = 0;
const waiting = new Map();
const listeners = [];
ws.addEventListener('message', ({data}) => {
    const msg = JSON.parse(data);
    if (msg.id && waiting.has(msg.id)) {
        const {ok, fail} = waiting.get(msg.id);
        waiting.delete(msg.id);
        msg.error ? fail(new Error(msg.error.message)) : ok(msg.result);
    } else if (msg.method) {
        listeners.forEach((fn) => fn(msg));
    }
});
const send = (method, params = {}, sessionId) => new Promise((ok, fail) => {
    const id = ++nextId;
    waiting.set(id, {ok, fail});
    ws.send(JSON.stringify({id, method, params, sessionId}));
});
const once = (method, sessionId) => new Promise((ok) => {
    const fn = (msg) => {
        if (msg.method === method && msg.sessionId === sessionId) {
            listeners.splice(listeners.indexOf(fn), 1);
            ok(msg.params);
        }
    };
    listeners.push(fn);
});

const {targetId} = await send('Target.createTarget', {url: 'about:blank'});
const {sessionId} = await send('Target.attachToTarget', {targetId, flatten: true});
await send('Page.enable', {}, sessionId);
await send('Emulation.setDeviceMetricsOverride', {width: 2100, height: 1200, deviceScaleFactor: 1, mobile: false}, sessionId);

mkdirSync(outDir, {recursive: true});
let failures = 0;
for (const page of pages) {
    const loaded = once('Page.loadEventFired', sessionId);
    await send('Page.navigate', {url: `${origin}/${page}?capture`}, sessionId);
    await loaded;
    const {result, exceptionDetails} = await send('Runtime.evaluate', {
        awaitPromise: true,
        returnByValue: true,
        expression: `(window.mockReady ?? Promise.resolve()).then(() => [...document.querySelectorAll('[data-frame]')]
            .map((f) => { const el = f.querySelector('.mock-screen') ?? f; const r = el.getBoundingClientRect();
                return {id: f.id, x: r.left + scrollX, y: r.top + scrollY, width: r.width, height: r.height}; }))`,
    }, sessionId);
    if (exceptionDetails) {
        console.error(`${page}: ${exceptionDetails.exception?.description ?? exceptionDetails.text}`);
        failures++;
        continue;
    }
    for (const frame of result.value) {
        if (!frame.id) {
            console.error(`${page}: a [data-frame] has no id, skipped`);
            failures++;
            continue;
        }
        const clip = {x: frame.x, y: frame.y, width: Math.ceil(frame.width), height: Math.ceil(frame.height), scale: 1};
        const {data} = await send('Page.captureScreenshot', {format: 'png', clip, captureBeyondViewport: true}, sessionId);
        const file = join(outDir, `${page.replace(/\.html$/, '')}-${frame.id}.png`);
        writeFileSync(file, Buffer.from(data, 'base64'));
        console.log(`${file.slice(root.length + 1)}  ${clip.width}×${clip.height}`);
    }
}

ws.close();
chrome.kill();
server.close();
rmSync(profile, {recursive: true, force: true});
process.exit(failures ? 1 : 0);
