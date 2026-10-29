import { existsSync, readFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

function settings(text) {
    return Object.fromEntries(
        text
            .split(/\r?\n/)
            .map((line) =>
                line.match(/^\s*(?:export\s+)?(['"]?)([A-Z_]+)\1\s*=(.*)$/),
            )
            .filter(Boolean)
            .map((match) => [
                match[2],
                match[3].trim().replace(/^(['"])(.*)\1$/, '$2'),
            ]),
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
    const validate = (values, { effective = false } = {}) => {
        const overrides = (name) =>
            effective ? Object.hasOwn(values, name) : Boolean(values[name]);
        if (overrides('DB_DATABASE') && !permitted(values.DB_DATABASE))
            throw new Error('Unsafe task database override');
        if (values.DB_URL || values.DATABASE_URL)
            throw new Error('Database URL overrides are not supported');
        if (overrides('DB_CONNECTION') && values.DB_CONNECTION !== 'pgsql')
            throw new Error('Unexpected task database connection');
        if (Object.hasOwn(values, 'APP_BASE_PATH'))
            throw new Error('Custom application base path is not supported');
        if (values.APP_CONFIG_CACHE)
            throw new Error('Custom cached configuration is not supported');
        if (effective && overrides('APP_ENV') && values.APP_ENV !== 'testing')
            throw new Error('Unexpected task environment override');
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
    const configurations = [
        'phpunit.xml',
        'phpunit.dist.xml',
        'phpunit.xml.dist',
    ]
        .map((name) => resolve(cwd, name))
        .filter((path) => statSync(path, { throwIfNoEntry: false })?.isFile());
    for (const xml of configurations) {
        const source = readFileSync(xml, 'utf8');
        if (/<!DOCTYPE\b|<!ENTITY\b/i.test(source))
            throw new Error(
                'XML declarations and indirection are not supported',
            );
        for (const tag of source.match(
            /<(?:env|server)\b(?:[^"'<>]|"[^"]*"|'[^']*')*>/g,
        ) ?? []) {
            const fields = Object.fromEntries(
                [
                    ...tag.matchAll(/\s+([\w:.-]+)\s*=\s*(['"])([\s\S]*?)\2/g),
                ].map((m) => [m[1], m[3]]),
            );
            if (fields.name?.includes('&') || fields.force?.includes('&'))
                throw new Error(
                    'Encoded XML setting controls are not supported',
                );
            if (
                tag.startsWith('<server') ||
                fields.name === 'APP_CONFIG_CACHE' ||
                fields.name === 'APP_BASE_PATH' ||
                fields.force?.toLowerCase() === 'true' ||
                fields.force === '1'
            )
                validate({ [fields.name]: fields.value }, { effective: true });
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
