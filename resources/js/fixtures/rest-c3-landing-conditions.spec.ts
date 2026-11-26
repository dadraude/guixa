// @vitest-environment jsdom
import { describe, expect, it } from 'vite-plus/test';
import { paintedColours, paintsToken } from './rest-c3-landing-conditions';

// #a05a10, the light landing ochre, as `token()` reports it.
const ochre = 'rgb(160, 90, 16)';

describe('C3 colour role observations', () => {
    it.each([
        ['opaque', 'rgb(160, 90, 16)'],
        ['translucent', 'rgba(160, 90, 16, 0.4)'],
        [
            'translucent color-mix',
            'color(srgb 0.627451 0.352941 0.062745 / 0.5)',
        ],
        ['opacity utility', 'oklab(0.538047 0.061324 0.104086 / 0.5)'],
    ])('counts an %s use of a status token', (_, colour) => {
        expect(paintsToken(colour, ochre)).toBe(true);
    });

    it.each([
        ['invisible', 'rgba(160, 90, 16, 0)'],
        ['focus colour', 'rgb(168, 95, 18)'],
        ['neighbouring colour-mix', 'color(srgb 0.658824 0.372549 0.070588)'],
        ['keyword', 'none'],
    ])('does not count an %s paint', (_, colour) => {
        expect(paintsToken(colour, ochre)).toBe(false);
    });

    it('reads the colours of box shadows', () => {
        const element = document.createElement('div');
        element.style.boxShadow =
            'rgba(160, 90, 16, 0.5) 0px 0px 0px 3px, rgb(1, 2, 3) 0px 1px 2px 0px';
        document.body.append(element);

        expect(
            paintedColours(element).filter((colour) =>
                paintsToken(colour, ochre),
            ),
        ).toHaveLength(1);
        element.remove();
    });
});
