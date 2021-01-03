<?php

declare(strict_types=1);

namespace Install;

$dirFrontEnd = dirname(__DIR__, 2);

require_once $dirFrontEnd . '/application/vendor/autoload.php';

$update = new Install();

$update->copyFileAndDirectory($dirFrontEnd . '/application/vendor/twbs/bootstrap/dist', $dirFrontEnd);
$update->clearCache($dirFrontEnd . '/application');