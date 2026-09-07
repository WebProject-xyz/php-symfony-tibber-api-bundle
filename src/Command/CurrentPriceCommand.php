<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Command\CurrentPriceCommand as BaseCurrentPriceCommand;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

#[AsCommand(
    name: 'tibber:prices:current',
    description: 'Fetch the current electricity price for a Tibber home',
)]
class CurrentPriceCommand extends BaseCurrentPriceCommand
{
    use AccountOptionTrait;

    public function __construct(
        private readonly TibberServiceRegistryInterface $serviceRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addAccountOption();
    }

    protected function getTibberService(InputInterface $input): TibberServiceInterface
    {
        return $this->resolveService($input, $this->serviceRegistry);
    }
}
