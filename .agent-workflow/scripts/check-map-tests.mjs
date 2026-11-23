// Checks that every test named in a handoff's acceptance-to-test map exists in the test sources.
// Usage: node .agent-workflow/scripts/check-map-tests.mjs <handoff.md> [--root <dir>]
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';

export const MAP_HEADING = 'Acceptance-to-test map';
const TEST_DIRECTORIES = ['tests', 'resources/js'];
const TEST_FILE = /(?:Test\.php|\.(?:spec|test)\.ts)$/;
const SKIPPED_DIRECTORIES = new Set(['node_modules', 'vendor', '.git']);

const literal = /'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)"|`((?:[^`\\]|\\.)*)`/g;
// Pest and Vitest declare titles as `it('…')`, `test('…')` and `describe('…')`; a title may be
// wrapped over several literals joined with `.` (PHP) or `+` (TypeScript).
const titlePattern =
    /\b(?:it|test|describe)\(\s*((?:(?:'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"|`(?:[^`\\]|\\.)*`)\s*[.+]?\s*)+)/g;
// Interpolation is not known statically, so `$x`, `{$x}` and `${…}` all become `$` on both sides.
export const normalize = (text) =>
    text.replace(/\{\$[^}]*\}|\$\{[^}]*\}|\$\w+/g, '$');

export function testTitles(source) {
    return [...source.matchAll(titlePattern)].map((match) =>
        normalize(
            [...match[1].matchAll(literal)]
                .map((part) =>
                    (part[1] ?? part[2] ?? part[3]).replace(/\\(.)/g, '$1'),
                )
                .join(''),
        ),
    );
}

function sectionLines(lines, heading) {
    const start = lines.findIndex(
        (line) => /^#+ /.test(line) && line.includes(heading),
    );
    if (start < 0) throw new Error(`section not found: ${heading}`);
    const level = lines[start].match(/^#+/)[0].length;
    const end = lines.findIndex(
        (line, index) =>
            index > start &&
            /^#+ /.test(line) &&
            line.match(/^#+/)[0].length <= level,
    );
    return lines.slice(start, end < 0 ? undefined : end);
}

// Only multi-word backtick cells in table rows are test titles; identifiers and values are skipped.
export function namedTests(markdown, heading = MAP_HEADING) {
    return sectionLines(markdown.split('\n'), heading)
        .filter((line) => line.startsWith('|'))
        .flatMap((line) => [...line.matchAll(/`([^`]+)`/g)].map((m) => m[1]))
        .filter((name) => /^[A-Za-z0-9]/.test(name) && /\s/.test(name));
}

// A title with interpolation stands for every value it is generated with, so it matches by pattern.
const escape = (part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const matchesTitle = (title, wanted) =>
    title.includes(wanted) ||
    (title.includes('$') &&
        new RegExp(title.split('$').map(escape).join('.+')).test(wanted));

export function missingTests(names, titles) {
    const problems = [];
    for (const name of new Set(names)) {
        const wanted = normalize(name);
        const ellipsis = /(?:…|\.\.\.)$/;
        const prefix = ellipsis.test(wanted)
            ? wanted.replace(ellipsis, '').trimEnd()
            : null;
        const matches = titles.filter((title) =>
            prefix === null
                ? matchesTitle(title, wanted)
                : title.startsWith(prefix),
        );
        if (!matches.length) {
            problems.push(`missing: ${name}`);
            continue;
        }
        if (prefix !== null && new Set(matches).size > 1)
            problems.push(
                `ambiguous prefix (${new Set(matches).size} tests): ${name}`,
            );
    }
    return problems;
}

function testFiles(directory) {
    return readdirSync(directory).flatMap((entry) => {
        if (SKIPPED_DIRECTORIES.has(entry)) return [];
        const path = join(directory, entry);
        if (statSync(path).isDirectory()) return testFiles(path);
        return TEST_FILE.test(path) ? [path] : [];
    });
}

export function checkMap(handoff, root) {
    const titles = TEST_DIRECTORIES.flatMap((directory) => {
        try {
            return testFiles(join(root, directory));
        } catch {
            return [];
        }
    }).flatMap((file) => testTitles(readFileSync(file, 'utf8')));
    const names = namedTests(readFileSync(handoff, 'utf8'));
    return {
        names: new Set(names).size,
        titles: titles.length,
        problems: missingTests(names, titles),
    };
}

if (import.meta.url === `file://${process.argv[1]}`) {
    const [handoff, flag, root] = process.argv.slice(2);
    if (!handoff) {
        console.error('usage: check-map-tests.mjs <handoff.md> [--root <dir>]');
        process.exit(2);
    }
    const result = checkMap(handoff, resolve(flag === '--root' ? root : '.'));
    console.log(
        `${result.names} named tests checked against ${result.titles} test titles`,
    );
    for (const problem of result.problems) console.log(problem);
    process.exit(result.problems.length ? 1 : 0);
}
