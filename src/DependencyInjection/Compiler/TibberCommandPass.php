<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use WebProject\Symfony\TibberApiBundle\Command\ConsumptionCommand;
use WebProject\Symfony\TibberApiBundle\Command\CurrentPriceCommand;
use WebProject\Symfony\TibberApiBundle\Command\PushNotificationCommand;
use WebProject\Symfony\TibberApiBundle\Command\SchemaDumpCommand;
use WebProject\Symfony\TibberApiBundle\Command\TodayPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\TomorrowPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\ViewerCommand;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;

use function class_exists;

class TibberCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(Command::class)) {
            return;
        }

        $serviceCommands = [
            ViewerCommand::class           => [new Reference(TibberServiceRegistryInterface::class)],
            TodayPricesCommand::class      => [new Reference(TibberServiceRegistryInterface::class)],
            TomorrowPricesCommand::class   => [new Reference(TibberServiceRegistryInterface::class)],
            CurrentPriceCommand::class     => [new Reference(TibberServiceRegistryInterface::class)],
            ConsumptionCommand::class      => [new Reference(TibberServiceRegistryInterface::class)],
            PushNotificationCommand::class => [new Reference(TibberServiceRegistryInterface::class)],
        ];

        if ($container->hasDefinition('tibber_api.service_registry')) {
            foreach ($serviceCommands as $commandClass => $arguments) {
                if ($container->hasDefinition($commandClass)) {
                    continue;
                }

                $commandDef = new Definition($commandClass, $arguments);
                $commandDef->addTag('console.command');
                $commandDef->setPublic(false);

                $container->setDefinition($commandClass, $commandDef);
            }
        }

        if ($container->hasDefinition('tibber_api.client_registry') && !$container->hasDefinition(SchemaDumpCommand::class)) {
            $commandDef = new Definition(SchemaDumpCommand::class, [new Reference(TibberClientRegistryInterface::class)]);
            $commandDef->addTag('console.command');
            $commandDef->setPublic(false);

            $container->setDefinition(SchemaDumpCommand::class, $commandDef);
        }
    }
}
