import {expect, test} from '@playwright/test';

const partyCode = process.env.E2E_PARTY_CODE ?? 'ETVE';

for (const width of [1280, 390]) {
    test(`tv screen has no horizontal scroll at ${width}px`, async ({page}) => {
        await page.setViewportSize({width, height: 800});
        await page.goto(`/parties/${partyCode}/tv`);
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('tv-now-playing-title')).toBeVisible();

        const {scrollWidth, clientWidth} = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));

        expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });
}
