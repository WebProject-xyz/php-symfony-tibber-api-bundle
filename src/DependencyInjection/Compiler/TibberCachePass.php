<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use WebProject\Symfony\TibberApiBundle\Service\CachedTibberService;

use function is_array;
use function is_string;
use function sprintf;

class TibberCachePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds(TibberRegistryPass::SERVICE_TAG);

        foreach ($taggedServices as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $cachePool = $attributes['cache_pool'] ?? null;
                if (!is_string($cachePool) || '' === $cachePool) {
                    continue;
                }

                $account = (string) ($attributes['account'] ?? 'default');

                if (!$container->has($cachePool)) {
                    throw new InvalidArgumentException(sprintf('The cache pool "%s" configured for Tibber account "%s" does not exist.', $cachePool, $account));
                }

                /** @var array<string, int> $cacheTtl */
                $cacheTtl = isset($attributes['cache_ttl']) && is_array($attributes['cache_ttl'])
                    ? $attributes['cache_ttl']
                    : [];

                $cachedServiceId  = sprintf('tibber_api.service.cached.%s', $account);
                $cachedServiceDef = new Definition(CachedTibberService::class, [
                    new Reference($cachedServiceId . '.inner'),
                    new Reference($cachePool),
                    $cacheTtl,
                    sprintf('tibber_%s_', $account),
                ]);
                $cachedServiceDef->setDecoratedService($serviceId);
                $cachedServiceDef->setPublic(false);

                $container->setDefinition($cachedServiceId, $cachedServiceDef);
            }
        }
    }
}
