<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use WebProject\Symfony\TibberApiBundle\Command\TodayPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\ViewerCommand;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberCachePass;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberCommandPass;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberRegistryPass;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Service\CachedTibberService;
use WebProject\Symfony\TibberApiBundle\TibberApiBundle;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

class BundleConfigurationTest extends Unit
{
    public function testBundleMetadataAndBuild(): void
    {
        $bundle    = new TibberApiBundle();
        $container = new ContainerBuilder();

        self::assertSame('TibberApiBundle', $bundle->getName());

        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $hasRegistry = false;
        $hasCache    = false;
        $hasCommand  = false;

        foreach ($passes as $pass) {
            if ($pass instanceof TibberRegistryPass) {
                $hasRegistry = true;
            }
            if ($pass instanceof TibberCachePass) {
                $hasCache = true;
            }
            if ($pass instanceof TibberCommandPass) {
                $hasCommand = true;
            }
        }

        self::assertTrue($hasRegistry, 'TibberRegistryPass must be registered.');
        self::assertTrue($hasCache, 'TibberCachePass must be registered.');
        self::assertTrue($hasCommand, 'TibberCommandPass must be registered.');
    }

    public function testConfigurationNormalizationWithShorthandToken(): void
    {
        $bundle    = new TibberApiBundle();
        $container = new ContainerBuilder();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        $processor = new Processor();

        /** @var array<string, mixed> $processed */
        $processed = $processor->processConfiguration($configuration, [[
            'access_token' => 'secret-tibber-token',
            'cache_pool'   => 'cache.app',
        ]]);

        self::assertSame('default', $processed['default_account']);
        self::assertArrayHasKey('default', $processed['accounts']);
        self::assertSame('secret-tibber-token', $processed['accounts']['default']['access_token']);
        self::assertSame('cache.app', $processed['accounts']['default']['cache_pool']);
        self::assertSame(CachedTibberService::DEFAULT_TTL_VIEWER, $processed['accounts']['default']['cache_ttl']['viewer']);
    }

    public function testConfigurationWithMultiAccount(): void
    {
        $bundle    = new TibberApiBundle();
        $container = new ContainerBuilder();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        $processor = new Processor();

        /** @var array<string, mixed> $processed */
        $processed = $processor->processConfiguration($configuration, [[
            'default_account' => 'secondary',
            'accounts'        => [
                'primary'   => ['access_token' => 'token-primary'],
                'secondary' => [
                    'access_token' => 'token-secondary',
                    'cache_pool'   => 'cache.tibber',
                    'cache_ttl'    => [
                        'current_price' => 120,
                    ],
                ],
            ],
        ]]);

        self::assertSame('secondary', $processed['default_account']);
        self::assertSame('token-primary', $processed['accounts']['primary']['access_token']);
        self::assertSame('token-secondary', $processed['accounts']['secondary']['access_token']);
        self::assertSame('cache.tibber', $processed['accounts']['secondary']['cache_pool']);
        self::assertSame(120, $processed['accounts']['secondary']['cache_ttl']['current_price']);
    }

    public function testLoadExtensionAndCompilerPasses(): void
    {
        $bundle    = new TibberApiBundle();
        $container = new ContainerBuilder();

        // Register dummy cache pool
        $container->setDefinition('cache.app', new Definition());

        $config = [
            'default_account' => 'secondary',
            'accounts'        => [
                'primary'   => [
                    'access_token' => 'token-primary',
                    'endpoint'     => 'https://api.tibber.com/v1-beta/gql',
                    'user_agent'   => 'test-agent',
                ],
                'secondary' => [
                    'access_token' => 'token-secondary',
                    'cache_pool'   => 'cache.app',
                    'cache_ttl'    => ['today_prices' => 7200],
                ],
            ],
        ];

        $configurator = $this->createMock(ContainerConfigurator::class);
        $bundle->loadExtension($config, $configurator, $container);

        // Process compiler passes
        (new TibberRegistryPass())->process($container);
        (new TibberCachePass())->process($container);
        (new TibberCommandPass())->process($container);

        // Registries
        self::assertTrue($container->hasDefinition('tibber_api.client_registry'));
        self::assertTrue($container->hasDefinition('tibber_api.service_registry'));
        self::assertTrue($container->hasAlias(TibberClientRegistryInterface::class));
        self::assertTrue($container->hasAlias(TibberServiceRegistryInterface::class));

        // Primary account
        self::assertTrue($container->hasDefinition('tibber_api.client.primary'));
        self::assertTrue($container->hasDefinition('tibber_api.service.primary'));
        self::assertFalse($container->hasDefinition('tibber_api.service.cached.primary'));

        // Secondary account (with cache decorator)
        self::assertTrue($container->hasDefinition('tibber_api.client.secondary'));
        self::assertTrue($container->hasDefinition('tibber_api.service.secondary'));
        self::assertTrue($container->hasDefinition('tibber_api.service.cached.secondary'));

        $cachedDef = $container->getDefinition('tibber_api.service.cached.secondary');
        self::assertSame(['tibber_api.service.secondary', null, 0], $cachedDef->getDecoratedService());

        // Default autowiring aliases (resolved by TibberRegistryPass)
        self::assertTrue($container->hasAlias(TibberClientInterface::class));
        self::assertSame('tibber_api.client.secondary', (string) $container->getAlias(TibberClientInterface::class));

        self::assertTrue($container->hasAlias(TibberServiceInterface::class));
        self::assertSame('tibber_api.service.secondary', (string) $container->getAlias(TibberServiceInterface::class));

        // Commands registered by TibberCommandPass
        self::assertTrue($container->hasDefinition(ViewerCommand::class));
        self::assertTrue($container->hasDefinition(TodayPricesCommand::class));
        self::assertTrue($container->getDefinition(ViewerCommand::class)->hasTag('console.command'));
    }
}
