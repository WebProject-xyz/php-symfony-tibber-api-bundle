<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberCachePass;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberCommandPass;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberRegistryPass;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistry;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistry;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Service\CachedTibberService;
use WebProject\TibberApiClient\Client\TibberClient;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Service\TibberService;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function array_key_first;
use function array_keys;
use function is_array;
use function sprintf;

class TibberApiBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TibberRegistryPass());
        $container->addCompilerPass(new TibberCachePass());
        $container->addCompilerPass(new TibberCommandPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $rootNode = $definition->rootNode();

        $rootNode
            ->beforeNormalization()
                ->ifTrue(static fn (mixed $v): bool => is_array($v) && isset($v['access_token']) && !isset($v['accounts']))
                ->then(static function (array $v): array {
                    $account = [
                        'access_token' => $v['access_token'],
                    ];
                    if (isset($v['endpoint'])) {
                        $account['endpoint'] = $v['endpoint'];
                    }
                    if (isset($v['user_agent'])) {
                        $account['user_agent'] = $v['user_agent'];
                    }
                    if (isset($v['cache_pool'])) {
                        $account['cache_pool'] = $v['cache_pool'];
                    }
                    if (isset($v['cache_ttl'])) {
                        $account['cache_ttl'] = $v['cache_ttl'];
                    }

                    return [
                        'default_account' => 'default',
                        'accounts'        => ['default' => $account],
                    ];
                })
            ->end()
            ->validate()
                ->ifTrue(static fn (mixed $v): bool => is_array($v) && (!isset($v['accounts']) || [] === $v['accounts']))
                ->thenInvalid('You must configure at least one account under "tibber_api.accounts" or provide "tibber_api.access_token".')
            ->end()
            ->validate()
                ->ifTrue(static fn (mixed $v): bool => is_array($v) && isset($v['default_account'], $v['accounts']) && !isset($v['accounts'][$v['default_account']]))
                ->thenInvalid('The configured default_account does not exist in the configured accounts.')
            ->end()
            ->children()
                ->scalarNode('default_account')->defaultNull()->cannotBeEmpty()->end()
                ->arrayNode('accounts')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('access_token')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('endpoint')->defaultValue(TibberClient::DEFAULT_ENDPOINT)->cannotBeEmpty()->end()
                            ->scalarNode('user_agent')->defaultValue(TibberClient::DEFAULT_USER_AGENT)->cannotBeEmpty()->end()
                            ->scalarNode('cache_pool')->defaultNull()->end()
                            ->arrayNode('cache_ttl')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->integerNode('viewer')->defaultValue(CachedTibberService::DEFAULT_TTL_VIEWER)->min(0)->end()
                                    ->integerNode('homes')->defaultValue(CachedTibberService::DEFAULT_TTL_HOMES)->min(0)->end()
                                    ->integerNode('home')->defaultValue(CachedTibberService::DEFAULT_TTL_HOME)->min(0)->end()
                                    ->integerNode('price_info')->defaultValue(CachedTibberService::DEFAULT_TTL_PRICE_INFO)->min(0)->end()
                                    ->integerNode('today_prices')->defaultValue(CachedTibberService::DEFAULT_TTL_TODAY_PRICES)->min(0)->end()
                                    ->integerNode('tomorrow_prices')->defaultValue(CachedTibberService::DEFAULT_TTL_TOMORROW_PRICES)->min(0)->end()
                                    ->integerNode('current_price')->defaultValue(CachedTibberService::DEFAULT_TTL_CURRENT_PRICE)->min(0)->end()
                                    ->integerNode('consumption')->defaultValue(CachedTibberService::DEFAULT_TTL_CONSUMPTION)->min(0)->end()
                                 ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array<string, array<string, mixed>> $accounts */
        $accounts = $config['accounts'] ?? [];

        /** @var string $defaultAccount */
        $defaultAccount = $config['default_account']
            ?? (isset($accounts['default']) ? 'default' : (array_key_first($accounts) ?? 'default'));

        $builder->setParameter('tibber_api.default_account', $defaultAccount);

        // Register autoconfiguration for custom implementations
        $builder->registerForAutoconfiguration(TibberClientInterface::class)
            ->addTag(TibberRegistryPass::CLIENT_TAG);
        $builder->registerForAutoconfiguration(TibberServiceInterface::class)
            ->addTag(TibberRegistryPass::SERVICE_TAG);

        $accountNames = array_keys($accounts);

        // Register Registry Definitions (wired dynamically in TibberRegistryPass)
        $clientRegistryDef = new Definition(TibberClientRegistry::class, [
            null,
            $defaultAccount,
            $accountNames,
        ]);
        $clientRegistryDef->setPublic(false);
        $builder->setDefinition('tibber_api.client_registry', $clientRegistryDef);
        $builder->setAlias(TibberClientRegistryInterface::class, 'tibber_api.client_registry')->setPublic(false);
        $builder->setAlias(TibberClientRegistry::class, 'tibber_api.client_registry')->setPublic(false);

        $serviceRegistryDef = new Definition(TibberServiceRegistry::class, [
            null,
            $defaultAccount,
            $accountNames,
        ]);
        $serviceRegistryDef->setPublic(false);
        $builder->setDefinition('tibber_api.service_registry', $serviceRegistryDef);
        $builder->setAlias(TibberServiceRegistryInterface::class, 'tibber_api.service_registry')->setPublic(false);
        $builder->setAlias(TibberServiceRegistry::class, 'tibber_api.service_registry')->setPublic(false);

        // Register client & service definitions for each configured account
        foreach ($accounts as $name => $accountConfig) {
            $clientId  = sprintf('tibber_api.client.%s', $name);
            $clientDef = new Definition(TibberClient::class, [
                $accountConfig['access_token'],
                null,
                $accountConfig['endpoint'] ?? TibberClient::DEFAULT_ENDPOINT,
                $accountConfig['user_agent'] ?? TibberClient::DEFAULT_USER_AGENT,
            ]);
            $clientDef->addTag(TibberRegistryPass::CLIENT_TAG, ['account' => $name]);
            $clientDef->setPublic(false);
            $builder->setDefinition($clientId, $clientDef);

            $serviceId  = sprintf('tibber_api.service.%s', $name);
            $serviceDef = new Definition(TibberService::class, [
                new Reference($clientId),
            ]);

            $serviceTagAttributes = ['account' => $name];
            if (isset($accountConfig['cache_pool'])) {
                $serviceTagAttributes['cache_pool'] = $accountConfig['cache_pool'];
                $serviceTagAttributes['cache_ttl']  = $accountConfig['cache_ttl'] ?? [];
            }

            $serviceDef->addTag(TibberRegistryPass::SERVICE_TAG, $serviceTagAttributes);
            $serviceDef->setPublic(false);
            $builder->setDefinition($serviceId, $serviceDef);
        }
    }
}
