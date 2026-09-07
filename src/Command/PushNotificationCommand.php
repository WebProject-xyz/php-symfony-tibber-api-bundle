<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Command\PushNotificationCommand as BasePushNotificationCommand;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

#[AsCommand(
    name: 'tibber:push',
    description: 'Send a push notification to the authenticated user Tibber mobile app',
)]
class PushNotificationCommand extends BasePushNotificationCommand
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
