<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit\Registry;

use Codeception\Test\Unit;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistry;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistry;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

class RegistryTest extends Unit
{
    public function testClientRegistryResolvesDefaultAndNamedClients(): void
    {
        $defaultClient   = $this->createMock(TibberClientInterface::class);
        $secondaryClient = $this->createMock(TibberClientInterface::class);

        $locator = new ServiceLocator([
            'default'   => static fn (): TibberClientInterface => $defaultClient,
            'secondary' => static fn (): TibberClientInterface => $secondaryClient,
        ]);

        $registry = new TibberClientRegistry($locator, 'default', ['default', 'secondary']);

        self::assertSame('default', $registry->getDefaultClientName());
        self::assertTrue($registry->hasClient('default'));
        self::assertTrue($registry->hasClient('secondary'));
        self::assertFalse($registry->hasClient('unknown'));
        self::assertSame(['default', 'secondary'], $registry->getClientNames());

        self::assertSame($defaultClient, $registry->getClient());
        self::assertSame($defaultClient, $registry->getClient('default'));
        self::assertSame($secondaryClient, $registry->getClient('secondary'));
    }

    public function testClientRegistryThrowsExceptionOnUnknownAccount(): void
    {
        $locator  = new ServiceLocator([]);
        $registry = new TibberClientRegistry($locator, 'default', ['default']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tibber client for account "non_existent" not found.');

        $registry->getClient('non_existent');
    }

    public function testServiceRegistryResolvesDefaultAndNamedServices(): void
    {
        $defaultService   = $this->createMock(TibberServiceInterface::class);
        $secondaryService = $this->createMock(TibberServiceInterface::class);

        $locator = new ServiceLocator([
            'default'   => static fn (): TibberServiceInterface => $defaultService,
            'secondary' => static fn (): TibberServiceInterface => $secondaryService,
        ]);

        $registry = new TibberServiceRegistry($locator, 'default', ['default', 'secondary']);

        self::assertSame('default', $registry->getDefaultServiceName());
        self::assertTrue($registry->hasService('default'));
        self::assertTrue($registry->hasService('secondary'));
        self::assertFalse($registry->hasService('unknown'));
        self::assertSame(['default', 'secondary'], $registry->getServiceNames());

        self::assertSame($defaultService, $registry->getService());
        self::assertSame($defaultService, $registry->getService('default'));
        self::assertSame($secondaryService, $registry->getService('secondary'));
    }

    public function testServiceRegistryThrowsExceptionOnUnknownAccount(): void
    {
        $locator  = new ServiceLocator([]);
        $registry = new TibberServiceRegistry($locator, 'default', ['default']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tibber service for account "unknown" not found.');

        $registry->getService('unknown');
    }
}
