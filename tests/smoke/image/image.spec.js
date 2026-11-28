'use strict';

const {test, expect} = require('@playwright/test');
const {execFileSync} = require('node:child_process');

const appUrl = process.env.SMOKE_APP_URL;
const oauthUrl = process.env.SMOKE_OAUTH_URL;
const appHost = new URL(appUrl).hostname;
const allowedHosts = new Set([appHost, new URL(oauthUrl).hostname]);

function artisan(code) {
    return execFileSync('docker', ['compose', '-p', 'mp-image-smoke', 'exec', '-T', 'app', 'php', 'artisan', 'tinker', '--execute', code], {
        encoding: 'utf8',
        env: {...process.env, SMOKE_APP_KEY: 'unused', SMOKE_APP_URL: appUrl},
    });
}

function watchConsole(page, errors) {
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 400) {
            errors.push(`${response.status()} ${response.url()}`);
        }
    });
    page.on('pageerror', (error) => errors.push(String(error)));
}

async function login(page) {
    await page.goto('/login');
    await page.locator('a[href*="/login/laravelpassport"]').first().click();
    await page.waitForURL((url) => url.hostname === appHost && url.pathname !== '/login' && (!url.pathname.startsWith('/login/') || url.pathname === '/login/signup'));
}

test('pages and bundles carry no localhost or foreign absolute URLs', async ({page}) => {
    const errors = [];
    const bodies = [];
    watchConsole(page, errors);
    page.on('response', async (response) => {
        const type = response.headers()['content-type'] || '';
        if (new URL(response.url()).hostname === appHost && /html|javascript|css|json/.test(type)) {
            bodies.push({url: response.url(), text: await response.text().catch(() => '')});
        }
    });

    for (const path of ['/', '/login']) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
    }

    const offenders = [];
    for (const {url, text: raw} of bodies) {
        const text = raw.replaceAll('typeof window>`u`?`http://localhost`', '');
        if (/localhost|127\.0\.0\.1/.test(text)) {
            offenders.push(`${url}: localhost`);
        }
        for (const match of text.matchAll(/https?:\/\/([a-z0-9.-]+)/gi)) {
            const host = match[1].toLowerCase();
            const known = allowedHosts.has(host) || /(^|\.)(w3\.org|example\.com|example\.org|schema\.org|reactjs\.org|vuejs\.org|googleapis\.com|gstatic\.com|github\.com|spotify\.com|scdn\.co|discord\.com|discordapp\.com)$/.test(host);
            if (!known && /\.(js|css)$|\/$/.test(new URL(url).pathname) === false) {
                offenders.push(`${url}: ${host}`);
            }
        }
    }

    expect(offenders).toEqual([]);
    expect(errors).toEqual([]);
});

test('a vote from a second browser reaches the first over the websocket', async ({browser}) => {
    const errors = [];
    const alice = await browser.newContext();
    const bob = await browser.newContext();
    const alicePage = await alice.newPage();
    const bobPage = await bob.newPage();
    watchConsole(alicePage, errors);

    await login(alicePage);
    await login(bobPage);

    artisan(`
        \\Illuminate\\Database\\Eloquent\\Model::unguard();
        \\App\\Models\\User::query()->update(['first_login' => false]);
        $owner = \\App\\Models\\User::orderBy('id')->first();
        $party = \\App\\Models\\Party::firstOrCreate(['code' => 'SMKE'], ['name' => 'Smoke Party', 'user_id' => $owner->id, 'provider_id' => 'spotify']);
        $album = \\App\\Models\\Album::firstOrCreate(['provider_id' => 'spotify', 'provider_album_id' => 'smokealbum'], ['name' => 'Smoke Album', 'image_url' => 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7']);
        $song = \\App\\Models\\Song::firstOrCreate(['provider_id' => 'spotify', 'provider_track_id' => 'smoketrack'], ['name' => 'Smoke Track', 'length' => 180000, 'album_id' => $album->id]);
        \\App\\Models\\UpcomingSong::firstOrCreate(['party_id' => $party->id, 'song_id' => $song->id], ['user_id' => $owner->id]);
    `);

    await alicePage.goto('/parties/SMKE');
    const score = alicePage.locator('[data-test="score"]').first();
    await expect(score).toBeVisible();
    const before = Number((await score.textContent()).trim());

    await bobPage.goto('/parties/SMKE');
    await bobPage.locator('[data-test="heart"], [data-test="upvote"]').first().click();

    await expect(score).toHaveText(String(before + 1), {timeout: 20000});
    expect(errors).toEqual([]);
});
