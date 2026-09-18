<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
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
use function json_encode;
use function mkdir;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

#[AsCommand(
    name: 'tibber:schema:dump',
    description: 'Fetch and dump the full GraphQL schema introspection from the Tibber API',
)]
class SchemaDumpCommand extends BaseSchemaDumpCommand
{
    use AccountOptionTrait;
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
        $this->addAccountOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $client = $this->resolveClient($input, $this->clientRegistry);

        $outputPath = (string) $input->getOption('output');

        $io->title('Fetching Tibber GraphQL Schema Introspection');
        $io->text(sprintf('Target: %s', TibberClient::DEFAULT_ENDPOINT));

        $data = $client->query(self::INTROSPECTION_QUERY);

        $json = json_encode(['data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $dir = dirname($outputPath);
        if (!is_dir($dir) && '.' !== $dir && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $io->error(sprintf('Failed to create directory "%s" for schema dump.', $dir));

            return self::FAILURE;
        }

        if (false === @file_put_contents($outputPath, $json)) {
            $io->error(sprintf('Failed to write schema introspection to "%s". Check file permissions.', $outputPath));

            return self::FAILURE;
        }

        $typeCount = isset($data['__schema']['types']) && is_array($data['__schema']['types']) ? count($data['__schema']['types']) : 0;

        $io->success(sprintf('Schema introspection successfully dumped to "%s" (%d types found).', $outputPath, $typeCount));

        return self::SUCCESS;
    }
}
