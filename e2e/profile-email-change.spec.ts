import { expect, test } from '@playwright/test';
import { readFromEmail } from './support/mailpit';

const EMAIL = 'e2e-profile@example.test';
// The UserFactory password the E2eSeeder leaves on the user.
const PASSWORD = 'password';
const NEW_EMAIL = 'e2e-profile-new@example.test';

test('cancelling a pending email change keeps unsaved edits and shows the saved email', async ({
    page,
    browser,
    baseURL,
}) => {
    await page.goto('/login');
    await page.getByTestId('toggle-sign-in-method').click();
    await page.locator('input[name="email"]').fill(EMAIL);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.getByTestId('login-button').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));

    await page.goto('/settings/profile');
    await page.locator('input[name="email"]').fill(NEW_EMAIL);
    await page.getByTestId('update-profile-button').click();
    await expect(page.getByTestId('pending-email')).toBeVisible();

    // Item DRA-39: the typed name survives the cancellation.
    await page.locator('input[name="name"]').fill('Nom sense desar');
    await page.getByTestId('cancel-email-change-button').click();

    await expect(page.getByTestId('pending-email')).toHaveCount(0);
    await expect(page.locator('input[name="name"]')).toHaveValue(
        'Nom sense desar',
    );

    // The change is confirmed from another device while the page stays open.
    await page.locator('input[name="email"]').fill(NEW_EMAIL);
    await page.getByTestId('update-profile-button').click();
    await expect(page.getByTestId('pending-email')).toBeVisible();

    const token = await readFromEmail(
        NEW_EMAIL,
        /settings\/email\/([A-Za-z0-9]+)/,
    );
    const otherDevice = await browser.newContext({ baseURL, locale: 'ca' });
    const other = await otherDevice.newPage();
    await other.goto(`/settings/email/${token}`);
    await other.getByTestId('confirm-email-change-button').click();
    await expect(other).toHaveURL(/\/login$|\/settings\/profile$/);
    await otherDevice.close();

    // The field on the stale page still holds the old address; cancelling must reload the saved one.
    await page.locator('input[name="email"]').fill(EMAIL);
    // Cancelling on the stale page reloads the saved email into the field.
    await page.getByTestId('cancel-email-change-button').click();
    await expect(page.locator('input[name="email"]')).toHaveValue(NEW_EMAIL);
});
