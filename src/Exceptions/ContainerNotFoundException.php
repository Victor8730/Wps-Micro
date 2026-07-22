<?php

declare(strict_types=1);

namespace WpsMicro\Core\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class ContainerNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
