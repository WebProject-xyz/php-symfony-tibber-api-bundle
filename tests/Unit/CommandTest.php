<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use WebProject\Symfony\TibberApiBundle\Command\SchemaDumpCommand;
use WebProject\Symfony\TibberApiBundle\Command\TodayPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\ViewerCommand;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Model\Address;
use WebProject\TibberApiClient\Model\Enum\PriceLevel;
use WebProject\TibberApiClient\Model\Enum\PriceResolution;
use WebProject\TibberApiClient\Model\Home;
use WebProject\TibberApiClient\Model\Price;
use WebProject\TibberApiClient\Model\Viewer;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function sys_get_temp_dir;
use function unlink;

class CommandTest extends Unit
{
    public function testViewerCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $viewer = new Viewer(
            login: 'ben@example.com',
            userId: 'user-42',
            name: 'Ben',
            homes: [
                new Home(
                    id: 'home-1',
                    timeZone: 'Europe/Berlin',
                    address: new Address(address1: 'Energy Str. 1', postalCode: '10115', city: 'Berlin'),
                ),
            ],
        );

        $registry->expects(self::once())
            ->method('getService')
            ->with('secondary')
            ->willReturn($service);

        $service->expects(self::once())
            ->method('getViewer')
            ->willReturn($viewer);

        $command = new ViewerCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute(['--account' => 'secondary']);
        self::assertSame(Command::SUCCESS, $status);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Tibber Account Information', $display);
        self::assertStringContainsString('Energy Str. 1', $display);
        self::assertStringContainsString('home-1', $display);
    }

    public function testTodayPricesCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $price = new Price(
            total: 0.3214,
            energy: 0.1500,
            tax: 0.1714,
            startsAt: new DateTimeImmutable('2026-09-08 12:00:00'),
            currency: 'EUR',
            level: PriceLevel::NORMAL,
        );

        $registry->expects(self::once())
            ->method('getService')
            ->with('primary')
            ->willReturn($service);

        $service->expects(self::once())
            ->method('getTodaysPrices')
            ->with('home-42', PriceResolution::HOURLY)
            ->willReturn([$price]);

        $command = new TodayPricesCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--account' => 'primary',
            '--home-id' => 'home-42',
        ]);
        self::assertSame(Command::SUCCESS, $status);

        $display = $tester->getDisplay();
        self::assertStringContainsString("Today's Tibber Energy Prices", $display);
        self::assertStringContainsString('0.3214', $display);
        self::assertStringContainsString('NORMAL', $display);
        self::assertStringContainsString('EUR', $display);
    }

    public function testSchemaDumpCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberClientRegistryInterface::class);
        $client   = $this->createMock(TibberClientInterface::class);

        $registry->expects(self::once())
            ->method('getClient')
            ->with('backup_account')
            ->willReturn($client);

        $client->expects(self::once())
            ->method('query')
            ->willReturn([
                '__schema' => [
                    'types' => [['name' => 'Query'], ['name' => 'Viewer']],
                ],
            ]);

        $tmpFile = sys_get_temp_dir() . '/test_tibber_schema.json';

        $command = new SchemaDumpCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--account' => 'backup_account',
            '--output'  => $tmpFile,
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Schema introspection successfully dumped', $tester->getDisplay());
        self::assertFileExists($tmpFile);

        unlink($tmpFile);
    }
}
