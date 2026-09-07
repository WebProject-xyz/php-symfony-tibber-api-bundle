<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Registry;

use WebProject\TibberApiClient\Service\TibberServiceInterface;

interface TibberServiceRegistryInterface
{
    /**
     * Retrieve a Tibber service by account name, or the default service if null.
     */
    public function getService(?string $name = null): TibberServiceInterface;

    /**
     * Determine if a service exists for the given account name.
     */
    public function hasService(string $name): bool;

    /**
     * Return all configured service account names.
     *
     * @return array<string>
     */
    public function getServiceNames(): array;

    /**
     * Return the name of the default account.
     */
    public function getDefaultServiceName(): string;
}
