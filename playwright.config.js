import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    testMatch: 'admin-agent.spec.js',
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
    webServer: {
        command: [
            'npx --yes @wp-playground/cli@3.1.55 server',
            '--port=9400',
            // Playground needs enough PHP workers to avoid file-lock deadlocks,
            // while keeping the count bounded for small CI runners.
            '--workers=6',
            '--mount=.:/wordpress/wp-content/plugins/william-research-admin-agent',
            '--blueprint=tests/e2e/blueprint.json',
        ].join(' '),
        url: 'http://127.0.0.1:9400/wp-admin/',
        timeout: 300_000,
        reuseExistingServer: !process.env.CI,
    },
});
