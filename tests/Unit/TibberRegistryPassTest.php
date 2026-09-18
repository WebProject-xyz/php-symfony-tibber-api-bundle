<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use WebProject\Symfony\TibberApiBundle\DependencyInjection\Compiler\TibberRegistryPass;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistry;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistry;
use WebProject\TibberApiClient\Client\TibberClient;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Service\TibberService;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

class TibberRegistryPassTest extends Unit
{
    public function testProcessRegistersLocatorsDefaultAndNamedAliases(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('tibber_api.default_account', 'primary');

        // Register registry definitions
        $clientRegistryDef = new Definition(TibberClientRegistry::class, [null, 'primary', []]);
        $container->setDefinition('tibber_api.client_registry', $clientRegistryDef);

        $serviceRegistryDef = new Definition(TibberServiceRegistry::class, [null, 'primary', []]);
        $container->setDefinition('tibber_api.service_registry', $serviceRegistryDef);

        // Register two accounts
        $client1 = new Definition(TibberClient::class, ['token1']);
        $client1->addTag(TibberRegistryPass::CLIENT_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.client.primary', $client1);

        $client2 = new Definition(TibberClient::class, ['token2']);
        $client2->addTag(TibberRegistryPass::CLIENT_TAG, ['account' => 'secondary_account']);
        $container->setDefinition('tibber_api.client.secondary_account', $client2);

        $service1 = new Definition(TibberService::class, [new Reference('tibber_api.client.primary')]);
        $service1->addTag(TibberRegistryPass::SERVICE_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.service.primary', $service1);

        $service2 = new Definition(TibberService::class, [new Reference('tibber_api.client.secondary_account')]);
        $service2->addTag(TibberRegistryPass::SERVICE_TAG, ['account' => 'secondary_account']);
        $container->setDefinition('tibber_api.service.secondary_account', $service2);

        $pass = new TibberRegistryPass();
        $pass->process($container);

        // Verify default autowiring aliases
        self::assertTrue($container->hasAlias(TibberClientInterface::class));
        self::assertSame('tibber_api.client.primary', (string) $container->getAlias(TibberClientInterface::class));

        self::assertTrue($container->hasAlias(TibberServiceInterface::class));
        self::assertSame('tibber_api.service.primary', (string) $container->getAlias(TibberServiceInterface::class));

        // Verify named autowiring aliases
        self::assertTrue($container->hasAlias(TibberClientInterface::class . ' $primaryTibberClient'));
        self::assertTrue($container->hasAlias(TibberClientInterface::class . ' $primary'));
        self::assertSame('tibber_api.client.primary', (string) $container->getAlias(TibberClientInterface::class . ' $primaryTibberClient'));
        self::assertSame('tibber_api.client.primary', (string) $container->getAlias(TibberClientInterface::class . ' $primary'));

        self::assertTrue($container->hasAlias(TibberClientInterface::class . ' $secondaryAccountTibberClient'));
        self::assertTrue($container->hasAlias(TibberClientInterface::class . ' $secondaryAccount'));
        self::assertSame('tibber_api.client.secondary_account', (string) $container->getAlias(TibberClientInterface::class . ' $secondaryAccountTibberClient'));

        self::assertTrue($container->hasAlias(TibberServiceInterface::class . ' $primaryTibberService'));
        self::assertTrue($container->hasAlias(TibberServiceInterface::class . ' $primary'));
        self::assertSame('tibber_api.service.primary', (string) $container->getAlias(TibberServiceInterface::class . ' $primaryTibberService'));
        self::assertSame('tibber_api.service.primary', (string) $container->getAlias(TibberServiceInterface::class . ' $primary'));

        self::assertTrue($container->hasAlias(TibberServiceInterface::class . ' $secondaryAccountTibberService'));
        self::assertTrue($container->hasAlias(TibberServiceInterface::class . ' $secondaryAccount'));
        self::assertSame('tibber_api.service.secondary_account', (string) $container->getAlias(TibberServiceInterface::class . ' $secondaryAccountTibberService'));

        // Verify registry locator arguments
        $clientRegistryArgs = $clientRegistryDef->getArguments();
        self::assertInstanceOf(Reference::class, $clientRegistryArgs[0]);
        self::assertSame(['primary', 'secondary_account'], $clientRegistryArgs[2]);

        $serviceRegistryArgs = $serviceRegistryDef->getArguments();
        self::assertInstanceOf(Reference::class, $serviceRegistryArgs[0]);
        self::assertSame(['primary', 'secondary_account'], $serviceRegistryArgs[2]);
    }

    public function testProcessThrowsExceptionOnDuplicateClientAccount(): void
    {
        $container = new ContainerBuilder();

        $client1 = new Definition(TibberClient::class, ['token1']);
        $client1->addTag(TibberRegistryPass::CLIENT_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.client.primary', $client1);

        $client2 = new Definition(TibberClient::class, ['token2']);
        $client2->addTag(TibberRegistryPass::CLIENT_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.client.primary_dup', $client2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate Tibber client registered for account "primary"');

        (new TibberRegistryPass())->process($container);
    }

    public function testProcessThrowsExceptionOnDuplicateServiceAccount(): void
    {
        $container = new ContainerBuilder();

        $service1 = new Definition(TibberService::class);
        $service1->addTag(TibberRegistryPass::SERVICE_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.service.primary', $service1);

        $service2 = new Definition(TibberService::class);
        $service2->addTag(TibberRegistryPass::SERVICE_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.service.primary_dup', $service2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate Tibber service registered for account "primary"');

        (new TibberRegistryPass())->process($container);
    }

    public function testProcessThrowsExceptionWhenDefaultAccountNotRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('tibber_api.default_account', 'non_existent');

        $service1 = new Definition(TibberService::class);
        $service1->addTag(TibberRegistryPass::SERVICE_TAG, ['account' => 'primary']);
        $container->setDefinition('tibber_api.service.primary', $service1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The configured default Tibber account "non_existent" does not match any registered service accounts');

        (new TibberRegistryPass())->process($container);
    }

    public function testProcessHandlesHyphenatedAccountNames(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('tibber_api.default_account', 'my-meter');

        $client = new Definition(TibberClient::class, ['token1']);
        $client->addTag(TibberRegistryPass::CLIENT_TAG, ['account' => 'my-meter']);
        $container->setDefinition('tibber_api.client.my-meter', $client);

        $service = new Definition(TibberService::class);
        $service->addTag(TibberRegistryPass::SERVICE_TAG, ['account' => 'my-meter']);
        $container->setDefinition('tibber_api.service.my-meter', $service);

        (new TibberRegistryPass())->process($container);

        self::assertTrue($container->hasAlias(TibberServiceInterface::class . ' $myMeter'));
        self::assertTrue($container->hasAlias(TibberServiceInterface::class . ' $myMeterTibberService'));
        self::assertSame('tibber_api.service.my-meter', (string) $container->getAlias(TibberServiceInterface::class . ' $myMeter'));
    }
}
