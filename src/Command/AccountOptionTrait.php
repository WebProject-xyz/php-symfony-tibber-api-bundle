<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Client\TibberClient;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Exception\TibberApiException;
use WebProject\TibberApiClient\Exception\TibberAuthenticationException;
use WebProject\TibberApiClient\Exception\TibberRateLimitException;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function sprintf;
use function trim;

trait AccountOptionTrait
{
    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (TibberAuthenticationException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error(sprintf('Tibber Authentication Failed: %s', $e->getMessage()));

            return Command::FAILURE;
        } catch (TibberRateLimitException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error(sprintf('Tibber Rate Limit Exceeded: %s', $e->getMessage()));

            return Command::FAILURE;
        } catch (TibberApiException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error(sprintf('Tibber API Error: %s', $e->getMessage()));

            return Command::FAILURE;
        } catch (InvalidArgumentException|RuntimeException $e) {
            $io = new SymfonyStyle($input, $output);
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

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

    protected function resolveClient(
        InputInterface $input,
        TibberClientRegistryInterface $registry,
    ): TibberClientInterface {
        /** @var string|null $account */
        $account = $input->getOption('account');
        if (null !== $account && '' !== trim($account)) {
            return $registry->getClient($account);
        }

        /** @var string|null $token */
        $token = $input->getOption('token');
        if (null !== $token && '' !== trim($token)) {
            return new TibberClient($token);
        }

        return $registry->getClient();
    }
}
