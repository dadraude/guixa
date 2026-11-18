// Read-only task selector. It combines a task catalog (from the planning documents) with one evidence
// snapshot (tracker, base, reservations, active pipelines) and recommends a compatible batch. It never
// claims a task: the coordinator rechecks status and ownership immediately before claiming.

export const EVIDENCE_MAX_AGE_MS = 15 * 60 * 1000;
const CLOCK_SKEW_MS = 60 * 1000;
const TIERS = ['S', 'M', 'L'];

export function isFresh(observedAt, now) {
    const age = now - Date.parse(observedAt);
    return (
        Number.isFinite(age) &&
        age >= -CLOCK_SKEW_MS &&
        age <= EVIDENCE_MAX_AGE_MS
    );
}

function staticPrefix(path) {
    return path.replace(/^\.\//, '').split(/[?*[]/, 1)[0].replace(/\/$/, '');
}

// Task details may name a path relative to a known directory (`components/ui/x.vue`), so a path that
// ends with another at a segment boundary also overlaps.
export function pathsOverlap(left, right) {
    const a = staticPrefix(left);
    const b = staticPrefix(right);
    if (!a || !b) return true;
    if (a === b) return true;
    if (a.startsWith(b + '/') || b.startsWith(a + '/')) return true;
    if (a.endsWith('/' + b) || b.endsWith('/' + a)) return true;
    const glob = /[?*[]/.test(left) || /[?*[]/.test(right);
    return glob && (a.startsWith(b) || b.startsWith(a));
}

export function reservationConflicts(task, reservation) {
    if (!Array.isArray(reservation.paths)) return true;
    return task.paths.some((own) =>
        reservation.paths.some((other) => pathsOverlap(own, other)),
    );
}

export function validateCatalog(tasks) {
    const byId = new Map();
    for (const task of tasks) {
        if (byId.has(task.id)) throw new Error(`Duplicate task ${task.id}`);
        if (!TIERS.includes(task.tier))
            throw new Error(`Task ${task.id} has no S, M or L tier`);
        byId.set(task.id, task);
    }
    const visiting = new Set();
    const visited = new Set();
    const visit = (id) => {
        if (visiting.has(id))
            throw new Error(`Start-dependency cycle at ${id}`);
        if (visited.has(id)) return;
        visiting.add(id);
        for (const dependency of byId.get(id).dependencies) {
            if (!byId.has(dependency.task))
                throw new Error(
                    `Unknown dependency ${dependency.task} of ${id}`,
                );
            if (dependency.gate === 'start') visit(dependency.task);
        }
        visiting.delete(id);
        visited.add(id);
    };
    for (const id of byId.keys()) visit(id);
    return byId;
}

function validateEvidence(evidence, now) {
    if (evidence?.version !== 1 || evidence.complete !== true)
        throw new Error('Incomplete evidence; collect again');
    if (!isFresh(evidence.observed_at, now))
        throw new Error('Evidence older than 15 minutes; collect again');
    for (const field of ['issues', 'reservations', 'active'])
        if (!Array.isArray(evidence[field]))
            throw new Error(`Evidence lacks ${field}`);
    const issues = new Map();
    for (const issue of evidence.issues) {
        if (issues.has(issue.id))
            throw new Error(`Duplicate tracker issue ${issue.id}`);
        if (!Array.isArray(issue.labels) || !Array.isArray(issue.blockers))
            throw new Error(`Incomplete tracker fields for ${issue.id}`);
        if (
            !Number.isInteger(issue.priority) ||
            issue.priority < 0 ||
            issue.priority > 4
        )
            throw new Error(`Invalid priority for ${issue.id}`);
        issues.set(issue.id, issue);
    }
    return issues;
}

const DEFAULT_POLICY = {
    capacity: 3,
    largeLimit: 2,
    eligibleStatuses: ['Backlog', 'Todo'],
    doneStatus: 'Done',
    activeStatus: 'In Progress',
    holdLabels: ['hold', 'on-hold', 'blocked'],
};

export function selectTasks(tasks, evidence, options = {}) {
    const policy = { ...DEFAULT_POLICY, ...options };
    const now = options.now ?? Date.now();
    const byId = validateCatalog(tasks);
    validateEvidence(evidence, now);
    const issueByTask = new Map(
        evidence.issues
            .filter((issue) => issue.task)
            .map((issue) => [issue.task, issue]),
    );
    const taskByIssue = new Map(
        [...issueByTask].map(([task, issue]) => [issue.id, task]),
    );
    const integrated = (taskId) => Boolean(evidence.integrated?.[taskId]);
    const baseReady =
        evidence.base?.sha && evidence.base.sha === evidence.base.remote_sha;
    const warnings = [];

    const active = new Map(
        evidence.active.map((pipeline) => [pipeline.owner, pipeline]),
    );
    for (const issue of evidence.issues) {
        if (issue.status !== policy.activeStatus || active.has(issue.id))
            continue;
        const taskId = taskByIssue.get(issue.id);
        active.set(issue.id, {
            owner: issue.id,
            task: taskId,
            tier: byId.get(taskId)?.tier ?? 'L',
        });
    }
    const reservations = [...evidence.reservations];
    for (const pipeline of active.values()) {
        const hasEvidence = reservations.some(
            (r) => r.owner === pipeline.owner,
        );
        if (hasEvidence) continue;
        const task = byId.get(pipeline.task);
        reservations.push({
            owner: pipeline.owner,
            paths: task?.paths ?? null,
            source: task
                ? 'planned scope of active work'
                : 'active work with unknown scope',
        });
    }

    const downstreamCount = (id) => {
        const found = new Set();
        const walk = (parent) => {
            for (const task of tasks) {
                if (found.has(task.id)) continue;
                if (!task.dependencies.some((d) => d.task === parent)) continue;
                found.add(task.id);
                walk(task.id);
            }
        };
        walk(id);
        return [...found].filter((taskId) => !integrated(taskId)).length;
    };

    const rows = tasks.map((task) => {
        const issue = issueByTask.get(task.id);
        const row = {
            task: task.id,
            issue: issue?.id ?? null,
            tier: task.tier,
            priority: issue?.priority ?? 0,
            unblocks: downstreamCount(task.id),
            order: task.order,
            decision: 'eligible',
            reasons: [],
            integration_pending: [],
            warnings: [],
        };
        const reject = (decision, reason) => {
            if (row.decision === 'eligible') row.decision = decision;
            row.reasons.push(reason);
        };

        if (integrated(task.id)) {
            row.decision = 'integrated';
            row.reasons.push('Merged and contained in the base');
            return row;
        }
        if (!issue) {
            reject('needs-scope', 'No tracker issue maps to this task');
            return row;
        }
        if (issue.status === policy.doneStatus) {
            const warning = `${issue.id} is ${policy.doneStatus} but no merged PR for it is contained in the base`;
            row.warnings.push(warning);
            warnings.push(warning);
        }
        if (!policy.eligibleStatuses.includes(issue.status))
            reject(
                issue.status === policy.activeStatus ? 'owned' : 'excluded',
                `Tracker status: ${issue.status}`,
            );
        if (
            issue.labels.some((label) =>
                policy.holdLabels.includes(label.toLowerCase()),
            )
        )
            reject('held', 'Tracker hold label');
        if (!task.paths.length)
            reject('needs-scope', 'Task detail reserves no paths');

        const declared = new Set(task.dependencies.map((d) => d.task));
        for (const blocker of issue.blockers) {
            const blockerTask = taskByIssue.get(blocker);
            if (!blockerTask || !declared.has(blockerTask))
                reject(
                    'needs-scope',
                    `Undeclared tracker blocker ${blocker}; add it to the plan first`,
                );
        }
        for (const dependency of task.dependencies) {
            if (dependency.gate === 'integration') {
                row.integration_pending.push(dependency.task);
                continue;
            }
            if (integrated(dependency.task)) continue;
            const dependencyIssue = issueByTask.get(dependency.task);
            if (dependencyIssue?.status === policy.doneStatus)
                reject(
                    'waiting-integration',
                    `${dependency.task} is ${policy.doneStatus} but not contained in the base`,
                );
            else
                reject(
                    'blocked-dependency',
                    `${dependency.task} is not ${policy.doneStatus}`,
                );
        }

        if (!baseReady)
            reject(
                'needs-environment',
                'Local base does not match the remote base; fetch and collect again',
            );
        const collision = reservations.find(
            (reservation) =>
                reservation.owner !== issue.id &&
                reservationConflicts(task, reservation),
        );
        if (collision)
            reject(
                'waiting-integration',
                `Reserved by ${collision.owner} (${collision.source ?? 'overlapping paths'})`,
            );
        return row;
    });

    for (const issue of evidence.issues)
        if (!issue.task) warnings.push(`Unmapped tracker issue ${issue.id}`);
    if (evidence.merged_complete === false)
        warnings.push(
            'Merged-PR listing hit its limit; integration proof may be missing',
        );

    const eligible = rows
        .filter((row) => row.decision === 'eligible')
        .sort(
            (a, b) =>
                (a.priority || 5) - (b.priority || 5) ||
                b.unblocks - a.unblocks ||
                a.order - b.order ||
                a.task.localeCompare(b.task),
        );
    const activeList = [...active.values()];
    let large = activeList.filter((pipeline) => pipeline.tier === 'L').length;
    const capacity = Math.max(
        0,
        Math.min(
            policy.limit ?? policy.capacity,
            policy.capacity - activeList.length,
        ),
    );
    const selected = [];
    for (const row of eligible) {
        const task = byId.get(row.task);
        const peer = selected.find((id) =>
            reservationConflicts(task, { paths: byId.get(id).paths }),
        );
        if (peer) {
            row.decision = 'queued';
            row.reasons.push(`Overlaps selected ${peer}`);
            continue;
        }
        if (
            selected.length >= capacity ||
            (row.tier === 'L' && large >= policy.largeLimit)
        ) {
            row.decision = 'queued';
            row.reasons.push('Pipeline or L-tier capacity is full');
            continue;
        }
        selected.push(row.task);
        if (row.tier === 'L') large++;
        row.reasons.push(
            'Eligible; ranked by priority, tasks unblocked and plan order',
        );
    }

    return {
        observed_at: evidence.observed_at,
        advisory: true,
        next: selected[0] ?? null,
        selected,
        active: activeList.map((pipeline) => pipeline.owner),
        rows,
        warnings,
        note: 'Read-only recommendation. Recheck tracker status and ownership immediately before claiming.',
    };
}
