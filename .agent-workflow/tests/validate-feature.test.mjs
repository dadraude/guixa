import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import {
    mkdtempSync,
    mkdirSync,
    rmSync,
    symlinkSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';
import { validateFeature } from '../scripts/validate-feature.mjs';

const script = fileURLToPath(
    new URL('../scripts/validate-feature.mjs', import.meta.url),
);
const fields = {
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

function fixture(status = 'Accepted') {
    const root = mkdtempSync(join(tmpdir(), 'feature-evidence-'));
    const feature = '004-example';
    const write = (path, content) => {
        const full = join(root, path);
        mkdirSync(dirname(full), { recursive: true });
        writeFileSync(full, content);
        return {
            path,
            sha256: createHash('sha256').update(content).digest('hex'),
        };
    };

    const stages = {};
    for (const [stage, names] of Object.entries(fields)) {
        stages[stage] = stage === 'discovery' ? {} : { sliceId: 'first' };
        for (const name of names) {
            const path =
                stage === 'discovery' && name === 'request'
                    ? `specs/${feature}/history/request.md`
                    : stage === 'discovery' && name === 'discovery'
                      ? `specs/${feature}/discovery.md`
                      : stage === 'spec' && name === 'specification'
                        ? `specs/${feature}/spec.md`
                        : stage === 'plan' && name === 'plan'
                          ? `specs/${feature}/plan.md`
                          : stage === 'tasks' && name === 'tasks'
                            ? `specs/${feature}/tasks.md`
                            : `evidence/${stage}-${name}.md`;
            stages[stage][name] = write(
                path,
                `${stage === 'discovery' && name === 'request' ? 'Request' : stage + ':' + name}\n`,
            );
        }
    }
    const manifest = {
        schemaVersion: 1,
        slice: {
            id: 'first',
            scopeRefs: ['R1', 'S1'],
            deferredRefs: ['R2', 'S2'],
        },
        stages,
    };
    write(`specs/${feature}/spec.md`, `# Example\n\n**Status: ${status}.**\n`);
    manifest.stages.spec.specification = {
        path: `specs/${feature}/spec.md`,
        sha256: createHash('sha256')
            .update(`# Example\n\n**Status: ${status}.**\n`)
            .digest('hex'),
    };
    const save = () =>
        write(`specs/${feature}/workflow.json`, JSON.stringify(manifest));
    save();
    return {
        root,
        feature,
        write,
        manifest,
        save,
        clean: () => rmSync(root, { recursive: true, force: true }),
    };
}

void test('a later-stage claim cannot skip a required earlier artifact', () => {
    const f = fixture();
    try {
        delete f.manifest.stages.spec.gapReview;
        f.save();
        const result = validateFeature(f.root, f.feature, 'integration');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some((finding) =>
                finding.includes('spec.gapReview'),
            ),
        );
    } finally {
        f.clean();
    }
});

void test('changed evidence bytes make a prior stage stale', () => {
    const f = fixture();
    try {
        f.write(`specs/${f.feature}/plan.md`, 'changed\n');
        const result = validateFeature(f.root, f.feature, 'tasks');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some(
                (finding) =>
                    finding.includes('plan.plan') && finding.includes('SHA256'),
            ),
        );
    } finally {
        f.clean();
    }
});

void test('retired feature cannot progress even with otherwise complete evidence', () => {
    const f = fixture('Retired');
    try {
        const result = validateFeature(f.root, f.feature, 'complete');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some((finding) => finding.includes('Retired')),
        );
    } finally {
        f.clean();
    }
});

void test('selected first slice can advance while future refs remain deferred', () => {
    const f = fixture();
    try {
        assert.equal(
            validateFeature(f.root, f.feature, 'implementation').evidenceReady,
            true,
        );
        assert.equal(
            validateFeature(f.root, f.feature, 'complete').evidenceReady,
            true,
        );
    } finally {
        f.clean();
    }
});

