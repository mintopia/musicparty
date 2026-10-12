import {expect, test} from '@playwright/test';

const loginPath = process.env.E2E_PENDING_LOGIN_PATH;
const pendingPath = process.env.E2E_PENDING_PATH ?? '/parties/PEND/requests/pending';

test.skip(!loginPath, 'E2E_PENDING_LOGIN_PATH must log in a moderator and land on a party with pending requests');

for (const scheme of ['light', 'dark']) {
    test(`pending action row does not wrap at 1280px (${scheme})`, async ({page}) => {
        await page.setViewportSize({width: 1280, height: 800});
        await page.emulateMedia({colorScheme: scheme});
        await page.goto(loginPath);
        await page.goto(pendingPath);
        await page.waitForLoadState('networkidle');

        const item = page.locator('[data-testid^=pending-]').filter({has: page.locator('input')}).first();
        const tops = await Promise.all(
            [item.getByRole('button', {name: 'Approve'}), item.locator('input'), item.getByRole('button', {name: 'Reject'}), item.getByRole('button', {name: 'Remove'})]
                .map(async (control) => Math.round((await control.boundingBox()).y)),
        );
        expect(new Set(tops).size).toBe(1);

        await item.screenshot({path: `docs/design/v3-screenshots/pending-row-v3-desktop-1280-${scheme}.png`});
        await page.screenshot({path: `docs/design/v3-screenshots/pending-v3-desktop-1280-${scheme}.png`});
    });
}
