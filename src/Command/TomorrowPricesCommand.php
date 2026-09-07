<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Command\TomorrowPricesCommand as BaseTomorrowPricesCommand;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

#[AsCommand(
    name: 'tibber:prices:tomorrow',
    description: "Fetch tomorrow's electricity prices for a Tibber home (available ~13:00 CET)",
)]
class TomorrowPricesCommand extends BaseTomorrowPricesCommand
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
