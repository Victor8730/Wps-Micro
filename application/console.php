<?php

declare(strict_types=1);

namespace Core;

use Core\Console\Application;
use Core\Console\Commands\MakeControllerCommand;
use Core\Console\Commands\MakeMigrationCommand;
use Core\Console\Commands\MakeModelCommand;
use Core\Console\Commands\MigrateCommand;
use Core\Console\Commands\MigrateRollbackCommand;

/** @var Kernel $kernel */
$kernel = require __DIR__ . '/bootstrap.php';
$container = $kernel->getContainer();

$rootPath = dirname(__DIR__);

$application = new Application();
$application
    ->add(new MigrateCommand($container))
    ->add(new MigrateRollbackCommand($container))
    ->add(new MakeControllerCommand($rootPath))
    ->add(new MakeModelCommand($rootPath))
    ->add(new MakeMigrationCommand($rootPath));

exit($application->run($argv));
