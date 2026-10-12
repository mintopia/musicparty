import {expect, test} from '@playwright/test';

const partyCode = process.env.E2E_PARTY_CODE;

for (const width of [1280, 390]) {
    test(`tv screen has no horizontal scroll at ${width}px`, async ({page}) => {
        test.skip(!partyCode, 'E2E_PARTY_CODE must name a seeded party');
        await page.setViewportSize({width, height: 800});
        await page.goto(`/parties/${partyCode}/tv`);
        await page.waitForLoadState('networkidle');

        const {scrollWidth, clientWidth} = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));

        expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });
}