void test('discovery starts with a request but does not require finished discovery', () => {
    const f = fixture();
    try {
        delete f.manifest.stages.discovery.discovery;
        f.save();
        assert.equal(
            validateFeature(f.root, f.feature, 'discovery').evidenceReady,
            true,
        );
        assert.equal(
            validateFeature(f.root, f.feature, 'scope').evidenceReady,
            false,
        );
    } finally {
        f.clean();
    }
});

void test('outside-root and malformed evidence are rejected', () => {
    const f = fixture();
    try {
        f.manifest.stages.scope.slices.path = '../outside.md';
        f.save();
        const result = validateFeature(f.root, f.feature, 'spec');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some((finding) => finding.includes('scope.slices')),
        );
    } finally {
        f.clean();
    }
});

void test('malformed CLI arguments fail with actionable usage', () => {
    const result = spawnSync(
        process.execPath,
        [script, '004-example', 'unknown-stage'],
        { encoding: 'utf8' },
    );
    assert.equal(result.status, 2);
    assert.match(result.stderr, /Usage:.*validate-feature/);
});

void test('new discovery works before spec.md exists', () => {
    const f = fixture();
    try {
        rmSync(join(f.root, 'specs', f.feature, 'spec.md'));
        delete f.manifest.slice;
        f.save();
        assert.equal(
            validateFeature(f.root, f.feature, 'discovery').evidenceReady,
            true,
        );
        assert.equal(
            validateFeature(f.root, f.feature, 'scope').evidenceReady,
            true,
        );
    } finally {
        f.clean();
    }
});

void test('additional split-spec source hash is checked', () => {
    const f = fixture();
    try {
        f.manifest.stages.spec.sources = [
            f.write(`specs/${f.feature}/ordering.md`, 'R1 old\n'),
        ];
        f.save();
        f.write(`specs/${f.feature}/ordering.md`, 'R1 new\n');
        const result = validateFeature(f.root, f.feature, 'plan');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some(
                (finding) =>
                    finding.includes('spec.sources[0]') &&
                    finding.includes('SHA256'),
            ),
        );
    } finally {
        f.clean();
    }
});

void test('evidence from another slice cannot satisfy the gate', () => {
    const f = fixture();
    try {
        f.manifest.stages.implementation.sliceId = 'other';
        f.save();
        const result = validateFeature(f.root, f.feature, 'review');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some((finding) =>
                finding.includes('implementation.sliceId'),
            ),
        );
    } finally {
        f.clean();
    }
});

void test('malformed manifest fails closed', () => {
    const f = fixture();
    try {
        f.write(`specs/${f.feature}/workflow.json`, '{broken');
        assert.throws(
            () => validateFeature(f.root, f.feature, 'plan'),
            /valid JSON/,
        );
    } finally {
        f.clean();
    }
});

void test('complete requires the final acceptance evidence', () => {
    const f = fixture();
    try {
        delete f.manifest.stages.acceptance.userAcceptance;
        f.save();
        const result = validateFeature(f.root, f.feature, 'complete');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some((finding) =>
                finding.includes('acceptance.userAcceptance'),
            ),
        );
        assert.equal(
            validateFeature(f.root, f.feature, 'acceptance').evidenceReady,
            true,
        );
    } finally {
        f.clean();
    }
});

void test('superseded feature status blocks progression', () => {
    const f = fixture('Superseded');
    try {
        const result = validateFeature(f.root, f.feature, 'plan');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some((finding) => finding.includes('Superseded')),
        );
    } finally {
        f.clean();
    }
});

void test('symlinked evidence outside the repository is rejected', () => {
    const f = fixture();
    const outside = mkdtempSync(join(tmpdir(), 'feature-outside-'));
    try {
        const path = join(f.root, 'evidence', 'review-independentReview.md');
        rmSync(path);
        writeFileSync(join(outside, 'review.md'), 'external review\n');
        symlinkSync(join(outside, 'review.md'), path);
        const result = validateFeature(f.root, f.feature, 'integration');
        assert.equal(result.evidenceReady, false);
        assert.ok(
            result.findings.some(
                (finding) =>
                    finding.includes('review.independentReview') &&
                    finding.includes('symlink'),
            ),
        );
    } finally {
        f.clean();
        rmSync(outside, { recursive: true, force: true });
    }
});
