<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use WpsMicro\Core\Container;
use WpsMicro\Core\Exceptions\ContainerException;
use WpsMicro\Core\Exceptions\ContainerNotFoundException;

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

    public function testBoundOnlyReportsExplicitServiceBindings(): void
    {
        $container = new Container();

        self::assertTrue($container->has(AutowireService::class));
        self::assertFalse($container->bound(AutowireService::class));

        $container->set(AutowireService::class, static fn (): AutowireService => new AutowireService(
            new AutowireDependency()
        ));

        self::assertTrue($container->bound(AutowireService::class));
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
