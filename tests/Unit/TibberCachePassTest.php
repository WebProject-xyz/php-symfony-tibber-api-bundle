<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberCachePass;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberRegistryPass;
use WebProject\Symfony\TibberApiBundle\Service\CachedTibberService;
use WebProject\TibberApiClient\Service\TibberService;

class TibberCachePassTest extends Unit
{
    public function testProcessCreatesDecoratorWhenCachePoolExists(): void
    {
        $container = new ContainerBuilder();

        // Register fake cache pool definition
        $container->setDefinition('cache.app', new Definition());

        // Register service with cache tag
        $serviceDef = new Definition(TibberService::class);
        $serviceDef->addTag(TibberRegistryPass::SERVICE_TAG, [
            'account'    => 'primary',
            'cache_pool' => 'cache.app',
            'cache_ttl'  => ['today_prices' => 1800],
        ]);
        $container->setDefinition('tibber_api.service.primary', $serviceDef);

        $pass = new TibberCachePass();
        $pass->process($container);

        self::assertTrue($container->hasDefinition('tibber_api.service.cached.primary'));

        $cachedDef = $container->getDefinition('tibber_api.service.cached.primary');
        self::assertSame(CachedTibberService::class, $cachedDef->getClass());
        self::assertSame(['tibber_api.service.primary', null, 0], $cachedDef->getDecoratedService());

        $args = $cachedDef->getArguments();
        self::assertSame('cache.app', (string) $args[1]);
        self::assertSame(['today_prices' => 1800], $args[2]);
        self::assertSame('tibber_primary_', $args[3]);
    }

    public function testProcessThrowsExceptionWhenCachePoolDoesNotExist(): void
    {
        $container = new ContainerBuilder();

        $serviceDef = new Definition(TibberService::class);
        $serviceDef->addTag(TibberRegistryPass::SERVICE_TAG, [
            'account'    => 'primary',
            'cache_pool' => 'non_existent_cache_pool',
        ]);
        $container->setDefinition('tibber_api.service.primary', $serviceDef);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The cache pool "non_existent_cache_pool" configured for Tibber account "primary" does not exist.');

        $pass = new TibberCachePass();
        $pass->process($container);
    }
}
