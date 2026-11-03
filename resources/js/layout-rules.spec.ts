import { describe, expect, it } from 'vite-plus/test';

// Files already migrated to the plan's layout rules (plan.md "Layout rules").
// Each slice appends its pages, shell layouts and presentation components.
export const checkedFiles = [
    'pages/Dashboard.vue',
    'pages/Wallet/Statement.vue',
    'pages/Member/Baskets/Index.vue',
    'pages/Member/Baskets/Show.vue',
    'pages/Stock/Index.vue',
    'pages/Producer/Cutoff.vue',
    'pages/Producer/CycleSummary.vue',
    'pages/Producer/Products/Index.vue',
    'pages/Producer/Products/Create.vue',
    'pages/Producer/Products/Edit.vue',
    'components/StockProductFields.vue',
    'pages/Orders/Index.vue',
    'pages/Orders/Edit.vue',
    'components/orders/CycleCard.vue',
    'components/orders/OrderPageHeader.vue',
    'components/orders/OrderProductRow.vue',
    'layouts/AppLayout.vue',
    'layouts/app/AppSidebarLayout.vue',
    'components/AppShell.vue',
    'components/AppContent.vue',
    'components/AppSidebar.vue',
    'components/AppSidebarHeader.vue',
    'components/Breadcrumbs.vue',
    'components/NavMain.vue',
    'components/NavUser.vue',
    'components/NavFooter.vue',
    'components/UserInfo.vue',
    'components/AppLogo.vue',
    'components/UserMenuContent.vue',
    'components/baskets/WeeklyLines.vue',
    'components/pickup/StockLines.vue',
    'components/pickup/ReturnLines.vue',
    'pages/Manage/Accounts/Index.vue',
    'pages/Manage/Households/Index.vue',
    'pages/Manage/Households/Show.vue',
    'pages/Manage/Producers/Index.vue',
    'pages/Manage/Producers/Products.vue',
    'components/ProductAdministrationActions.vue',
    'pages/Manage/Cycles/Index.vue',
    'pages/Manage/Cycles/Show.vue',
    'pages/Manage/Settings.vue',
    'pages/Manage/Stock/Index.vue',
    'pages/Manage/Stock/Create.vue',
    'pages/Manage/Stock/Edit.vue',
    'pages/Manage/Stock/Movements.vue',
    'pages/Manage/ReturnItems/Index.vue',
    'pages/Manage/ReturnItems/Create.vue',
    'pages/Manage/ReturnItems/Edit.vue',
    'pages/settings/Appearance.vue',
    'pages/settings/Profile.vue',
    'pages/settings/Security.vue',
    'pages/auth/AcceptInvitation.vue',
    'pages/auth/ConfirmPassword.vue',
    'pages/auth/ForgotPassword.vue',
    'pages/auth/Login.vue',
    'pages/auth/ResetPassword.vue',
    'pages/auth/TwoFactorChallenge.vue',
    'pages/auth/VerifyEmail.vue',
    'layouts/settings/Layout.vue',
    'layouts/AuthLayout.vue',
    'layouts/auth/AuthSimpleLayout.vue',
    'components/Heading.vue',
    'components/DeleteUser.vue',
    'components/ManageTwoFactor.vue',
    'components/TwoFactorRecoveryCodes.vue',
    'components/ManagePasskeys.vue',
];

const sources = import.meta.glob<string>('/resources/js/**/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});

const excludedFromChecks = ['components/ui/', 'pages/Welcome.vue'];

// T46: existing spacing retained after rendered before/after review.
// The allowlist is exact by file and class; new off-grid values still fail.
const retainedT46Spacing: Record<string, readonly string[]> = {
    'pages/auth/Login.vue': ['space-x-3'],
    'layouts/auth/AuthSimpleLayout.vue': ['md:p-10'],
    'components/Heading.vue': ['space-y-0.5', 'mb-0.5'],
    'components/DeleteUser.vue': ['space-y-0.5', 'space-y-3'],
    'components/TwoFactorRecoveryCodes.vue': ['gap-3', 'mt-3', 'space-y-3'],
};

const spacingUtility =
    /^-?(?:p|px|py|pt|pr|pb|pl|ps|pe|m|mx|my|mt|mr|mb|ml|ms|me|gap-x|gap-y|gap|space-x|space-y)-(.+)$/;
const sizeUtility = /^-?(?:w|h|size|min-w|min-h|max-w|max-h)-\[.+\]$/;
// Step 1 (4 px) is allowed everywhere: the check cannot tell a single control
// from a container, so review and the visual check cover that rule.
const gridValues = new Set([
    '0',
    'px',
    'auto',
    'reverse',
    '1',
    '2',
    '4',
    '6',
    '8',
    '12',
]);

function withoutVariants(token: string): string {
    let depth = 0;
    let cut = -1;
    for (let index = 0; index < token.length; index++) {
        const char = token[index];
        if (char === '[' || char === '(') depth++;
        if (char === ']' || char === ')') depth--;
        if (char === ':' && depth === 0) cut = index;
    }
    return token.slice(cut + 1);
}

export function offGridClasses(source: string): string[] {
    const found = new Set<string>();
    for (const raw of source.split(/[\s"'`]+/)) {
        const utility = withoutVariants(raw).replace(/^!/, '');
        if (sizeUtility.test(utility)) {
            found.add(raw);
            continue;
        }
        const value = spacingUtility.exec(utility)?.[1];
        if (value === undefined) continue;
        if (!gridValues.has(value)) found.add(raw);
    }
    return [...found];
}

describe('layout rules static check', () => {
    it('flags off-grid steps and arbitrary spacing values', () => {
        const classes =
            'p-5 gap-3 py-1.5 md:px-7 w-[300px] -mt-[3px] space-y-3';
        expect(offGridClasses(classes)).toEqual([
            'p-5',
            'gap-3',
            'py-1.5',
            'md:px-7',
            'w-[300px]',
            '-mt-[3px]',
            'space-y-3',
        ]);
    });

    it('accepts the grid steps, with variants and negative margins', () => {
        const classes =
            'p-4 md:p-6 gap-2 gap-x-2 gap-x-1 @2xl:px-6 py-8 mt-12 -ml-1 mx-auto group-data-[collapsible=icon]:p-0 min-h-11 size-4 grid-cols-[minmax(0,1fr)_8rem]';
        expect(offGridClasses(classes)).toEqual([]);
    });

    it('lists only existing files and none of the excluded ones', () => {
        for (const file of checkedFiles) {
            expect(sources[`/resources/js/${file}`], file).toBeTypeOf('string');
            for (const excluded of excludedFromChecks) {
                expect(file.startsWith(excluded), file).toBe(false);
            }
        }
    });

    it.each(checkedFiles)('%s uses only the spacing grid', (file) => {
        expect(offGridClasses(sources[`/resources/js/${file}`])).toEqual(
            retainedT46Spacing[file] ?? [],
        );
    });
});
