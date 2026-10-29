import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

function settings(text) {
    return Object.fromEntries(
        text
            .split(/\r?\n/)
            .filter((line) => /^\s*[A-Z_]+\s*=/.test(line))
            .map((line) => {
                const split = line.indexOf('=');
                return [
                    line.slice(0, split).trim(),
                    line
                        .slice(split + 1)
                        .trim()
                        .replace(/^(['"])(.*)\1$/, '$2'),
                ];
            }),
    );
}
export function taskEnvironment(
    number,
    cwd,
    inherited = process.env,
    args = [],
) {
    if (!/^\d+$/.test(number))
        throw new Error('Task number must contain digits only');
    const database = 'guixa_test_dra' + number;
    const permitted = (value) =>
        value === database ||
        new RegExp('^' + database + '_test_[0-9]+$').test(value);
    const validate = (values) => {
        if (values.DB_DATABASE && !permitted(values.DB_DATABASE))
            throw new Error('Unsafe task database override');
        if (values.DB_URL || values.DATABASE_URL)
            throw new Error('Database URL overrides are not supported');
        if (values.DB_CONNECTION && values.DB_CONNECTION !== 'pgsql')
            throw new Error('Unexpected task database connection');
        if (values.APP_CONFIG_CACHE)
            throw new Error('Custom cached configuration is not supported');
    };
    validate(inherited);
    for (const name of ['.env', '.env.testing']) {
        if (existsSync(resolve(cwd, name)))
            validate(settings(readFileSync(resolve(cwd, name), 'utf8')));
    }
    if (existsSync(resolve(cwd, 'bootstrap/cache/config.php')))
        throw new Error(
            'Cached configuration prevents database isolation verification',
        );
    if (
        args.some((arg) =>
            /^(--(?:env|database|configuration|db)(?:=|$)|-c|(?:DB_|DATABASE_URL|APP_CONFIG_CACHE))/.test(
                arg,
            ),
        )
    )
        throw new Error('Command configuration overrides are not permitted');
    const xml = resolve(cwd, 'phpunit.xml');
    if (existsSync(xml)) {
        for (const tag of readFileSync(xml, 'utf8').match(
            /<(?:env|server)\b[^>]*>/g,
        ) ?? []) {
            const fields = Object.fromEntries(
                [...tag.matchAll(/(\w+)\s*=\s*(['"])(.*?)\2/g)].map((m) => [
                    m[1],
                    m[3],
                ]),
            );
            if (fields.force === 'true' || fields.force === '1')
                validate({ [fields.name]: fields.value });
        }
    }
    return {
        ...inherited,
        DB_DATABASE: inherited.DB_DATABASE || database,
        DB_CONNECTION: 'pgsql',
        DB_URL: '',
        DATABASE_URL: '',
        PGTZ: 'UTC',
        APP_ENV: 'testing',
    };
}

if (
    process.argv[1] &&
    resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
    try {
        const [number, separator, executable, ...args] = process.argv.slice(2);
        if (separator !== '--' || !executable)
            throw new Error(
                'Usage: node task-command.mjs <task-number> -- <executable> [arguments]',
            );
        const env = taskEnvironment(number, process.cwd(), process.env, args);
        const result = spawnSync(executable, args, {
            cwd: process.cwd(),
            env,
            stdio: 'inherit',
            shell: false,
        });
        if (result.error) throw new Error('Task command could not start');
        process.exitCode = result.status ?? 1;
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
