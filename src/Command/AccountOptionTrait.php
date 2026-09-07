<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function trim;

trait AccountOptionTrait
{
    protected function addAccountOption(): void
    {
        $this->addOption(
            'account',
            'a',
            InputOption::VALUE_REQUIRED,
            'Tibber account name configured in tibber_api.yaml (defaults to default_account)',
        );
    }

    protected function resolveService(
        InputInterface $input,
        TibberServiceRegistryInterface $registry,
    ): TibberServiceInterface {
        /** @var string|null $account */
        $account = $input->getOption('account');
        if (null !== $account && '' !== trim($account)) {
            return $registry->getService($account);
        }

        /** @var string|null $token */
        $token = $input->getOption('token');
        if (null !== $token && '' !== trim($token)) {
            return parent::getTibberService($input);
        }

        return $registry->getService();
    }
}
