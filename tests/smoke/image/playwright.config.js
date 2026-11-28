'use strict';

const {defineConfig} = require('@playwright/test');

const appHost = new URL(process.env.SMOKE_APP_URL).hostname;
const oauthHost = new URL(process.env.SMOKE_OAUTH_URL).hostname;

module.exports = defineConfig({
    testDir: '.',
    testMatch: 'image.spec.js',
    timeout: 120000,
    workers: 1,
    retries: 0,
    use: {
        baseURL: process.env.SMOKE_APP_URL,
        launchOptions: {
            args: [`--host-resolver-rules=MAP ${appHost} 127.0.0.1, MAP ${oauthHost} 127.0.0.1`],
        },
    },
});
