// Reads a feature's committed tasks.md into the catalog the shared selector consumes.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { validateCatalog } from '../../skills/agent-workflow/scripts/select-tasks.mjs';

const REGISTRY_HEADING = /^## Canonical task registry\s*$/m;
const PATH_FIELD = /^(Files\/surfaces reserved|Scope and path)\.$/;
const SIZE_FIELD = /^Size\b/;
const DONE_WHEN_FIELD = /^Done when\.$/;
const DEPENDENCIES_FIELD = /^Dependencies\.$/;
const REQUIREMENTS_FIELD = /^Requirements\b/;

const cells = (line) =>
    line
        .trim()
        .replace(/^\||\|$/g, '')
        .split('|')
        .map((cell) => cell.trim());

function registryRows(text) {
    const [, afterHeading] = text.split(REGISTRY_HEADING);
    if (afterHeading === undefined)
        throw new Error('No "## Canonical task registry" section');
    const section = afterHeading.split(/^## /m)[0];
    const lines = section
        .split('\n')
        .filter((line) => line.trim().startsWith('|'));
    const header = cells(lines[0] ?? '');
    const idColumn = header.indexOf('ID');
    const afterColumn = header.indexOf('After');
    if (idColumn < 0 || afterColumn < 0)
        throw new Error('Task registry needs "ID" and "After" columns');
    const linearColumn = header.indexOf('Linear');
    return lines.slice(2).map((line, index) => {
        const row = cells(line);
        return {
            task: row[idColumn],
            after: row[afterColumn],
            issue:
                linearColumn >= 0
                    ? (row[linearColumn].match(/DRA-\d+/)?.[0] ?? null)
                    : null,
            order: index + 1,
        };
    });
}

function expandReferences(text) {
    const references = [];
    for (const match of text.matchAll(
        /\b([A-Z])(\d+)(?:\s*[–-]\s*\1?(\d+))?\b/g,
    )) {
        const [, letter, from, to] = match;
        const last = to ? Number(to) : Number(from);
        for (let n = Number(from); n <= last; n++)
            references.push(`${letter}${String(n).padStart(from.length, '0')}`);
    }
    return references;
}

// "After" lists start prerequisites. "T03 (integration)" marks an edge that is checked after merge
// but never blocks the start; "T23 approved" is still a start prerequisite.
export function parseDependencies(after) {
    if (!after || /^[—-]$/.test(after)) return [];
    const dependencies = [];
    for (const part of after.split(/[;,]/)) {
        const gate = /\(integration\)/.test(part) ? 'integration' : 'start';
        const withoutNotes = part.replace(/\([^)]*\)/g, '');
        for (const task of expandReferences(withoutNotes).filter((ref) =>
            ref.startsWith('T'),
        ))
            if (!dependencies.some((d) => d.task === task))
                dependencies.push({ task, gate });
    }
    return dependencies;
}

function detailSections(text) {
    const details = new Map();
    const parts = text.split(/^### (T\d+)\b.*$/m);
    for (let n = 1; n < parts.length; n += 2)
        details.set(parts[n], parts[n + 1].split(/^##+ /m)[0]);
    return details;
}

function fields(detail) {
    const result = new Map();
    let current = null;
    for (const line of detail.split('\n')) {
        const field = line.match(/^- \*\*([^*]+)\*\*\s*(.*)$/);
        if (field) {
            current = { label: field[1].trim(), text: field[2], rows: 0 };
            result.set(current.label, current);
            continue;
        }
        if (!current) continue;
        if (/^\s{2,}- /.test(line)) current.rows++;
        current.text += '\n' + line;
    }
    return result;
}

const findField = (detailFields, pattern) =>
    [...detailFields.values()].find((field) => pattern.test(field.label));

export function reservedPaths(text) {
    return [...text.matchAll(/`([^`\s]+)`/g)]
        .map((match) => match[1].replace(/[,.;:]$/, ''))
        .filter((value) => /\//.test(value) || /\.[a-z]{2,5}$/.test(value))
        .filter((value) => !/^https?:/.test(value) && !value.includes('…'));
}

// "001 R23a/R24" cites another feature's requirements and a "quoted" requirement text may cite
// others, so only unprefixed references outside quotes are this task's own.
export function ownRequirements(text) {
    const local = text
        .replace(/`[^`]*`/g, '')
        .replace(/"[^"]*"/g, '')
        .replace(/\b\d{3}\s+[RS][\w/–-]*(?:\s*,\s*[RS]\d[\w/–-]*)*/g, '');
    return expandReferences(local).filter((ref) => /^[RS]/.test(ref));
}

export function classifyTier(sizeText, doneWhenRows) {
    const stated = /\blarge\b/i.test(sizeText)
        ? 'L'
        : /\bmedium\b/i.test(sizeText)
          ? 'M'
          : /\bsmall\b/i.test(sizeText)
            ? 'S'
            : null;
    let tier = stated ?? 'M';
    const reasons = [stated ? `stated ${tier}` : 'no stated size'];
    if (tier === 'S' && doneWhenRows > 5) {
        tier = 'M';
        reasons.push(`${doneWhenRows} done-when rows exceed S`);
    }
    if (doneWhenRows > 10 && tier !== 'L') {
        tier = tier === 'S' ? 'M' : 'L';
        reasons.push(`${doneWhenRows} done-when rows raise the tier`);
    }
    return { tier, stated, reason: reasons.join('; ') };
}

export function parseTasks(text) {
    const details = detailSections(text);
    return registryRows(text).map((row) => {
        const detailFields = fields(details.get(row.task) ?? '');
        const doneWhen = findField(detailFields, DONE_WHEN_FIELD);
        const size = findField(detailFields, SIZE_FIELD);
        // A "Done when" written as one paragraph is one row.
        const doneWhenRows = doneWhen
            ? doneWhen.rows || (doneWhen.text.trim() ? 1 : 0)
            : 0;
        const { tier, stated, reason } = classifyTier(
            size?.text ?? '',
            doneWhenRows,
        );
        return {
            id: row.task,
            order: row.order,
            issue: row.issue,
            dependencies: parseDependencies(row.after),
            paths: reservedPaths(
                findField(detailFields, PATH_FIELD)?.text ?? '',
            ),
            tier,
            tierReason: reason,
            statedTier: stated,
            doneWhenRows,
            dependenciesText:
                findField(detailFields, DEPENDENCIES_FIELD)?.text ?? null,
            requirements: ownRequirements(
                findField(detailFields, REQUIREMENTS_FIELD)?.text ?? '',
            ),
            hasDetail: details.has(row.task),
        };
    });
}

export function loadCatalog(root, feature, { workingTree = false } = {}) {
    const path = `specs/${feature}/tasks.md`;
    const text = workingTree
        ? readFileSync(join(root, path), 'utf8')
        : execFileSync('git', ['show', `HEAD:${path}`], {
              cwd: root,
              encoding: 'utf8',
          });
    const tasks = parseTasks(text);
    validateCatalog(tasks);
    return tasks;
}
