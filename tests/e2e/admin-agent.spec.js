import { test, expect } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');

    if (page.url().includes('wp-login.php')) {
        await page.locator('#user_login').fill('admin');
        await page.locator('#user_pass').fill('password');
        await Promise.all([
            page.waitForURL(/\/wp-admin\//),
            page.locator('#wp-submit').click(),
        ]);
    }
});

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

    await page.locator('#wradmin_provider').selectOption('fake');
    await page.locator('#wradmin_data_retention_days').fill('14');
    await page.getByRole('button', { name: 'Save Settings' }).click();

    await page.reload();
    await expect(page.locator('#wradmin_provider')).toHaveValue('fake');
    await expect(page.locator('#wradmin_data_retention_days')).toHaveValue('14');
});

test('chat streams and the saved conversation can be loaded from history', async ({ page }) => {
    await page.goto('/wp-admin/');
    await openAssistant(page);
    await sendMessage(page, 'Reply with the single word OK.');

    await expect(page.getByText('OK', { exact: true })).toBeVisible();
    await expect(page.getByText(/Session #\d+/)).toBeVisible();

    await page.getByRole('button', { name: 'Start a new session' }).click();
    await expect(page.getByText('Session chưa được tạo', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Session history' }).click();
    const savedSession = page.getByText('Reply with the single word OK.', { exact: true }).first();
    await expect(savedSession).toBeVisible();
    await savedSession.click();
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
    await page.locator('#wradmin_provider').selectOption('anthropic');
    await page.getByRole('button', { name: 'Save Settings' }).click();
    await page.reload();
    await expect(page.locator('#wradmin_provider')).toHaveValue('anthropic');

    await openAssistant(page);
    await page.getByRole('button', { name: 'Start a new session' }).click();
    await sendMessage(page, 'This request should fail without credentials.');

    await expect(page.getByText('AI provider not configured. Please go to Settings → Admin Agent.', { exact: true })).toBeVisible();
});

test('prefix upgrade preserves encrypted settings and database rows', async ({ page }) => {
    const response = await page.request.post('/wp-admin/admin-ajax.php?action=wradmin_test_prefix_upgrade');
    expect(response.ok()).toBeTruthy();
    const result = await response.json();
    expect(result, JSON.stringify(result)).toMatchObject({
        success: true,
        data: { migrated: true, key: 'preserved-key', row: 42 },
    });
});

test('guided setup saves personality, tests safe tools, and opens the named bot', async ({ page }) => {
    await page.goto('/wp-admin/options-general.php?page=wp-admin-agent-setup');
    await expect(page.getByRole('heading', { name: 'Let’s make your assistant yours.' })).toBeVisible();
    await page.getByRole('button', { name: 'Let’s begin' }).click();

    await page.locator('#wradmin-bot-name').fill('Orbit');
    await page.locator('#wradmin-user-title').fill('William');
    await page.getByLabel('Professional').check();
    await expect(page.locator('#wradmin-preview-name')).toHaveText('Orbit');
    await page.getByRole('button', { name: 'Continue' }).click();

    await page.locator('#wradmin-setup-provider').selectOption('fake');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: 'Save & test drive' }).click();

    await expect(page.getByRole('heading', { name: 'Time for a tiny test drive.' })).toBeVisible();
    await page.getByRole('button', { name: 'Test connection' }).click();
    await expect(page.locator('[data-wradmin-result="connection"]')).toContainText('✓');
    await page.getByRole('button', { name: 'Test site tool' }).click();
    await expect(page.locator('[data-wradmin-result="get_site_settings"]')).toContainText('✓');
    await page.getByRole('button', { name: 'Finish setup' }).click();

    await expect(page.getByRole('heading', { name: 'Meet Orbit.' })).toBeVisible();
    await page.getByRole('button', { name: 'Say hello to your bot' }).click();
    await expect(page.getByRole('dialog', { name: 'Orbit' })).toBeVisible();

    await page.goto('/wp-admin/options-general.php?page=wp-admin-agent-setup');
    await page.getByRole('button', { name: 'Let’s begin' }).click();
    await expect(page.locator('#wradmin-bot-name')).toHaveValue('Orbit');
});
