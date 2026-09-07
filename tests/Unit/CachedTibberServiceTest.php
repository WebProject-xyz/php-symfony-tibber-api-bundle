<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Tests\Unit;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use WebProject\Symfony\TibberApiBundle\Service\CachedTibberService;
use WebProject\TibberApiClient\Model\ConsumptionNode;
use WebProject\TibberApiClient\Model\Enum\AppScreen;
use WebProject\TibberApiClient\Model\Enum\EnergyResolution;
use WebProject\TibberApiClient\Model\Enum\PriceResolution;
use WebProject\TibberApiClient\Model\Home;
use WebProject\TibberApiClient\Model\Price;
use WebProject\TibberApiClient\Model\PriceInfo;
use WebProject\TibberApiClient\Model\SendPushNotificationResult;
use WebProject\TibberApiClient\Model\UpdateHomeInput;
use WebProject\TibberApiClient\Model\Viewer;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

class CachedTibberServiceTest extends Unit
{
    public function testGetViewerCacheMissAndHit(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $viewer = new Viewer();

        // 1. Cache Miss
        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_viewer')
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getViewer')->willReturn($viewer);
        $item->expects(self::once())->method('set')->with($viewer);
        $item->expects(self::once())->method('expiresAfter')->with(86400);
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($viewer, $service->getViewer());
        self::assertSame($inner, $service->getInnerService());
    }

    public function testGetViewerCacheHit(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $viewer = new Viewer();

        $cache->expects(self::once())->method('getItem')->with('tibber_viewer')->willReturn($item);
        $item->expects(self::once())->method('isHit')->willReturn(true);
        $item->expects(self::once())->method('get')->willReturn($viewer);
        $inner->expects(self::never())->method('getViewer');

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($viewer, $service->getViewer());
    }

    public function testGetHomesCacheMiss(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $homes = [new Home(id: 'home-123')];

        $cache->expects(self::once())->method('getItem')->with('tibber_homes')->willReturn($item);
        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getHomes')->willReturn($homes);
        $item->expects(self::once())->method('set')->with($homes);
        $item->expects(self::once())->method('expiresAfter')->with(86400);
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($homes, $service->getHomes());
    }

    public function testGetHomeCacheMiss(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $home = new Home(id: 'home-123');

        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_home_' . md5('home-123'))
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getHome')->with('home-123')->willReturn($home);
        $item->expects(self::once())->method('set')->with($home);
        $item->expects(self::once())->method('expiresAfter')->with(86400);
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($home, $service->getHome('home-123'));
    }

    public function testGetPriceInfoCacheMiss(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $priceInfo = new PriceInfo();

        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_price_info_' . md5('home-123') . '_HOURLY_' . date('Y-m-d'))
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getPriceInfo')->with('home-123', PriceResolution::HOURLY)->willReturn($priceInfo);
        $item->expects(self::once())->method('set')->with($priceInfo);
        $item->expects(self::once())->method('expiresAfter')->with(300); // 300 because tomorrow is empty
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($priceInfo, $service->getPriceInfo('home-123', PriceResolution::HOURLY));
    }

    public function testGetCurrentPriceCalculatesIntervalRemainingTtl(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $price = new Price(total: 0.35, startsAt: new DateTimeImmutable());

        $intervalTimestamp = time() - (time() % 3600);
        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_current_price_' . md5('home-123') . '_HOURLY_' . $intervalTimestamp)
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getCurrentPrice')->with('home-123', PriceResolution::HOURLY)->willReturn($price);
        $item->expects(self::once())->method('set')->with($price);

        // Expect TTL to be <= 300 and >= 10
        $item->expects(self::once())
            ->method('expiresAfter')
            ->with(self::callback(static fn (int $ttl): bool => $ttl >= 10 && $ttl <= 300));
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($price, $service->getCurrentPrice('home-123'));
    }

    public function testGetTodaysPricesCacheMiss(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $prices = [new Price(total: 0.28)];

        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_today_prices_' . md5('home-123') . '_HOURLY_' . date('Y-m-d'))
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getTodaysPrices')->with('home-123', PriceResolution::HOURLY)->willReturn($prices);
        $item->expects(self::once())->method('set')->with($prices);
        $item->expects(self::once())->method('expiresAfter')->with(3600);
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($prices, $service->getTodaysPrices('home-123'));
    }

    public function testGetTomorrowsPricesUsesShortTtlWhenEmpty(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_tomorrow_prices_' . md5('home-123') . '_HOURLY_' . date('Y-m-d'))
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getTomorrowsPrices')->with('home-123', PriceResolution::HOURLY)->willReturn([]);
        $item->expects(self::once())->method('set')->with([]);
        // When empty, recheck after 300s
        $item->expects(self::once())->method('expiresAfter')->with(300);
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame([], $service->getTomorrowsPrices('home-123'));
    }

    public function testGetConsumptionCacheMiss(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $item  = $this->createMock(CacheItemInterface::class);

        $nodes = [new ConsumptionNode(consumption: 1.5)];

        $cache->expects(self::once())
            ->method('getItem')
            ->with('tibber_consumption_' . md5('home-123') . '_HOURLY_24')
            ->willReturn($item);

        $item->expects(self::once())->method('isHit')->willReturn(false);
        $inner->expects(self::once())->method('getConsumption')->with('home-123', EnergyResolution::HOURLY, 24)->willReturn($nodes);
        $item->expects(self::once())->method('set')->with($nodes);
        $item->expects(self::once())->method('expiresAfter')->with(3600);
        $cache->expects(self::once())->method('save')->with($item);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($nodes, $service->getConsumption('home-123', EnergyResolution::HOURLY, 24));
    }

    public function testSendPushNotificationIsPassedThrough(): void
    {
        $inner  = $this->createMock(TibberServiceInterface::class);
        $cache  = $this->createMock(CacheItemPoolInterface::class);
        $result = new SendPushNotificationResult(successful: true, pushedToNumberOfDevices: 1);

        $inner->expects(self::once())
            ->method('sendPushNotification')
            ->with('Title', 'Message', AppScreen::HOME)
            ->willReturn($result);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($result, $service->sendPushNotification('Title', 'Message', AppScreen::HOME));
    }

    public function testUpdateHomeInvalidatesCache(): void
    {
        $inner = $this->createMock(TibberServiceInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $input = new UpdateHomeInput(homeId: 'home-123', appNickname: 'Summer House');
        $home  = new Home(id: 'home-123', appNickname: 'Summer House');

        $inner->expects(self::once())
            ->method('updateHome')
            ->with($input)
            ->willReturn($home);

        $cache->expects(self::once())
            ->method('deleteItems')
            ->with(['tibber_home_' . md5('home-123'), 'tibber_homes']);

        $service = new CachedTibberService($inner, $cache);
        self::assertSame($home, $service->updateHome($input));
    }
}
