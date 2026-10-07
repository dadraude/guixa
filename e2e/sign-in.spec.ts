import { expect, test } from '@playwright/test';
import { readFromEmail } from './support/mailpit';

const EMAIL = 'e2e-member@example.test';
const PASSWORD = 'a-long-e2e-password-1';

test('a new member signs in with a code from the email, sets a password and signs in with it', async ({
    page,
    baseURL,
}) => {
    // The seeder created the member with a household; the welcome email links to the sign-in screen.
    const signInPath = await readFromEmail(EMAIL, /(\/login)(?![A-Za-z0-9/])/);

    await page.goto(new URL(signInPath, baseURL).toString());
    await page.locator('input[name="email"]').fill(EMAIL);
    await page.getByTestId('magic-link-button').click();
    await expect(page).toHaveURL(/\/magic-link\/code$/);

    const code = await readFromEmail(EMAIL, /\b(\d{6})\b/);

    await page.locator('input[name="code"]').fill(code);
    await page.getByTestId('code-button').click();

    await expect(page).toHaveURL(/\/set-password$/);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.locator('input[name="password_confirmation"]').fill(PASSWORD);
    await page.getByTestId('set-password-button').click();

    await expect(page).toHaveURL(/\/dashboard$/);

    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/login$/);

    await page.getByTestId('toggle-sign-in-method').click();
    await page.locator('input[name="email"]').fill(EMAIL);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.getByTestId('login-button').click();

    await expect(page).toHaveURL(/\/dashboard$/);
});
