import { createHash } from 'node:crypto';
import { existsSync, readFileSync, realpathSync, statSync } from 'node:fs';
import { isAbsolute, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = fileURLToPath(new URL('../../', import.meta.url));
const featurePattern = /^\d{3}-[a-z0-9]+(?:-[a-z0-9]+)*$/;
const stages = [
    'discovery',
    'scope',
    'spec',
    'plan',
    'tasks',
    'implementation',
    'review',
    'integration',
    'acceptance',
];
const required = {
    discovery: ['request', 'discovery'],
    scope: ['slices', 'selectionDecision'],
    spec: ['specification', 'gapReview', 'acceptance'],
    plan: ['plan', 'acceptance'],
    tasks: ['tasks', 'acceptance'],
    implementation: ['changes', 'checks'],
    review: ['independentReview'],
    integration: ['integratedRevision', 'ci'],
    acceptance: ['acceptanceMatrix', 'review', 'userAcceptance'],
};
const canonical = {
    'discovery.discovery': (feature) => `specs/${feature}/discovery.md`,
    'spec.specification': (feature) => `specs/${feature}/spec.md`,
    'plan.plan': (feature) => `specs/${feature}/plan.md`,
    'tasks.tasks': (feature) => `specs/${feature}/tasks.md`,
};

function within(root, file) {
    const rel = relative(root, file);
    return (
        rel !== '' && rel !== '..' && !rel.startsWith('../') && !isAbsolute(rel)
    );
}

function evidenceFinding(root, feature, key, pointer) {
    if (!pointer || typeof pointer !== 'object' || Array.isArray(pointer))
        return `${key}: expected {path, sha256} evidence pointer`;
    const { path, sha256 } = pointer;
    if (
        typeof path !== 'string' ||
        !path ||
        isAbsolute(path) ||
        path.split('/').includes('..') ||
        path.split('/').includes('.') ||
        path.includes('\\')
    ) {
        return `${key}: path must be a repo-relative file without traversal`;
    }
    if (typeof sha256 !== 'string' || !/^[a-f0-9]{64}$/.test(sha256))
        return `${key}: sha256 must be a lowercase SHA256 hex digest`;
    if (canonical[key] && path !== canonical[key](feature))
        return `${key}: expected canonical path ${canonical[key](feature)}`;
    if (
        key === 'discovery.request' &&
        !new RegExp(`^specs/${feature}/history/[^/]+\\.md$`).test(path)
    ) {
        return `${key}: expected a feature Request history file`;
    }
    const full = resolve(root, path);
    if (!within(root, full)) return `${key}: path escapes repository root`;
    if (!existsSync(full)) return `${key}: missing file ${path}`;
    let actual;
    try {
        actual = realpathSync(full);
    } catch {
        return `${key}: cannot read ${path}`;
    }
    if (!within(realpathSync(root), actual))
        return `${key}: symlink escapes repository root`;
    if (!statSync(actual).isFile())
        return `${key}: expected a regular file at ${path}`;
    let bytes;
    try {
        bytes = readFileSync(actual);
    } catch {
        return `${key}: cannot read ${path}`;
    }
    if (
        key === 'discovery.request' &&
        !/\bRequest\b/.test(bytes.toString('utf8').slice(0, 250))
    ) {
        return `${key}: source does not identify a Request entry`;
    }
    if (createHash('sha256').update(bytes).digest('hex') !== sha256)
        return `${key}: stale SHA256 for ${path}`;
    return null;
}

export function validateFeature(root, feature, nextStage) {
    if (!featurePattern.test(feature))
        throw new Error('Expected an NNN-feature directory name.');
    if (![...stages, 'complete'].includes(nextStage))
        throw new Error(`Unknown next stage: ${nextStage}`);
    const absoluteRoot = realpathSync(root);
    const featureDirectory = join(absoluteRoot, 'specs', feature);
    if (
        !existsSync(featureDirectory) ||
        !within(join(absoluteRoot, 'specs'), realpathSync(featureDirectory))
    ) {
        throw new Error(
            `Feature directory does not exist inside specs: ${feature}`,
        );
    }
    const findings = [];
    const manifestPath = join(featureDirectory, 'workflow.json');
    if (!existsSync(manifestPath))
        return {
            evidenceReady: false,
            stage: nextStage,
            findings: [
                `Missing ${relative(absoluteRoot, manifestPath)}; create an empty evidence manifest before checking the gate.`,
            ],
        };
    if (!within(featureDirectory, realpathSync(manifestPath)))
        throw new Error(
            'workflow.json must be a regular file inside the feature directory.',
        );
    let manifest;
    try {
        manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    } catch {
        throw new Error('workflow.json must contain valid JSON.');
    }
    if (
        !manifest ||
        manifest.schemaVersion !== 1 ||
        !manifest.stages ||
        typeof manifest.stages !== 'object' ||
        Array.isArray(manifest.stages)
    ) {
        throw new Error(
            'workflow.json requires schemaVersion 1 and a stages object.',
        );
    }
    const requiredStages =
        nextStage === 'complete'
            ? stages
            : stages.slice(0, stages.indexOf(nextStage));
    const checks =
        nextStage === 'discovery'
            ? [['discovery', 'request']]
            : requiredStages.flatMap((stage) =>
                  required[stage].map((field) => [stage, field]),
              );
    const needsSlice =
        stages.indexOf(nextStage) >= stages.indexOf('spec') ||
        nextStage === 'complete';
    if (
        needsSlice &&
        (!manifest.slice ||
            typeof manifest.slice.id !== 'string' ||
            !manifest.slice.id.trim())
    ) {
        findings.push('slice.id: select a delivery slice before proceeding');
    }
    if (manifest.slice) {
        for (const list of ['scopeRefs', 'deferredRefs']) {
            if (
                manifest.slice[list] !== undefined &&
                (!Array.isArray(manifest.slice[list]) ||
                    !manifest.slice[list].every(
                        (value) => typeof value === 'string' && value.trim(),
                    ))
            ) {
                findings.push(
                    `slice.${list}: expected an array of nonempty reference strings`,
                );
            }
        }
    }
    for (const stage of new Set(checks.map(([name]) => name))) {
        const record = manifest.stages[stage];
        if (!record || typeof record !== 'object' || Array.isArray(record)) {
            findings.push(`stages.${stage}: missing stage record`);
            continue;
        }
        if (
            stage !== 'discovery' &&
            (!manifest.slice || record.sliceId !== manifest.slice.id)
        ) {
            findings.push(
                `stages.${stage}.sliceId: must match selected slice.id`,
            );
        }
        for (const field of checks
            .filter(([name]) => name === stage)
            .map(([, field]) => field)) {
            const finding = evidenceFinding(
                absoluteRoot,
                feature,
                `${stage}.${field}`,
                record[field],
            );
            if (finding) findings.push(finding);
        }
        if (record.sources !== undefined) {
            if (!Array.isArray(record.sources))
                findings.push(
                    `${stage}.sources: expected an array of evidence pointers`,
                );
            else
                record.sources.forEach((source, index) => {
                    const finding = evidenceFinding(
                        absoluteRoot,
                        feature,
                        `${stage}.sources[${index}]`,
                        source,
                    );
                    if (finding) findings.push(finding);
                });
        }
    }
    const specPath = join(featureDirectory, 'spec.md');
    if (existsSync(specPath)) {
        const heading = readFileSync(specPath, 'utf8').slice(0, 1000);
        const status = heading.match(
            /\bStatus:\s*(Retired|Superseded)\b/i,
        )?.[1];
        if (status)
            findings.push(
                `spec.md status is ${status}; select an independently authorized active scope instead`,
            );
    }
    return { evidenceReady: findings.length === 0, stage: nextStage, findings };
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    const [feature, nextStage, ...rest] = process.argv.slice(2);
    if (
        rest.length ||
        !feature ||
        !nextStage ||
        !featurePattern.test(feature) ||
        ![...stages, 'complete'].includes(nextStage)
    ) {
        process.stderr.write(
            'Usage: node .agent-workflow/scripts/validate-feature.mjs <NNN-feature> <next-stage>\n',
        );
        process.exitCode = 2;
    } else {
        try {
            const result = validateFeature(repositoryRoot, feature, nextStage);
            process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
            process.exitCode = result.evidenceReady ? 0 : 1;
        } catch (error) {
            process.stderr.write(`${error.message}\n`);
            process.exitCode = 2;
        }
    }
}
