import { expect, type Page, test } from '@playwright/test';

const OWNER_EMAIL = 'e2e-owner@example.test';
// The UserFactory password the E2eSeeder leaves on the owner.
const OWNER_PASSWORD = 'password';

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByTestId('toggle-sign-in-method').click();
    await page.locator('input[name="email"]').fill(OWNER_EMAIL);
    await page.locator('input[name="password"]').fill(OWNER_PASSWORD);
    await page.getByTestId('login-button').click();
    // The owner has no household, so sign-in lands on the admin home, not the dashboard.
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/households');
});

// The name lives in an input, so a row is found by its household number; migrate:fresh and the
// E2eSeeder make them stable: 1 Cal E2E, 2 Cal Gran E2E (two members), 3 Cal Buit E2E (none).
const row = (page: Page, number: number) =>
    page.getByRole('listitem').filter({ hasText: `#${number}` });

test('deactivating and reactivating a household asks what happens to its members, with nothing preselected each time', async ({
    page,
}) => {
    const household = row(page, 2);
    const dialog = page.getByRole('dialog');
    const onlyHousehold = dialog.getByRole('radio', {
        name: 'Desactiva només la unitat familiar',
    });
    const withMembers = dialog.getByRole('radio', {
        name: 'Desactiva la unitat familiar i els seus membres',
    });
    const confirm = dialog.getByTestId('confirm-household-status');

    await household.getByTestId('deactivate-household').click();

    await expect(dialog).toBeVisible();
    await expect(onlyHousehold).not.toBeChecked();
    await expect(withMembers).not.toBeChecked();
    await expect(confirm).toBeDisabled();
    await expect(dialog.getByTestId('affected-members')).toContainText(
        'Berta E2E',
    );
    await expect(dialog.getByTestId('affected-members')).toContainText(
        'Carles E2E',
    );

    await withMembers.check();
    await expect(confirm).toBeEnabled();
    await confirm.click();

    await expect(
        page.getByText('Unitat familiar i membres desactivats.'),
    ).toBeVisible();
    await expect(dialog).toBeHidden();
    await expect(household).toContainText('Inactiu/iva');

    // Regression: the previous choice must not come back when the dialog opens again.
    await household.getByTestId('reactivate-household').click();

    const reactivateWithMembers = dialog.getByRole('radio', {
        name: 'Reactiva la unitat familiar i els seus membres',
    });
    const reactivateOnly = dialog.getByRole('radio', {
        name: 'Reactiva només la unitat familiar',
    });

    await expect(dialog).toBeVisible();
    await expect(reactivateOnly).not.toBeChecked();
    await expect(reactivateWithMembers).not.toBeChecked();
    await expect(confirm).toBeDisabled();

    // Choosing and cancelling must not leave the choice behind either.
    await reactivateWithMembers.check();
    await dialog.getByRole('button', { name: 'Cancel·la' }).click();
    await expect(dialog).toBeHidden();

    await household.getByTestId('reactivate-household').click();

    await expect(reactivateOnly).not.toBeChecked();
    await expect(reactivateWithMembers).not.toBeChecked();
    await expect(confirm).toBeDisabled();
});

test('a household without active members is deactivated at once, with no dialog', async ({
    page,
}) => {
    const household = row(page, 3);

    await household.getByTestId('deactivate-household').click();

    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(page.getByText('Unitat familiar desactivada.')).toBeVisible();
    await expect(household).toContainText('Inactiu/iva');
});
