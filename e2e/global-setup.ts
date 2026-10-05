import { execFileSync } from 'node:child_process';
import { serverEnvironment } from '../playwright.config';
import { clearMailbox } from './support/mailpit';

const SAFE_DATABASE_NAME = /(test|e2e)/;
const LOCAL_HOSTS = ['127.0.0.1', 'localhost'];

/**
 * `migrate:fresh` drops every table, so refuse to run against anything that is
 * not obviously a throwaway database on this machine (CI's service container
 * is also reached through 127.0.0.1).
 */
function assertDisposableDatabase(): void {
    const { DB_DATABASE, DB_HOST } = serverEnvironment;

    if (
        !SAFE_DATABASE_NAME.test(DB_DATABASE) ||
        !LOCAL_HOSTS.includes(DB_HOST)
    ) {
        throw new Error(
            `Refusing to wipe database "${DB_DATABASE}" on "${DB_HOST}": the e2e run needs a local database whose name contains "test" or "e2e" (default: "testing" on 127.0.0.1).`,
        );
    }
}

/**
 * Starts every run from an empty database and mailbox, with one pending
 * invitation. The database is wiped, so it must be a throwaway one.
 */
export default async function globalSetup(): Promise<void> {
    assertDisposableDatabase();
    await clearMailbox();

    const artisan = (...args: string[]) =>
        execFileSync('php', ['artisan', ...args, '--force'], {
            env: { ...process.env, ...serverEnvironment },
            stdio: 'inherit',
        });

    artisan('migrate:fresh');
    artisan('db:seed', '--class=E2eSeeder');
}
