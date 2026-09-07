<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use WebProject\Symfony\TibberApiBundle\Command\ConsumptionCommand;
use WebProject\Symfony\TibberApiBundle\Command\CurrentPriceCommand;
use WebProject\Symfony\TibberApiBundle\Command\PushNotificationCommand;
use WebProject\Symfony\TibberApiBundle\Command\SchemaDumpCommand;
use WebProject\Symfony\TibberApiBundle\Command\TodayPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\TomorrowPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\ViewerCommand;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberCommandPass;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistry;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistry;

use function sprintf;

class TibberCommandPassTest extends Unit
{
    public function testProcessRegistersAllCommandsWhenRegistriesExist(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('tibber_api.client_registry', new Definition(TibberClientRegistry::class));
        $container->setDefinition('tibber_api.service_registry', new Definition(TibberServiceRegistry::class));

        $pass = new TibberCommandPass();
        $pass->process($container);

        $expectedCommands = [
            ViewerCommand::class,
            TodayPricesCommand::class,
            TomorrowPricesCommand::class,
            CurrentPriceCommand::class,
            ConsumptionCommand::class,
            PushNotificationCommand::class,
            SchemaDumpCommand::class,
        ];

        foreach ($expectedCommands as $commandClass) {
            self::assertTrue($container->hasDefinition($commandClass), sprintf('Command %s must be registered.', $commandClass));
            self::assertTrue($container->getDefinition($commandClass)->hasTag('console.command'));
        }
    }

    public function testProcessSkipsWhenRegistriesAreMissing(): void
    {
        $container = new ContainerBuilder();

        $pass = new TibberCommandPass();
        $pass->process($container);

        self::assertFalse($container->hasDefinition(ViewerCommand::class));
    }
}
