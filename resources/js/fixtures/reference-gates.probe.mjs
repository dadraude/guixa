import { readFileSync } from 'node:fs';
import {
    openChrome,
    waitFor,
} from '../../../skills/agent-workflow/scripts/visual/chrome.mjs';

const manifest = JSON.parse(
    readFileSync(new URL('./reference-gates.visual.json', import.meta.url)),
);
const browser = await openChrome();
const results = [];
const width = Number(process.env.PROBE_WIDTH ?? 375);
const textScale = width === 1024 ? 200 : 100;
try {
    for (const view of manifest.views) {
        const url = new URL(view.path, manifest.baseUrl);
        for (const [key, value] of Object.entries(view.query))
            url.searchParams.set(key, value);
        url.searchParams.set(
            'mode',
            process.env.PROBE_MODE === 'dark' ? 'dark' : 'light',
        );
        url.searchParams.set('text', String(textScale));
        await browser.send('Emulation.setDeviceMetricsOverride', {
            width,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false,
        });
        await browser.send('Page.navigate', { url: url.href });
        await waitFor(
            browser,
            `!!document.querySelector(${JSON.stringify(view.root)}) && !!document.querySelector('script[data-page="app"]')`,
            view.id,
        );
        let keyboardDrawer = null;
        if (!view.id.startsWith('welcome-')) {
            await browser.evaluate(
                'document.activeElement?.blur(); window.scrollTo(0,0)',
            );
            let triggerReached = false;
            for (let tab = 0; tab < 30 && !triggerReached; tab++) {
                await browser.send('Input.dispatchKeyEvent', {
                    type: 'keyDown',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                await browser.send('Input.dispatchKeyEvent', {
                    type: 'keyUp',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                triggerReached = await browser.evaluate(
                    "document.activeElement?.matches('button[data-sidebar=\"trigger\"]') && document.activeElement.matches(':focus-visible')",
                );
            }
            if (!triggerReached)
                throw new Error(
                    `${view.id}: keyboard did not reach visible drawer trigger`,
                );
            if (width === 375) {
                await browser.evaluate(
                    'document.querySelector(\'button[data-sidebar="trigger"]\').click()',
                );
            }
            await waitFor(
                browser,
                '!!document.querySelector(\'[data-sidebar="sidebar"]\')',
                `${view.id} sidebar`,
            );
            let drawerLinkReached = false;
            for (let tab = 0; tab < 30 && !drawerLinkReached; tab++) {
                await browser.send('Input.dispatchKeyEvent', {
                    type: 'keyDown',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                await browser.send('Input.dispatchKeyEvent', {
                    type: 'keyUp',
                    key: 'Tab',
                    code: 'Tab',
                    windowsVirtualKeyCode: 9,
                });
                drawerLinkReached = await browser.evaluate(
                    "!!document.activeElement?.closest('[data-sidebar=\"sidebar\"]') && document.activeElement.matches(':focus-visible')",
                );
            }
            keyboardDrawer = { triggerReached, drawerLinkReached };
        }
        let hoveredNavigation = false;
        if (
            [
                'manage-accounts-index',
                'producer-products-index',
                'orders-index',
            ].includes(view.id)
        ) {
            const point = await browser.evaluate(`(() => {
                const button = [...document.querySelectorAll('[data-sidebar="sidebar"] nav [data-sidebar="menu-button"]')].find(element => element.getAttribute('data-active') === 'false');
                if (!button) return null;
                const rect = button.getBoundingClientRect();
                return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 };
            })()`);
            if (point) {
                await browser.send('Input.dispatchMouseEvent', {
                    type: 'mouseMoved',
                    x: point.x,
                    y: point.y,
                });
                await browser.evaluate(
                    'new Promise(resolve => requestAnimationFrame(resolve))',
                );
                hoveredNavigation = true;
            }
        }
        const actual = await browser.evaluate(`(() => {
            const page = JSON.parse(document.querySelector('script[data-page="app"]').textContent);
            const visible = (element) => {
                if (!element) return false;
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
            };
            const size = (element) => {
                if (!visible(element)) return null;
                const rect = element.getBoundingClientRect();
                return { width: Math.round(rect.width), height: Math.round(rect.height) };
            };
            const action = document.querySelector('#welcome-action');
            const trigger = document.querySelector('button[data-sidebar="trigger"]');
            const mainAction = [...document.querySelectorAll('#main-content button, #main-content a')].find(visible);
            const sidebarElement = [...document.querySelectorAll('[data-sidebar="sidebar"]')].find(visible);
            const sidebar = sidebarElement?.textContent ?? '';
            const navTargets = [...(sidebarElement?.querySelectorAll('nav [data-sidebar="menu-button"]') ?? [])].filter(visible).map(element => {
                const style = getComputedStyle(element);
                let surface = element;
                while (surface && getComputedStyle(surface).backgroundColor === 'rgba(0, 0, 0, 0)') surface = surface.parentElement;
                return { label: element.textContent.trim(), size: size(element), active: element.getAttribute('data-active'), foreground: style.color, background: surface ? getComputedStyle(surface).backgroundColor : null };
            });
            const breadcrumb = document.querySelector('[data-slot="breadcrumb"], [aria-label="breadcrumb"]')?.textContent ?? '';
            const foreground = getComputedStyle(document.body).color;
            const background = getComputedStyle(document.body).backgroundColor;
            const primary = document.querySelector('#welcome-action, #main-content button[type=submit]');
            const primaryColors = primary ? { foreground: getComputedStyle(primary).color, background: getComputedStyle(primary).backgroundColor } : null;
            return {
                pageUrl: page.url,
                roles: page.props.auth.roles,
                user: Boolean(page.props.auth.user),
                roleAreas: page.props.roleAreas.map(area => area.role),
                sidebar,
                navTargets,
                breadcrumb,
                action: action ? { label: action.textContent.trim(), href: action.getAttribute('href'), size: size(action) } : null,
                trigger: size(trigger),
                mainAction: size(mainAction),
                foreground,
                background,
                primaryColors,
                overflow: document.documentElement.scrollWidth > innerWidth,
            };
        })()`);
        const expectedRole = view.query.role ?? 'member';
        const welcome = view.id.startsWith('welcome-');
        const authenticated = view.id === 'welcome-authenticated';
        actual.keyboardDrawer = keyboardDrawer;
        actual.hoveredNavigation = hoveredNavigation;
        const failures = [];
        if (actual.pageUrl !== view.query.route)
            failures.push('wrong Inertia page URL');
        if (actual.user !== (welcome ? authenticated : true))
            failures.push('wrong auth user');
        if (
            JSON.stringify(actual.roles) !==
            JSON.stringify(welcome && !authenticated ? [] : [expectedRole])
        )
            failures.push('wrong auth role');
        if (
            JSON.stringify(actual.roleAreas) !==
            JSON.stringify(welcome && !authenticated ? [] : [expectedRole])
        )
            failures.push('wrong role areas');
        if (
            !welcome &&
            !actual.sidebar.includes(
                expectedRole === 'producer'
                    ? 'Producció'
                    : expectedRole === 'member'
                      ? 'Comandes i moneder'
                      : 'Cooperativa',
            )
        )
            failures.push('wrong sidebar navigation');
        if (
            !welcome &&
            (!keyboardDrawer?.triggerReached ||
                !keyboardDrawer?.drawerLinkReached)
        )
            failures.push('drawer keyboard focus missing');
        if (
            !welcome &&
            actual.navTargets.some(
                (target) => target.size.width < 44 || target.size.height < 44,
            )
        )
            failures.push('navigation target below 44px');
        if (
            welcome &&
            (actual.action?.href !==
                (authenticated ? '/dashboard' : '/login') ||
                !actual.action?.label)
        )
            failures.push('wrong Welcome action');
        if (
            welcome &&
            (actual.action?.size?.width < 44 ||
                actual.action?.size?.height < 44)
        )
            failures.push('Welcome action below 44px');
        if (
            !welcome &&
            actual.trigger &&
            (actual.trigger.width < 44 || actual.trigger.height < 44)
        )
            failures.push('sidebar trigger below 44px');
        const linear = (channel) => {
            const value = channel / 255;
            return value <= 0.04045
                ? value / 12.92
                : ((value + 0.055) / 1.055) ** 2.4;
        };
        const luminance = (color) => {
            const channels = color.match(/^rgba?\((\d+), (\d+), (\d+)/);
            if (!channels) {
                const srgb = color.match(
                    /^color\(srgb ([0-9.]+) ([0-9.]+) ([0-9.]+)/,
                );
                if (!srgb) return null;
                return (
                    0.2126 * linear(Number(srgb[1]) * 255) +
                    0.7152 * linear(Number(srgb[2]) * 255) +
                    0.0722 * linear(Number(srgb[3]) * 255)
                );
            }
            return (
                0.2126 * linear(Number(channels[1])) +
                0.7152 * linear(Number(channels[2])) +
                0.0722 * linear(Number(channels[3]))
            );
        };
        const contrast = (foreground, background) => {
            const a = luminance(foreground),
                b = luminance(background);
            return a === null || b === null
                ? null
                : (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
        };
        actual.bodyContrast = contrast(actual.foreground, actual.background);
        actual.primaryContrast = actual.primaryColors
            ? contrast(
                  actual.primaryColors.foreground,
                  actual.primaryColors.background,
              )
            : null;
        if (actual.bodyContrast === null || actual.bodyContrast < 4.5)
            failures.push('body contrast below 4.5');
        if (actual.primaryContrast !== null && actual.primaryContrast < 4.5)
            failures.push('primary action contrast below 4.5');
        actual.navContrasts = actual.navTargets.map((target) => ({
            label: target.label,
            active: target.active,
            ratio: target.background
                ? contrast(target.foreground, target.background)
                : null,
        }));
        if (
            actual.navContrasts.some(
                (item) => item.ratio === null || item.ratio < 4.5,
            )
        )
            failures.push('navigation contrast below 4.5');
        if (actual.overflow) failures.push('horizontal overflow');
        results.push({
            id: view.id,
            expectedRole,
            width,
            textScale,
            mode: process.env.PROBE_MODE === 'dark' ? 'dark' : 'light',
            ...actual,
            failures,
        });
        process.stdout.write(
            `${view.id}: ${failures.length ? failures.join('; ') : 'pass'}\n`,
        );
    }
} finally {
    await browser.close();
}
process.stdout.write(
    JSON.stringify({
        summary: {
            views: results.length,
            failed: results.filter((result) => result.failures.length).length,
        },
        results,
    }) + '\n',
);
if (results.some((result) => result.failures.length)) process.exitCode = 1;
