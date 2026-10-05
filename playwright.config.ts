import { defineConfig, devices } from '@playwright/test';

const PORT = 8000;
const BASE_URL = `http://127.0.0.1:${PORT}`;

/**
 * Environment of the Laravel server the browser talks to. Real environment
 * variables win over `.env`, so this works the same locally and in CI. Mail
 * goes to Mailpit; sessions, cache and queue need no Redis.
 */
export const serverEnvironment = {
    APP_ENV: 'local',
    APP_URL: BASE_URL,
    DB_DATABASE: process.env.DB_DATABASE ?? 'testing',
    DB_HOST: process.env.DB_HOST ?? '127.0.0.1',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'smtp',
    MAIL_HOST: '127.0.0.1',
    MAIL_PORT: '1025',
};

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: BASE_URL,
        locale: 'ca',
        trace: 'retain-on-failure',
        testIdAttribute: 'data-test',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        // Not `artisan serve`: it rewrites the environment from `.env`, which
        // would undo the overrides above.
        command: `php -S 127.0.0.1:${PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        url: `${BASE_URL}/login`,
        reuseExistingServer: false,
        stdout: 'ignore',
        stderr: 'ignore',
        cwd: 'public',
        env: serverEnvironment,
    },
});
