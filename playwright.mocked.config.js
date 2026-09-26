import { defineConfig } from '@playwright/test';
import baseConfig from './playwright.config.js';

export default defineConfig({
    ...baseConfig,
    testMatch: ['confirmation.spec.js', 'hallucination-guards.spec.js'],
    retries: 0,
});
