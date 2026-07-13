<?php

declare(strict_types=1);

namespace Core;

use Core\Console\Application;
use Core\Console\Commands\MakeControllerCommand;
use Core\Console\Commands\MakeMigrationCommand;
use Core\Console\Commands\MakeModelCommand;
use Core\Console\Commands\MigrateCommand;
use Core\Console\Commands\MigrateRollbackCommand;

require_once __DIR__ . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$kernel = Kernel::fromConfigFile(__DIR__ . '/Config/app.php');
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
