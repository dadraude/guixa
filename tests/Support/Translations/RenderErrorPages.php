<?php

// Generate visual fixtures from the application's real Laravel error renderer.
// Run only in the isolated DRA-205 worktree; output belongs outside tracked source.

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$output = $argv[1] ?? null;
if (! is_string($output) || ! str_starts_with((realpath(dirname($output)) ?: '').'/', '/private/tmp/')) {
    throw new RuntimeException('Pass an output directory under /private/tmp.');
}

$app->setLocale('ca');
config()->set('app.debug', false);
if (! is_dir($output) && ! mkdir($output, 0700, true) && ! is_dir($output)) {
    throw new RuntimeException('Unable to create error page output.');
}

$statuses = [401, 402, 403, 404, 419, 429, 500, 503];
foreach ($statuses as $status) {
    Route::middleware('web')->get('/visual-error-'.$status, fn () => abort($status));
    $response = $kernel->handle(Request::create('/visual-error-'.$status));
    if ($response->getStatusCode() !== $status || ! str_contains($response->getContent(), '<html lang="ca">')) {
        throw new RuntimeException('Unexpected rendered error response for '.$status);
    }
    file_put_contents($output.'/'.$status.'.html', $response->getContent());
    $kernel->terminate(Request::create('/visual-error-'.$status), $response);
}

$head = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD'));
file_put_contents($output.'/manifest.json', json_encode([
    'head' => $head,
    'statuses' => $statuses,
    'template' => hash_file('sha256', $root.'/resources/views/errors/minimal.blade.php'),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
