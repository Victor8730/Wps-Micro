<?php

declare(strict_types=1);

namespace WpsMicro\Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \RuntimeException implements ContainerExceptionInterface
{
}
