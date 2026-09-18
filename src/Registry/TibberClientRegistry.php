<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Registry;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use WebProject\TibberApiClient\Client\TibberClientInterface;

use function array_keys;
use function implode;
use function sprintf;

class TibberClientRegistry implements TibberClientRegistryInterface
{
    /**
     * @param array<string> $clientNames
     */
    public function __construct(
        private readonly ?ContainerInterface $locator,
        private readonly string $defaultClientName,
        private readonly array $clientNames = [],
    ) {
    }

    public function getClient(?string $name = null): TibberClientInterface
    {
        $target = $name ?? $this->defaultClientName;

        if (null === $this->locator || !$this->locator->has($target)) {
            throw new InvalidArgumentException(sprintf('Tibber client for account "%s" not found. Available accounts: "%s".', $target, implode('", "', $this->getClientNames())));
        }

        /** @var TibberClientInterface $client */
        $client = $this->locator->get($target);

        return $client;
    }

    public function hasClient(string $name): bool
    {
        return null !== $this->locator && $this->locator->has($name);
    }

    /**
     * @return array<string>
     */
    public function getClientNames(): array
    {
        if ([] !== $this->clientNames) {
            return $this->clientNames;
        }

        if (null !== $this->locator && method_exists($this->locator, 'getProvidedServices')) {
            /** @var array<string, string> $provided */
            $provided = $this->locator->getProvidedServices();

            return array_keys($provided);
        }

        return [$this->defaultClientName];
    }

    public function getDefaultClientName(): string
    {
        return $this->defaultClientName;
    }
}
