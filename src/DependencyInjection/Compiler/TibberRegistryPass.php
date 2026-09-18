<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function array_keys;
use function implode;
use function is_string;
use function lcfirst;
use function sprintf;
use function str_replace;

class TibberRegistryPass implements CompilerPassInterface
{
    public const CLIENT_TAG  = 'tibber_api.client';
    public const SERVICE_TAG = 'tibber_api.service';

    public function process(ContainerBuilder $container): void
    {
        $clientMap  = [];
        $serviceMap = [];

        // Collect all tagged clients
        $taggedClients = $container->findTaggedServiceIds(self::CLIENT_TAG);
        foreach ($taggedClients as $id => $tags) {
            foreach ($tags as $attributes) {
                $account = isset($attributes['account']) ? (string) $attributes['account'] : null;
                if (null !== $account && '' !== $account) {
                    if (isset($clientMap[$account])) {
                        throw new InvalidArgumentException(sprintf('Duplicate Tibber client registered for account "%s" (conflicting service ID: "%s").', $account, $id));
                    }

                    $clientMap[$account] = new Reference($id);

                    // Register named autowiring and #[Target] aliases
                    $normalized = str_replace('-', '_', $account);
                    $camelCase  = lcfirst(Container::camelize($normalized));
                    $aliases    = [
                        sprintf('%s $%sTibberClient', TibberClientInterface::class, $camelCase),
                        sprintf('%s $%s', TibberClientInterface::class, $camelCase),
                        sprintf('%s $%s', TibberClientInterface::class, $account),
                        sprintf('%s $%sTibberClient', TibberClientInterface::class, $account),
                    ];
                    foreach ($aliases as $alias) {
                        if (!$container->hasAlias($alias)) {
                            $container->setAlias($alias, $id)->setPublic(false);
                        }
                    }
                }
            }
        }

        // Collect all tagged services
        $taggedServices = $container->findTaggedServiceIds(self::SERVICE_TAG);
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $account = isset($attributes['account']) ? (string) $attributes['account'] : null;
                if (null !== $account && '' !== $account) {
                    if (isset($serviceMap[$account])) {
                        throw new InvalidArgumentException(sprintf('Duplicate Tibber service registered for account "%s" (conflicting service ID: "%s").', $account, $id));
                    }

                    $serviceMap[$account] = new Reference($id);

                    // Register named autowiring and #[Target] aliases
                    $normalized = str_replace('-', '_', $account);
                    $camelCase  = lcfirst(Container::camelize($normalized));
                    $aliases    = [
                        sprintf('%s $%sTibberService', TibberServiceInterface::class, $camelCase),
                        sprintf('%s $%s', TibberServiceInterface::class, $camelCase),
                        sprintf('%s $%s', TibberServiceInterface::class, $account),
                        sprintf('%s $%sTibberService', TibberServiceInterface::class, $account),
                    ];
                    foreach ($aliases as $alias) {
                        if (!$container->hasAlias($alias)) {
                            $container->setAlias($alias, $id)->setPublic(false);
                        }
                    }
                }
            }
        }

        // Resolve default account
        $defaultAccount = 'default';
        if ($container->hasParameter('tibber_api.default_account')) {
            $param = $container->getParameter('tibber_api.default_account');
            if (is_string($param) && '' !== $param) {
                $defaultAccount = $param;
            }
        }

        if ([] !== $serviceMap && !isset($serviceMap[$defaultAccount])) {
            throw new InvalidArgumentException(sprintf('The configured default Tibber account "%s" does not match any registered service accounts ("%s").', $defaultAccount, implode('", "', array_keys($serviceMap))));
        }

        // Configure default autowiring aliases if matching service exists
        $defaultClientId  = sprintf('tibber_api.client.%s', $defaultAccount);
        $defaultServiceId = sprintf('tibber_api.service.%s', $defaultAccount);

        if ($container->hasDefinition($defaultClientId) && !$container->hasAlias(TibberClientInterface::class)) {
            $container->setAlias(TibberClientInterface::class, $defaultClientId)->setPublic(false);
        }

        if ($container->hasDefinition($defaultServiceId) && !$container->hasAlias(TibberServiceInterface::class)) {
            $container->setAlias(TibberServiceInterface::class, $defaultServiceId)->setPublic(false);
        }

        // Configure Client Registry
        if ($container->hasDefinition('tibber_api.client_registry')) {
            $clientLocatorRef = ServiceLocatorTagPass::register($container, $clientMap);
            $clientRegistry   = $container->getDefinition('tibber_api.client_registry');
            $clientRegistry->setArgument(0, $clientLocatorRef);
            $clientRegistry->setArgument(2, array_keys($clientMap));
        }

        // Configure Service Registry
        if ($container->hasDefinition('tibber_api.service_registry')) {
            $serviceLocatorRef = ServiceLocatorTagPass::register($container, $serviceMap);
            $serviceRegistry   = $container->getDefinition('tibber_api.service_registry');
            $serviceRegistry->setArgument(0, $serviceLocatorRef);
            $serviceRegistry->setArgument(2, array_keys($serviceMap));
        }
    }
}
