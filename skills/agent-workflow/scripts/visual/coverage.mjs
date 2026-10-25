function requireValue(condition, message) {
    if (!condition) throw new Error(message);
}
function unique(values, label) {
    requireValue(
        Array.isArray(values) && values.length > 0,
        `${label} must not be empty`,
    );
    requireValue(new Set(values).size === values.length, `Duplicate ${label}`);
}
export function expandMatrix(manifest) {
    const { matrix, views } = manifest;
    requireValue(
        ['http:', 'https:'].includes(new URL(manifest.baseUrl).protocol),
        'HTTP baseUrl required',
    );
    unique(matrix?.widths, 'widths');
    unique(matrix?.modes, 'modes');
    unique(matrix?.text, 'text sizes');
    requireValue(
        matrix.widths.every((v) => Number.isInteger(v) && v > 0),
        'Invalid width',
    );
    requireValue(
        matrix.text.every((v) => Number.isFinite(v) && v >= 100),
        'Invalid text size',
    );
    requireValue(
        matrix.modes.every((v) =>
            ['light', 'dark', 'system-light', 'system-dark'].includes(v),
        ),
        'Invalid mode',
    );
    requireValue(Array.isArray(views), 'views required');
    unique(
        views.map((v) => v.id),
        'view ids',
    );
    const cases = [];
    for (const view of views) {
        requireValue(
            typeof view.id === 'string' && /^[a-z0-9-]+$/.test(view.id),
            'Use kebab-case view ids',
        );
        requireValue(
            typeof view.path === 'string' && view.root && view.panels,
            `${view.id}: path, root and panels required`,
        );
        unique(view.fonts, `${view.id} fonts`);
        requireValue(
            Array.isArray(view.focus),
            `${view.id}: focus selectors required`,
        );
        requireValue(
            view.focus.length > 0 ||
                (typeof view.noFocusReason === 'string' &&
                    view.noFocusReason.trim()),
            `${view.id}: explain why there are no keyboard targets`,
        );
        requireValue(Array.isArray(view.states), `${view.id}: states required`);
        unique(
            view.states.map((s) => s.id),
            `${view.id} state ids`,
        );
        for (const state of view.states) {
            requireValue(
                typeof state.id === 'string' && /^[a-z0-9-]+$/.test(state.id),
                'Use kebab-case state ids',
            );
            const checkedView = { ...view, ...state.checks };
            unique(checkedView.fonts, `${view.id}/${state.id} fonts`);
            requireValue(
                checkedView.root && checkedView.panels,
                `${view.id}/${state.id}: selectors required`,
            );
            requireValue(
                Array.isArray(checkedView.focus) &&
                    (checkedView.focus.length > 0 ||
                        (typeof checkedView.noFocusReason === 'string' &&
                            checkedView.noFocusReason.trim())),
                `${view.id}/${state.id}: keyboard coverage required`,
            );
            const selected = Object.hasOwn(state, 'cases')
                ? state.cases
                : matrix.widths.flatMap((width) =>
                      matrix.modes.flatMap((mode) =>
                          matrix.text.map((text) => ({ width, mode, text })),
                      ),
                  );
            requireValue(
                Array.isArray(selected),
                `${view.id}/${state.id}: cases must be an array`,
            );
            unique(
                selected.map((item) => {
                    requireValue(
                        item &&
                            Object.keys(item).sort().join(',') === 'mode,text,width' &&
                            matrix.widths.includes(item.width) &&
                            matrix.modes.includes(item.mode) &&
                            matrix.text.includes(item.text),
                        `${view.id}/${state.id}: invalid case`,
                    );
                    return `${item.width}--${item.mode}--${item.text}`;
                }),
                `${view.id}/${state.id} cases`,
            );
            for (const { width, mode, text } of selected)
                cases.push({
                    id: `${view.id}--${state.id}--${width}--${mode}--${text}`,
                    view: checkedView,
                    state,
                    width,
                    mode,
                    text,
                });
        }
    }
    return cases;
}
export function checkCoverage(manifest, report, candidate) {
    const expected = expandMatrix(manifest);
    const errors = [];
    for (const key of ['head', 'base', 'manifestHash', 'harnessHash']) {
        if (!candidate[key] || report[key] !== candidate[key])
            errors.push(`Stale or missing ${key}`);
    }
    const records = new Map();
    for (const record of report.cases ?? []) {
        if (records.has(record.id)) errors.push(`Duplicate case: ${record.id}`);
        records.set(record.id, record);
    }
    for (const item of expected) {
        const record = records.get(item.id);
        records.delete(item.id);
        if (!record || record.status !== 'passed') {
            errors.push(`Missing or failed case: ${item.id}`);
            continue;
        }
        if (
            !/^[a-z0-9-]+\.png$/.test(record.screenshot ?? '') ||
            !/^[a-f0-9]{64}$/.test(record.sha256 ?? '')
        )
            errors.push(`Invalid capture: ${item.id}`);
        const m = record.measurements;
        if (
            m?.viewport !== item.width ||
            m?.text !== item.text ||
            m?.mode !== item.mode
        )
            errors.push(`Wrong measured matrix: ${item.id}`);
        if (
            !item.view.focus.every((selector) =>
                record.focused?.includes(selector),
            )
        )
            errors.push(`Missing keyboard targets: ${item.id}`);
    }
    for (const id of records.keys()) errors.push(`Unexpected case: ${id}`);
    return errors;
}
