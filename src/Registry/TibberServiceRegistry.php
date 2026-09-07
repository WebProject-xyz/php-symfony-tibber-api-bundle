<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Registry;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function array_keys;
use function implode;
use function sprintf;

class TibberServiceRegistry implements TibberServiceRegistryInterface
{
    /**
     * @param array<string> $serviceNames
     */
    public function __construct(
        private readonly ContainerInterface $locator,
        private readonly string $defaultServiceName,
        private readonly array $serviceNames = [],
    ) {
    }

    public function getService(?string $name = null): TibberServiceInterface
    {
        $target = $name ?? $this->defaultServiceName;

        if (!$this->locator->has($target)) {
            throw new InvalidArgumentException(sprintf('Tibber service for account "%s" not found. Available accounts: "%s".', $target, implode('", "', $this->getServiceNames())));
        }

        /** @var TibberServiceInterface $service */
        $service = $this->locator->get($target);

        return $service;
    }

    public function hasService(string $name): bool
    {
        return $this->locator->has($name);
    }

    /**
     * @return array<string>
     */
    public function getServiceNames(): array
    {
        if ([] !== $this->serviceNames) {
            return $this->serviceNames;
        }

        if (method_exists($this->locator, 'getProvidedServices')) {
            /** @var array<string, string> $provided */
            $provided = $this->locator->getProvidedServices();

            return array_keys($provided);
        }

        return [$this->defaultServiceName];
    }

    public function getDefaultServiceName(): string
    {
        return $this->defaultServiceName;
    }
}
