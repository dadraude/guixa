import { expect, test } from '@playwright/test';
import { readFromEmail } from './support/mailpit';

const EMAIL = 'e2e-member@example.test';
const PASSWORD = 'a-long-e2e-password-1';

test('a new member accepts the invitation and later signs in with a code from the email', async ({
    page,
    baseURL,
}) => {
    const invitationPath = await readFromEmail(
        EMAIL,
        /(\/invitations\/[A-Za-z0-9]+)/,
    );

    await page.goto(new URL(invitationPath, baseURL).toString());
    await page.getByLabel('Nom complet').fill('Anna E2E');
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.locator('input[name="password_confirmation"]').fill(PASSWORD);
    await page.getByTestId('accept-invitation-button').click();

    await expect(page).toHaveURL(/\/dashboard$/);

    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/login$/);

    await page.locator('input[name="email"]').fill(EMAIL);
    await page.getByTestId('magic-link-button').click();
    await expect(page).toHaveURL(/\/magic-link\/code$/);

    const code = await readFromEmail(EMAIL, /\b(\d{6})\b/);

    await page.locator('input[name="code"]').fill(code);
    await page.getByTestId('code-button').click();

    await expect(page).toHaveURL(/\/dashboard$/);
});
