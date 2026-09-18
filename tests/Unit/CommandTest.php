<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use WebProject\Symfony\TibberApiBundle\Command\ConsumptionCommand;
use WebProject\Symfony\TibberApiBundle\Command\CurrentPriceCommand;
use WebProject\Symfony\TibberApiBundle\Command\PushNotificationCommand;
use WebProject\Symfony\TibberApiBundle\Command\SchemaDumpCommand;
use WebProject\Symfony\TibberApiBundle\Command\TodayPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\TomorrowPricesCommand;
use WebProject\Symfony\TibberApiBundle\Command\ViewerCommand;
use WebProject\Symfony\TibberApiBundle\Registry\TibberClientRegistryInterface;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;
use WebProject\TibberApiClient\Client\TibberClientInterface;
use WebProject\TibberApiClient\Exception\TibberApiException;
use WebProject\TibberApiClient\Exception\TibberAuthenticationException;
use WebProject\TibberApiClient\Exception\TibberRateLimitException;
use WebProject\TibberApiClient\Model\Address;
use WebProject\TibberApiClient\Model\ConsumptionNode;
use WebProject\TibberApiClient\Model\Enum\AppScreen;
use WebProject\TibberApiClient\Model\Enum\EnergyResolution;
use WebProject\TibberApiClient\Model\Enum\PriceLevel;
use WebProject\TibberApiClient\Model\Enum\PriceResolution;
use WebProject\TibberApiClient\Model\Home;
use WebProject\TibberApiClient\Model\Price;
use WebProject\TibberApiClient\Model\SendPushNotificationResult;
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

    public function testTomorrowPricesCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $price = new Price(
            total: 0.2814,
            energy: 0.1200,
            tax: 0.1614,
            startsAt: new DateTimeImmutable('2026-09-09 14:00:00'),
            currency: 'EUR',
            level: PriceLevel::CHEAP,
        );

        $registry->expects(self::once())
            ->method('getService')
            ->with('primary')
            ->willReturn($service);

        $service->expects(self::once())
            ->method('getTomorrowsPrices')
            ->with('home-42', PriceResolution::HOURLY)
            ->willReturn([$price]);

        $command = new TomorrowPricesCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--account' => 'primary',
            '--home-id' => 'home-42',
        ]);
        self::assertSame(Command::SUCCESS, $status);

        $display = $tester->getDisplay();
        self::assertStringContainsString("Tomorrow's Tibber Energy Prices", $display);
        self::assertStringContainsString('0.2814', $display);
        self::assertStringContainsString('CHEAP', $display);
    }

    public function testCurrentPriceCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $price = new Price(
            total: 0.3542,
            energy: 0.1800,
            tax: 0.1742,
            startsAt: new DateTimeImmutable('2026-09-08 15:00:00'),
            currency: 'EUR',
            level: PriceLevel::EXPENSIVE,
        );

        $registry->expects(self::once())
            ->method('getService')
            ->with('primary')
            ->willReturn($service);

        $service->expects(self::once())
            ->method('getCurrentPrice')
            ->with('home-42', PriceResolution::HOURLY)
            ->willReturn($price);

        $command = new CurrentPriceCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--account' => 'primary',
            '--home-id' => 'home-42',
        ]);
        self::assertSame(Command::SUCCESS, $status);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Current Tibber Energy Price', $display);
        self::assertStringContainsString('0.3542', $display);
        self::assertStringContainsString('EXPENSIVE', $display);
    }

    public function testConsumptionCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $node = new ConsumptionNode(
            from: new DateTimeImmutable('2026-09-08 10:00:00'),
            to: new DateTimeImmutable('2026-09-08 11:00:00'),
            cost: 0.75,
            unitPrice: 0.30,
            unitPriceVAT: 0.05,
            consumption: 2.5,
            consumptionUnit: 'kWh',
            currency: 'EUR',
        );

        $registry->expects(self::once())
            ->method('getService')
            ->with('primary')
            ->willReturn($service);

        $service->expects(self::once())
            ->method('getConsumption')
            ->with('home-42', EnergyResolution::HOURLY, 12)
            ->willReturn([$node]);

        $command = new ConsumptionCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--account'    => 'primary',
            '--home-id'    => 'home-42',
            '--limit'      => '12',
            '--resolution' => 'HOURLY',
        ]);
        self::assertSame(Command::SUCCESS, $status);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Tibber Consumption History', $display);
        self::assertStringContainsString('2.500', $display);
    }

    public function testPushNotificationCommandWithAccountOption(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $result = new SendPushNotificationResult(successful: true, pushedToNumberOfDevices: 2);

        $registry->expects(self::once())
            ->method('getService')
            ->with('secondary')
            ->willReturn($service);

        $service->expects(self::once())
            ->method('sendPushNotification')
            ->with('Alert Title', 'Alert Message Body', AppScreen::HOME)
            ->willReturn($result);

        $command = new PushNotificationCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([
            '--account' => 'secondary',
            'title'     => 'Alert Title',
            'message'   => 'Alert Message Body',
            '--screen'  => 'HOME',
        ]);
        self::assertSame(Command::SUCCESS, $status);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Push notification sent successfully to 2 device(s)', $display);
    }

    public function testViewerCommandHandlesAuthenticationExceptionGracefully(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $registry->expects(self::once())->method('getService')->willReturn($service);
        $service->expects(self::once())->method('getViewer')->willThrowException(
            new TibberAuthenticationException('Invalid access token', 401),
        );

        $command = new ViewerCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([]);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Tibber Authentication Failed', $tester->getDisplay());
    }

    public function testViewerCommandHandlesRateLimitExceptionGracefully(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $registry->expects(self::once())->method('getService')->willReturn($service);
        $service->expects(self::once())->method('getViewer')->willThrowException(
            new TibberRateLimitException('Too Many Requests', 429),
        );

        $command = new ViewerCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([]);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Tibber Rate Limit Exceeded', $tester->getDisplay());
    }

    public function testViewerCommandHandlesApiExceptionGracefully(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $registry->expects(self::once())->method('getService')->willReturn($service);
        $service->expects(self::once())->method('getViewer')->willThrowException(
            new TibberApiException('Connection refused', 500),
        );

        $command = new ViewerCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([]);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Tibber API Error', $tester->getDisplay());
    }

    public function testCommandHandlesUnknownAccountException(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);

        $registry->expects(self::once())
            ->method('getService')
            ->with('non_existent')
            ->willThrowException(new InvalidArgumentException('Tibber service for account "non_existent" not found.'));

        $command = new ViewerCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute(['--account' => 'non_existent']);
        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Tibber service for account "non_existent" not found.', $tester->getDisplay());
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

    public function testSchemaDumpCommandHandlesWriteFailure(): void
    {
        $registry = $this->createMock(TibberClientRegistryInterface::class);
        $client   = $this->createMock(TibberClientInterface::class);

        $registry->expects(self::once())
            ->method('getClient')
            ->willReturn($client);

        $client->expects(self::once())
            ->method('query')
            ->willReturn(['__schema' => ['types' => []]]);

        $command = new SchemaDumpCommand($registry);
        $tester  = new CommandTester($command);

        // Invalid directory that cannot be created / written
        $status = $tester->execute([
            '--output' => '/non_existent_root_dir_12345/schema.json',
        ]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Failed to create directory', $tester->getDisplay());
    }

    public function testViewerCommandFallsBackToDefaultAccount(): void
    {
        $registry = $this->createMock(TibberServiceRegistryInterface::class);
        $service  = $this->createMock(TibberServiceInterface::class);

        $registry->expects(self::once())
            ->method('getService')
            ->with(null)
            ->willReturn($service);

        $service->expects(self::once())
            ->method('getViewer')
            ->willReturn(new Viewer(name: 'Default User', homes: []));

        $command = new ViewerCommand($registry);
        $tester  = new CommandTester($command);

        $status = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $status);
    }
}
