<?php

declare(strict_types=1);

namespace Core;

require_once __DIR__ . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$command = $argv[1] ?? null;
$kernel = Kernel::fromConfigFile(__DIR__ . '/Config/app.php');

if ($command === 'migrate') {
    /** @var Migrator $migrator */
    $migrator = $kernel->getContainer()->get(Migrator::class);
    $applied = $migrator->migrate();

    if ($applied === []) {
        echo "Nothing to migrate.\n";
        exit(0);
    }

    foreach ($applied as $migration) {
        echo 'Migrated: ' . $migration . "\n";
    }

    exit(0);
}

echo "Available commands:\n";
echo "  migrate    Apply pending database migrations\n";
exit($command === null ? 0 : 1);
