import { test, expect } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

async function openAssistant(page) {
    const toggle = page.getByRole('button', { name: 'Open AI assistant' });
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(page.getByRole('dialog', { name: 'William Research Admin Agent' })).toBeVisible();
}

async function sendMessage(page, message) {
    await page.getByLabel('Message input').fill(message);
    await page.getByRole('button', { name: 'Send message' }).click();
}

test('provider and retention settings persist through the real WordPress form', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wp-admin-agent');

    await page.locator('#waa_provider').selectOption('fake');
    await page.locator('#waa_data_retention_days').fill('14');
    await page.getByRole('button', { name: 'Save Settings' }).click();

    await expect(page).toHaveURL(/page=wp-admin-agent.*saved=1/);
    await expect(page.locator('#waa_provider')).toHaveValue('fake');
    await expect(page.locator('#waa_data_retention_days')).toHaveValue('14');
});

test('chat streams and the saved conversation can be loaded from history', async ({ page }) => {
    await page.goto('/wp-admin/');
    await openAssistant(page);
    await sendMessage(page, 'Reply with the single word OK.');

    await expect(page.getByText('OK', { exact: true })).toBeVisible();
    await expect(page.getByText(/Session #\d+/)).toBeVisible();

    await page.getByRole('button', { name: 'Start a new session' }).click();
    await page.getByRole('button', { name: 'Session history' }).click();
    await expect(page.getByText('Reply with the single word OK.', { exact: true })).toBeVisible();
    await page.getByText('Reply with the single word OK.', { exact: true }).click();
    await expect(page.getByText('OK', { exact: true })).toBeVisible();
});

test('protected actions wait for explicit approval and can be cancelled', async ({ page }) => {
    await page.goto('/wp-admin/');
    await openAssistant(page);
    await page.getByRole('button', { name: 'Start a new session' }).click();
    await sendMessage(page, 'Request a protected settings change.');

    await expect(page.getByText('Awaiting approval', { exact: true })).toBeVisible();
    await expect(page.getByText('Nothing will change until you confirm.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Cancel action' }).click();
    await expect(page.getByText('Okay, I will not run that action.', { exact: true })).toBeVisible();
    await expect(page.getByText('Awaiting approval', { exact: true })).not.toBeVisible();
});

test('provider configuration failures are shown in the browser', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wp-admin-agent');
    await page.locator('#waa_provider').selectOption('anthropic');
    await page.getByRole('button', { name: 'Save Settings' }).click();
    await expect(page).toHaveURL(/page=wp-admin-agent.*saved=1/);

    await openAssistant(page);
    await page.getByRole('button', { name: 'Start a new session' }).click();
    await sendMessage(page, 'This request should fail without credentials.');

    await expect(page.getByText('AI provider not configured. Please go to Settings → Admin Agent.', { exact: true })).toBeVisible();
});
