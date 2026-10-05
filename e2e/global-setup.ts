import { execFileSync } from 'node:child_process';
import { serverEnvironment } from '../playwright.config';
import { clearMailbox } from './support/mailpit';

/**
 * Starts every run from an empty database and mailbox, with one pending
 * invitation. The database is wiped, so it must be a throwaway one.
 */
export default async function globalSetup(): Promise<void> {
    await clearMailbox();

    const artisan = (...args: string[]) =>
        execFileSync('php', ['artisan', ...args, '--force'], {
            env: { ...process.env, ...serverEnvironment },
            stdio: 'inherit',
        });

    artisan('migrate:fresh');
    artisan('db:seed', '--class=E2eSeeder');
}
