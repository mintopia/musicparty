import {expect, test} from '@playwright/test';

for (const width of [1280, 390]) {
    test(`login page has no horizontal scroll at ${width}px`, async ({page}) => {
        await page.setViewportSize({width, height: 800});
        await page.goto('/login');
        await page.waitForLoadState('networkidle');

        const {scrollWidth, clientWidth} = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));

        expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });
}
