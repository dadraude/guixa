// Mechanical checks of a feature's tasks.md that the selector relies on. Prose quality stays a review
// concern; this only catches what scheduling would otherwise silently trust.
import { readFileSync, readdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import { validateCatalog } from '../../skills/agent-workflow/scripts/select-tasks.mjs';
import { parseTasks } from './task-catalog.mjs';

export function requirementIndex(specTexts) {
    const defined = new Set();
    for (const text of specTexts)
        for (const match of text.matchAll(/\*\*([RS]\d+[a-z]?)\b/g))
            defined.add(match[1]);
    return defined;
}

export function taskFindings(task, defined) {
    if (!task.hasDetail)
        return [`${task.id}: registry row has no "### ${task.id}" detail`];
    const findings = [];
    if (!task.paths.length)
        findings.push(
            `${task.id}: reserves no paths ("Files/surfaces reserved" or "Scope and path")`,
        );
    if (!task.doneWhenRows)
        findings.push(`${task.id}: "Done when" has no rows`);
    const prose = task.dependenciesText ?? '';
    for (const dependency of task.dependencies)
        if (
            !new RegExp(`\\b${dependency.task}\\b`).test(prose) &&
            !rangeCovers(prose, dependency.task)
        )
            findings.push(
                `${task.id}: "After" names ${dependency.task} but "Dependencies" never explains it`,
            );
    if (task.statedTier === 'S' && task.doneWhenRows > 5)
        findings.push(
            `${task.id}: stated small but has ${task.doneWhenRows} done-when rows (S allows 5)`,
        );
    for (const reference of new Set(task.requirements))
        if (defined.size && !defined.has(reference))
            findings.push(
                `${task.id}: ${reference} is not defined in the spec`,
            );
    return findings;
}

function rangeCovers(prose, taskId) {
    const number = Number(taskId.slice(1));
    return [...prose.matchAll(/T(\d+)\s*[–-]\s*T?(\d+)/g)].some(
        ([, from, to]) => Number(from) <= number && number <= Number(to),
    );
}

function specTexts(root, feature) {
    const directory = join(root, 'specs', feature);
    return readdirSync(directory, { recursive: true })
        .filter(
            (name) =>
                String(name).endsWith('.md') &&
                !/tasks\.md$|history\//.test(String(name)),
        )
        .map((name) => readFileSync(join(directory, String(name)), 'utf8'));
}

export function validateFeature(root, feature) {
    const tasks = parseTasks(
        readFileSync(join(root, 'specs', feature, 'tasks.md'), 'utf8'),
    );
    const findings = [];
    try {
        validateCatalog(tasks);
    } catch (error) {
        findings.push(error.message);
    }
    const defined = requirementIndex(specTexts(root, feature));
    for (const task of tasks) findings.push(...taskFindings(task, defined));
    return { tasks: tasks.length, findings };
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    const feature = process.argv[2];
    if (!feature) {
        console.error('Usage: validate-plan.mjs NNN-feature');
        process.exit(2);
    }
    const root = execFileSync('git', ['rev-parse', '--show-toplevel'], {
        encoding: 'utf8',
    }).trim();
    const { tasks, findings } = validateFeature(root, feature);
    for (const finding of findings) console.log(finding);
    console.log(`${tasks} tasks checked, ${findings.length} findings`);
    if (findings.length) process.exitCode = 1;
}
