import { randomUUID } from 'node:crypto';
import {
    existsSync,
    mkdirSync,
    readFileSync,
    readdirSync,
    realpathSync,
    writeFileSync,
} from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const specs = join(root, 'specs');
const featurePattern = /^\d{3}-[a-z0-9]+(?:-[a-z0-9]+)*$/;
const kinds = ['Request', 'Q&A', 'Change', 'Deviation'];

function featurePath(feature) {
    if (!featurePattern.test(feature))
        throw new Error('Expected an NNN-feature directory name.');
    const path = join(specs, feature);
    if (
        !existsSync(path) ||
        dirname(realpathSync(path)) !== realpathSync(specs)
    ) {
        throw new Error(
            `Feature directory does not exist inside specs: ${feature}`,
        );
    }
    return path;
}

function readHistory(feature) {
    const features = feature
        ? [feature]
        : readdirSync(specs, { withFileTypes: true })
              .filter(
                  (entry) =>
                      entry.isDirectory() && featurePattern.test(entry.name),
              )
              .map((entry) => entry.name)
              .sort();
    for (const name of features) {
        const path = featurePath(name);
        const history = join(path, 'history');
        const entries = existsSync(history)
            ? readdirSync(history)
                  .filter((file) => file.endsWith('.md'))
                  .sort()
                  .map((file) => join(history, file))
            : [];
        const legacy = join(path, 'log.md');
        if (existsSync(legacy)) entries.unshift(legacy);
        for (const entry of entries) {
            process.stdout.write(`\n<!-- ${relative(root, entry)} -->\n\n`);
            process.stdout.write(readFileSync(entry));
            process.stdout.write('\n');
        }
    }
}

function addEntry(feature, kind, task) {
    const path = featurePath(feature);
    if (!kinds.includes(kind))
        throw new Error(`Expected kind: ${kinds.join(', ')}.`);
    if (task && !/^DRA-\d+$/.test(task))
        throw new Error('Expected task: DRA-NN.');
    const body = readFileSync(0, 'utf8');
    if (!body.trim()) throw new Error('Entry body on stdin must not be blank.');
    const timestamp = new Date().toISOString();
    const filename = `${timestamp.replaceAll(':', '-')}-${task ?? 'no-task'}-${randomUUID()}.md`;
    const history = join(path, 'history');
    mkdirSync(history, { recursive: true });
    // Exclusive creation never replaces a peer's entry, even if a name collides.
    const entry = join(history, filename);
    writeFileSync(
        entry,
        `## ${timestamp} · ${kind}\n\n${task ? `Task: ${task}\n\n` : ''}${body}`,
        { flag: 'wx' },
    );
    console.log(relative(root, entry));
}

try {
    const [command, ...args] = process.argv.slice(2);
    if (command === 'read' && args.length <= 1) readHistory(args[0]);
    else if (command === 'add' && (args.length === 2 || args.length === 3))
        addEntry(...args);
    else
        throw new Error(
            'Usage: history.mjs read [NNN-feature] | add NNN-feature <kind> [DRA-NN] < body.md',
        );
} catch (error) {
    console.error(error.message);
    process.exitCode = 1;
}
