import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { lstatSync } from 'node:fs';
export const digest = (bytes) =>
    createHash('sha256').update(bytes).digest('hex');
const git = (...args) => execFileSync('git', args);
const categories = [
    'fixtures',
    'source',
    'styles',
    'assets',
    'configuration',
    'dependencies',
    'data',
];

export function renderingInputs(declaration) {
    if (!declaration) return null;
    if (typeof declaration.review !== 'string' || !declaration.review.trim())
        throw new Error('Rendering inputs need recorded coverage review');
    const tracked = git('ls-files', '-z')
        .toString()
        .split('\0')
        .filter(Boolean);
    const unknown = git('ls-files', '--others', '-z')
        .toString()
        .split('\0')
        .filter(Boolean);
    const selected = new Set();
    for (const category of categories) {
        const paths = declaration[category];
        if (
            !Array.isArray(paths) ||
            (!paths.length && !declaration.notApplicable?.[category]?.trim())
        )
            throw new Error(
                'Declare rendering inputs or explain not applicable: ' +
                    category,
            );
        for (const path of paths) {
            if (
                typeof path !== 'string' ||
                !path ||
                path.startsWith('/') ||
                path.split('/').includes('..') ||
                /[*?:\\]/.test(path)
            )
                throw new Error(
                    'Rendering inputs must be repository-relative files or directories',
                );
            const matches = (file) =>
                file === path || file.startsWith(path.replace(/\/$/, '') + '/');
            const files = tracked.filter(matches);
            if (!files.length || unknown.some(matches))
                throw new Error(
                    'Missing, untracked or uncovered rendering input: ' + path,
                );
            files.forEach((file) => selected.add(file));
        }
    }
    const files = [...selected]
        .sort((a, b) => (a < b ? -1 : a > b ? 1 : 0))
        .map((path) => {
            if (lstatSync(path).isSymbolicLink())
                throw new Error(
                    'Symlink rendering inputs cannot establish reuse',
                );
            return { path, sha256: digest(git('show', 'HEAD:' + path)) };
        });
    if (!files.length) throw new Error('Rendering inputs cannot be empty');
    return {
        files,
        hash: digest(JSON.stringify(files)),
        review: declaration.review,
    };
}

export function reusable(report, candidate) {
    return (
        report.schemaVersion === 2 &&
        report.renderInputs?.hash &&
        candidate.renderInputs?.hash === report.renderInputs.hash &&
        JSON.stringify(candidate.renderInputs.files) ===
            JSON.stringify(report.renderInputs.files) &&
        candidate.renderInputs.review === report.renderInputs.review
    );
}
