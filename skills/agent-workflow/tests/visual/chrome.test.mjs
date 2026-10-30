import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mkdtemp, writeFile, readFile, stat, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { openChrome } from '../../scripts/visual/chrome.mjs';

void test(
    'profile cleanup tolerates a late browser writer and preserves startup errors',
    { timeout: 10000 },
    async () => {
        const cwd = await mkdtemp(join(tmpdir(), 'chrome-cleanup-test-'));
        const binary = join(cwd, 'chrome');
        try {
            await writeFile(
                binary,
                `#!${process.execPath}
const { spawn } = require('node:child_process');
const { mkdirSync, writeFileSync } = require('node:fs');
const { join } = require('node:path');
if (process.argv[2] === '--writer') {
    const profile = process.argv[3];
    process.send('ready', () => {
    const deadline = Date.now() + 250;
    while (Date.now() < deadline) {
        try {
            writeFileSync(join(profile, 'late-write'), 'Chrome profile flush');
        } catch (error) {
            if (error.code !== 'ENOENT') throw error;
        }
    }
    process.exit(0);
    });
} else {
    const profile = process.argv.find(arg => arg.startsWith('--user-data-dir=')).split('=')[1];
    writeFileSync(join(__dirname, 'profile'), profile);
    const directory = join(profile, 'Default');
    mkdirSync(directory);
    writeFileSync(join(directory, 'initial'), 'profile');
    const writer = spawn(process.execPath, [__filename, '--writer', directory], {
        detached: true, stdio: ['ignore', 'ignore', 'ignore', 'ipc'],
    });
    writer.once('message', () => process.exit(1));
}
`,
                { mode: 0o700 },
            );
            for (let attempt = 0; attempt < 5; attempt++) {
                await assert.rejects(openChrome(binary), /Chrome exited: 1/);
                const profile = await readFile(join(cwd, 'profile'), 'utf8');
                await assert.rejects(stat(profile), { code: 'ENOENT' });
            }
        } finally {
            const profile = await readFile(join(cwd, 'profile'), 'utf8');
            await rm(profile, { recursive: true, force: true, maxRetries: 5 });
            await rm(cwd, { recursive: true, force: true, maxRetries: 5 });
        }
    },
);
