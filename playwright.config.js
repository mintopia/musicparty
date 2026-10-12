import {defineConfig} from '@playwright/test';

export default defineConfig({
    testDir: 'tests/e2e',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        launchOptions: {executablePath: process.env.E2E_CHROME_PATH || undefined},
    },
});
