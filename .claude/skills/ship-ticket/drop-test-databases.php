<?php

// Drops the test databases one ticket created: testing_draNN and testing_draNN_* (parallel workers,
// reviewers). Usage: php drop-test-databases.php <NN> [--dry-run]
// It only ever matches names under testing_draNN, so `guixa`, `testing` and `postgres` are unreachable.

$ticket = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);

if (! preg_match('/^\d+$/', $ticket)) {
    fwrite(STDERR, "Usage: php drop-test-databases.php <ticket number> [--dry-run]\n");
    exit(1);
}

$env = [];
foreach (file(__DIR__.'/../../../.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^(DB_[A-Z_]+)=(.*)$/', $line, $match)) {
        $env[$match[1]] = trim($match[2], '"\'');
    }
}
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=postgres', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '5432');
$pdo = new PDO($dsn, $env['DB_USERNAME'] ?? 'postgres', $env['DB_PASSWORD'] ?? '');

$prefix = "testing_dra{$ticket}";
$names = $pdo->query('SELECT datname FROM pg_database WHERE NOT datistemplate')->fetchAll(PDO::FETCH_COLUMN);
$owned = array_filter($names, fn (string $name) => preg_match("/^{$prefix}(_[a-z0-9_]+)?$/", $name));

foreach ($owned as $name) {
    echo ($dryRun ? 'would drop ' : 'dropping ').$name."\n";
    if (! $dryRun) {
        $pdo->exec('DROP DATABASE IF EXISTS "'.$name.'" WITH (FORCE)');
    }
}

if ($owned === []) {
    echo "no databases under {$prefix}\n";
}
