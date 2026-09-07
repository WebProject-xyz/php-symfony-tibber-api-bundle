<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Registry;

use WebProject\TibberApiClient\Client\TibberClientInterface;

interface TibberClientRegistryInterface
{
    /**
     * Retrieve a Tibber client by account name, or the default client if null.
     */
    public function getClient(?string $name = null): TibberClientInterface;

    /**
     * Determine if a client exists for the given account name.
     */
    public function hasClient(string $name): bool;

    /**
     * Return all configured client names.
     *
     * @return array<string>
     */
    public function getClientNames(): array;

    /**
     * Return the name of the default account.
     */
    public function getDefaultClientName(): string;
}
