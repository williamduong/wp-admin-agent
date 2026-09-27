import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    testMatch: 'admin-agent.spec.js',
    globalSetup: './tests/e2e/global-setup.js',
    timeout: 120_000,
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
    expect: {
        timeout: 30_000,
    },
    use: {
        baseURL: 'http://127.0.0.1:9400',
        headless: true,
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
    },
});
