// Advisory check of one predecessor PR's collected evidence before stacking a successor on it.
// It collects nothing and authenticates nothing: the coordinator owns provenance and freshness.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { pathsOverlap } from './select-tasks.mjs';

const PATH_FIELDS = [
    'committed_paths',
    'uncommitted_paths',
    'untracked_paths',
    'candidate_paths',
];
const FLAGS = ['stopped', 'user_hold', 'active_correction'];
const text = (value) =>
    typeof value === 'string' && value.trim() ? value.trim() : null;

function identityFindings(evidence) {
    const reasons = [];
    for (const field of ['task', 'remote_head', 'local_head', 'base_sha'])
        if (!text(evidence[field])) reasons.push(`${field}: missing identity`);
    if (!(Number.isInteger(evidence.pr) && evidence.pr > 0))
        reasons.push('pr: missing PR number');
    if (evidence.pr_status !== 'open') reasons.push('pr_status: expected open');
    if (evidence.draft !== false) reasons.push('draft: expected false');
    if (
        evidence.remote_head &&
        evidence.local_head &&
        evidence.remote_head !== evidence.local_head
    )
        reasons.push('local_head: differs from the published remote_head');
    for (const source of ['review', 'ci']) {
        if (evidence[source]?.head_sha !== evidence.remote_head)
            reasons.push(`${source}.head_sha: differs from remote_head`);
        if (evidence[source]?.base_sha !== evidence.base_sha)
            reasons.push(`${source}.base_sha: differs from base_sha`);
    }
    if (evidence.review?.passed !== true)
        reasons.push('review.passed: expected true from an independent review');
    return reasons;
}

function ciFindings(ci) {
    const expected = ci?.expected_checks;
    if (
        !Array.isArray(expected) ||
        !expected.length ||
        new Set(expected).size !== expected.length
    )
        return [
            'ci.expected_checks: expected a nonempty list of unique check names',
        ];
    if (!Array.isArray(ci.checks)) return ['ci.checks: missing'];
    const reasons = [];
    for (const name of expected) {
        const matching = ci.checks.filter((check) => check?.name === name);
        if (matching.length !== 1)
            reasons.push(`ci.checks: ${name} is missing or duplicated`);
        else if (
            matching[0].status !== 'completed' ||
            matching[0].conclusion !== 'success'
        )
            reasons.push(`ci.checks: ${name} did not complete with success`);
    }
    return reasons;
}

export function evaluatePredecessor(evidence = {}) {
    const reasons = [];
    for (const flag of FLAGS) {
        if (typeof evidence[flag] !== 'boolean')
            reasons.push(`${flag}: missing boolean`);
        else if (evidence[flag]) reasons.push(`${flag}: active`);
    }
    reasons.push(...identityFindings(evidence), ...ciFindings(evidence.ci));

    const paths = {};
    for (const field of PATH_FIELDS) {
        const value = evidence[field];
        const valid =
            Array.isArray(value) && value.every((path) => text(path) === path);
        if (!valid) reasons.push(`${field}: missing or invalid path list`);
        paths[field] = valid
            ? [...new Set(value)].sort((a, b) => a.localeCompare(b))
            : [];
    }
    for (const field of ['uncommitted_paths', 'untracked_paths'])
        if (paths[field].length)
            reasons.push(`${field}: unpublished edits present`);

    const reservationPaths = [
        ...new Set([
            ...paths.committed_paths,
            ...paths.uncommitted_paths,
            ...paths.untracked_paths,
        ]),
    ].sort((a, b) => a.localeCompare(b));
    const overlap = reservationPaths.flatMap((predecessorPath) =>
        paths.candidate_paths
            .filter((candidatePath) =>
                pathsOverlap(predecessorPath, candidatePath),
            )
            .map((candidatePath) => ({
                predecessor_path: predecessorPath,
                candidate_path: candidatePath,
            })),
    );
    return {
        ready_for_stack: reasons.length === 0,
        advisory: true,
        reasons,
        reservation_paths: reservationPaths,
        overlap,
        note: 'Committed predecessor paths stay reserved against every task outside the stack.',
    };
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    const [flag, file] = process.argv.slice(2);
    if (flag !== '--evidence' || !file) {
        console.error('Usage: node predecessor-readiness.mjs --evidence FILE');
        process.exit(2);
    }
    const result = evaluatePredecessor(
        JSON.parse(readFileSync(resolve(file), 'utf8')),
    );
    console.log(JSON.stringify(result, null, 2));
    if (!result.ready_for_stack) process.exitCode = 1;
}
