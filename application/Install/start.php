<?php

declare(strict_types=1);

namespace Install;

$dirFrontEnd = dirname(__DIR__, 2);

require_once $dirFrontEnd . '/application/vendor/autoload.php';

$install = new Install();

$install->copyFileAndDirectory($dirFrontEnd . '/application/vendor/webpagestudio/wps-micro/dist', $dirFrontEnd);

