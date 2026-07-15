<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Container;
use Exceptions\ContainerException;
use Exceptions\ContainerNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    public function testItImplementsPsrContainerAndAutowiresDependencies(): void
    {
        $container = new Container();
        $service = $container->get(AutowireService::class);

        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(AutowireDependency::class, $service->dependency);
        self::assertTrue($container->has(AutowireService::class));
    }

    public function testItResolvesExplicitInterfaceBindingsAsSharedServices(): void
    {
        $container = new Container();
        $container->set(TestContract::class, static fn (): TestContract => new TestImplementation());

        $first = $container->get(ContractConsumer::class);
        $second = $container->get(TestContract::class);

        self::assertInstanceOf(TestImplementation::class, $first->contract);
        self::assertSame($first->contract, $second);
    }

    public function testItRejectsCircularDependencies(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $container->get(CircularA::class);
    }

    public function testItUsesPsrNotFoundExceptions(): void
    {
        $container = new Container();

        $this->expectException(ContainerNotFoundException::class);

        $container->get('Missing\\Service');
    }

    public function testHasRejectsClassesThatCannotBeInstantiated(): void
    {
        $container = new Container();

        self::assertFalse($container->has(AbstractContainerEntry::class));
        self::assertTrue($container->has(AutowireService::class));
    }
}

final class AutowireDependency
{
}

final class AutowireService
{
    public function __construct(public readonly AutowireDependency $dependency)
    {
    }
}

interface TestContract
{
}

final class TestImplementation implements TestContract
{
}

final class ContractConsumer
{
    public function __construct(public readonly TestContract $contract)
    {
    }
}

final class CircularA
{
    public function __construct(CircularB $dependency)
    {
    }
}

final class CircularB
{
    public function __construct(CircularA $dependency)
    {
    }
}

abstract class AbstractContainerEntry
{
}
