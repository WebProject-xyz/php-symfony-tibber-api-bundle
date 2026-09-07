<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\TibberApiClient\Client\TibberClient;
use WebProject\TibberApiClient\Command\SchemaDumpCommand as BaseSchemaDumpCommand;

use function count;
use function dirname;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;
use function sprintf;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

#[AsCommand(
    name: 'tibber:schema:dump',
    description: 'Fetch and dump the full GraphQL schema introspection from the Tibber API',
)]
class SchemaDumpCommand extends BaseSchemaDumpCommand
{
    private const INTROSPECTION_QUERY = <<<'GRAPHQL'
        query IntrospectionQuery {
          __schema {
            queryType { name }
            mutationType { name }
            subscriptionType { name }
            types {
              kind
              name
              description
              fields(includeDeprecated: true) {
                name
                description
                args {
                  name
                  description
                  type { ...TypeRef }
                  defaultValue
                }
                type { ...TypeRef }
                isDeprecated
                deprecationReason
              }
              inputFields {
                name
                description
                type { ...TypeRef }
                defaultValue
              }
              interfaces { ...TypeRef }
              enumValues(includeDeprecated: true) {
                name
                description
                isDeprecated
                deprecationReason
              }
              possibleTypes { ...TypeRef }
            }
            directives {
              name
              description
              locations
              args {
                name
                description
                type { ...TypeRef }
                defaultValue
              }
            }
          }
        }

        fragment TypeRef on __Type {
          kind
          name
          ofType {
            kind
            name
            ofType {
              kind
              name
              ofType {
                kind
                name
                ofType {
                  kind
                  name
                }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private readonly TibberClientRegistryInterface $clientRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'account',
            'a',
            InputOption::VALUE_REQUIRED,
            'Tibber account name configured in tibber_api.yaml (defaults to default_account)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $account */
        $account = $input->getOption('account');

        /** @var string|null $token */
        $token = $input->getOption('token');

        if (is_string($account) && '' !== trim($account)) {
            $client = $this->clientRegistry->getClient($account);
        } elseif (is_string($token) && '' !== trim($token)) {
            $client = new TibberClient($token);
        } else {
            $client = $this->clientRegistry->getClient();
        }

        $outputPath = (string) $input->getOption('output');

        $io->title('Fetching Tibber GraphQL Schema Introspection');
        $io->text(sprintf('Target: %s', TibberClient::DEFAULT_ENDPOINT));

        $data = $client->query(self::INTROSPECTION_QUERY);

        $json = json_encode(['data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $dir = dirname($outputPath);
        if (!is_dir($dir) && '.' !== $dir) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($outputPath, $json);

        $typeCount = isset($data['__schema']['types']) && is_array($data['__schema']['types']) ? count($data['__schema']['types']) : 0;

        $io->success(sprintf('Schema introspection successfully dumped to "%s" (%d types found).', $outputPath, $typeCount));

        return self::SUCCESS;
    }
}
