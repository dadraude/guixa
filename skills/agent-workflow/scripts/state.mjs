import { readFileSync, writeFileSync, renameSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const stages = [
    'implementation',
    'verification',
    'review',
    'ci',
    'decision-wait',
];
export function applyEvent(owner, event) {
    const next = structuredClone(owner);
    if (!next.pipeline) {
        if (event.type !== 'initialize' || !event.evidence?.trim())
            throw new Error(
                'Initialize with recovered counters and admission evidence; unknown history cannot reset',
            );
        const counters = event.counters;
        if (
            !counters ||
            !Number.isInteger(counters.substantive) ||
            counters.substantive < 0 ||
            !Number.isInteger(counters.mechanical) ||
            counters.mechanical < 0 ||
            !counters.product ||
            !counters.recovery ||
            [
                ...Object.values(counters.product),
                ...Object.values(counters.recovery),
            ].some((n) => !Number.isInteger(n) || n < 0)
        )
            throw new Error('Explicit known counter baseline required');
        const limits = {
            substantive: 3,
            mechanical: 2,
            product: 2,
            recovery: 2,
            ...owner.workflowLimits,
        };
        if (Object.values(limits).some((n) => !Number.isInteger(n) || n < 1))
            throw new Error('Invalid profile limits');
        next.pipeline = {
            disposition: 'active',
            reservations: [],
            events: [],
            counters: structuredClone(counters),
            mechanisms: {},
            periods: [],
            limits,
        };
    } else if (event.type === 'initialize')
        throw new Error('An existing pipeline cannot be initialized again');
    const state = next.pipeline;
    const at = event.at ?? new Date().toISOString();
    if (!Number.isFinite(Date.parse(at)))
        throw new Error('Event timestamp must be an ISO date');
    if (
        state.events.length &&
        Date.parse(at) < Date.parse(state.events.at(-1).at)
    )
        throw new Error('Events must be chronological');
    if (event.type === 'initialize') {
        // The explicit baseline is persisted with its evidence below.
    } else if (event.type === 'review') {
        if (
            !['substantive', 'mechanical'].includes(event.classification) ||
            !['clean', 'findings'].includes(event.verdict)
        )
            throw new Error('Classify the review and verdict');
        const key = event.classification,
            limit = state.limits[key];
        if (state.counters[key] >= limit)
            throw new Error('Review budget already exhausted');
        state.counters[key]++;
        if (state.counters[key] === limit && event.verdict === 'findings')
            state.blocker = 'Review budget exhausted';
    } else if (['failure', 'success', 'recovery'].includes(event.type)) {
        if (
            !['local', 'visual', 'ci'].includes(event.stage) ||
            !['product', 'harness', 'infrastructure', 'mechanical'].includes(
                event.classification,
            ) ||
            !event.mechanism?.trim()
        )
            throw new Error(
                'Validation needs stage, classification and stable failure mechanism',
            );
        const key = event.stage + ':' + event.mechanism;
        if (
            state.mechanisms[key] &&
            state.mechanisms[key] !== event.classification
        )
            throw new Error(
                'Reclassification cannot reset a failure mechanism',
            );
        state.mechanisms[key] = event.classification;
        const counter =
            event.classification === 'product' ? 'product' : 'recovery';
        const current = state.counters[counter][event.stage] ?? 0;
        if (event.type === 'recovery') {
            if (counter === 'product' || !event.evidence?.trim())
                throw new Error(
                    'Recovery requires non-product diagnosis and focused correction evidence',
                );
            if (current >= state.limits.recovery)
                throw new Error('Validation budget already exhausted');
            state.counters.recovery[event.stage] = current + 1;
        } else if (event.type === 'failure') {
            if (counter === 'product') {
                if (current >= state.limits.product)
                    throw new Error('Validation budget already exhausted');
                state.counters.product[event.stage] = current + 1;
            }
            if (
                (counter === 'product' ? current + 1 : current) >=
                state.limits[counter]
            )
                state.blocker =
                    'Validation budget exhausted: ' +
                    event.stage +
                    '/' +
                    counter;
        } else if (counter === 'product')
            state.counters.product[event.stage] = 0;
    } else if (event.type === 'disposition') {
        if (
            !['active', 'deferred', 'superseded', 'abandoned'].includes(
                event.value,
            ) ||
            !event.evidence?.trim()
        )
            throw new Error('Disposition requires explicit decision evidence');
        state.disposition = event.value;
        state.dispositionEvidence = event.evidence;
        // Recording abandonment is not proof that a previous worker stopped writing.
    } else if (event.type === 'reservations') {
        if (!Array.isArray(event.paths) || !event.evidence?.trim())
            throw new Error(
                'Reservations require paths and ownership evidence',
            );
        state.reservations = event.paths;
        state.reservationEvidence = event.evidence;
    } else if (['start', 'end'].includes(event.type)) {
        if (!stages.includes(event.stage))
            throw new Error('Unknown timing stage');
        const open = state.periods.findLast(
            (period) => period.stage === event.stage && !period.end,
        );
        if (event.type === 'start') {
            if (open) throw new Error('Stage already running');
            state.periods.push({ stage: event.stage, start: at });
        } else {
            if (!open) throw new Error('Stage was not started');
            open.end = at;
        }
    } else throw new Error('Unknown pipeline event');
    state.events.push({ ...event, at });
    state.durationsMs = Object.fromEntries(
        stages.map((stage) => [
            stage,
            state.periods
                .filter((period) => period.stage === stage && period.end)
                .reduce(
                    (sum, period) =>
                        sum + Date.parse(period.end) - Date.parse(period.start),
                    0,
                ),
        ]),
    );
    return next;
}

export function translationAdditions(base, candidate) {
    if (
        !base ||
        !candidate ||
        typeof base !== 'object' ||
        typeof candidate !== 'object' ||
        Array.isArray(base) ||
        Array.isArray(candidate)
    )
        return null;
    if (
        Object.values(base).some((value) => typeof value !== 'string') ||
        Object.values(candidate).some((value) => typeof value !== 'string')
    )
        return null;
    if (Object.entries(base).some(([key, value]) => candidate[key] !== value))
        return null;
    return Object.keys(candidate).filter((key) => !Object.hasOwn(base, key));
}
export function compatibleTranslations(base, left, right) {
    const a = translationAdditions(base, left),
        b = translationAdditions(base, right);
    return a !== null && b !== null && !a.some((key) => b.includes(key));
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    try {
        const [ownerPath, eventPath] = process.argv.slice(2);
        if (!ownerPath || !eventPath)
            throw new Error(
                'Usage: node state.mjs <implementation-owner.json> <event.json>',
            );
        const state = applyEvent(
            JSON.parse(readFileSync(ownerPath)),
            JSON.parse(readFileSync(eventPath)),
        );
        writeFileSync(ownerPath + '.tmp', JSON.stringify(state, null, 2), {
            mode: 0o600,
        });
        renameSync(ownerPath + '.tmp', ownerPath);
        console.log(
            JSON.stringify({
                disposition: state.pipeline.disposition,
                counters: state.pipeline.counters,
                blocker: state.pipeline.blocker ?? null,
                durationsMs: state.pipeline.durationsMs,
            }),
        );
        if (state.pipeline.blocker) process.exitCode = 1;
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
