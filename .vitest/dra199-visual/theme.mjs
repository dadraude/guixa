import { openChrome, waitFor } from '../../skills/agent-workflow/scripts/visual/chrome.mjs';

const browser = await openChrome();
let failed = 0;

try {
    for (const mode of ['light', 'dark']) {
        const url = `http://127.0.0.1:5181/.vitest/dra192-visual/index.html?scenario=stock&role=manager&mode=${mode}&text=100`;
        await browser.send('Page.navigate', { url });
        await waitFor(
            browser,
            `document.querySelector('.pilot-theme .pilot-page') !== null && document.fonts.status === 'loaded'`,
            `${mode} theme`,
        );

        const result = await browser.evaluate(`(() => {
            const theme = document.querySelector('.pilot-theme');
            const heading = theme.querySelector('h1, h2');
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d');
            const rgb = (token) => {
                const probe = document.createElement('span');
                probe.style.color = 'var(--' + token + ')';
                theme.append(probe);
                context.fillStyle = getComputedStyle(probe).color;
                probe.remove();
                context.fillRect(0, 0, 1, 1);
                return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
            };
            const luminance = (values) => values
                .map((value) => {
                    const channel = value / 255;
                    return channel <= 0.04045
                        ? channel / 12.92
                        : ((channel + 0.055) / 1.055) ** 2.4;
                })
                .reduce((sum, channel, index) =>
                    sum + channel * [0.2126, 0.7152, 0.0722][index], 0);
            const contrast = (first, second) => {
                const values = [first, second].map((token) => luminance(rgb(token)));
                return (Math.max(...values) + 0.05) / (Math.min(...values) + 0.05);
            };
            const hoverContrast = (fill, foreground, surface) => {
                const backdrop = rgb(surface);
                const probe = document.createElement('span');
                probe.style.color = 'color-mix(in oklab, var(--' + fill + ') 90%, transparent)';
                theme.append(probe);
                const hover = getComputedStyle(probe).color;
                probe.remove();
                context.fillStyle = 'rgb(' + backdrop.join(' ') + ')';
                context.fillRect(0, 0, 1, 1);
                context.fillStyle = hover;
                context.fillRect(0, 0, 1, 1);
                const fillLuminance = luminance(
                    [...context.getImageData(0, 0, 1, 1).data].slice(0, 3),
                );
                const textLuminance = luminance(rgb(foreground));
                return (Math.max(fillLuminance, textLuminance) + 0.05) /
                    (Math.min(fillLuminance, textLuminance) + 0.05);
            };
            const textPairs = [
                ['background', 'foreground'],
                ['card', 'card-foreground'],
                ['popover', 'popover-foreground'],
                ['primary', 'primary-foreground'],
                ['secondary', 'secondary-foreground'],
                ['muted', 'muted-foreground'],
                ['accent', 'accent-foreground'],
                ['destructive', 'destructive-foreground'],
                ['sidebar-background', 'sidebar-foreground'],
                ['sidebar-primary', 'sidebar-primary-foreground'],
                ['sidebar-accent', 'sidebar-accent-foreground'],
            ];
            return {
                text: Object.fromEntries(textPairs.map((pair) =>
                    [pair.join('/'), contrast(...pair)])),
                hover: Object.fromEntries(['primary', 'destructive'].map((fill) =>
                    [fill, Math.min(
                        hoverContrast(fill, fill + '-foreground', 'background'),
                        hoverContrast(fill, fill + '-foreground', 'card'),
                    )])),
                input: contrast('input', 'card'),
                bodyFont: getComputedStyle(theme).fontFamily,
                headingFont: getComputedStyle(heading).fontFamily,
            };
        })()`);

        const issues = Object.entries(result.text)
            .filter(([, ratio]) => ratio < 4.5)
            .map(([pair, ratio]) => `${pair}: ${ratio.toFixed(2)}:1`);
        issues.push(...Object.entries(result.hover)
            .filter(([, ratio]) => ratio < 4.5)
            .map(([fill, ratio]) => `${fill} hover: ${ratio.toFixed(2)}:1`));
        if (result.input < 3)
            issues.push(`input/card: ${result.input.toFixed(2)}:1`);
        if (!result.bodyFont.includes('Nunito Variable'))
            issues.push(`body font: ${result.bodyFont}`);
        if (!result.headingFont.includes('Nunito Variable'))
            issues.push(`heading font: ${result.headingFont}`);
        if (issues.length) {
            failed++;
            console.error(`${mode}: ${issues.join('; ')}`);
        } else {
            console.log(`${mode}: text and hover pairs >= 4.5:1, input edge >= 3:1; theme fonts loaded`);
        }
    }
} finally {
    await browser.close();
}

if (failed) process.exitCode = 1;
