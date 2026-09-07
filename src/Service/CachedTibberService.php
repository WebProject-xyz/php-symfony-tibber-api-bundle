<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
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

use function max;
use function md5;
use function min;
use function time;

class CachedTibberService implements TibberServiceInterface
{
    public const DEFAULT_TTL_VIEWER          = 86400; // 24 hours
    public const DEFAULT_TTL_HOMES           = 86400; // 24 hours
    public const DEFAULT_TTL_HOME            = 86400; // 24 hours
    public const DEFAULT_TTL_PRICE_INFO      = 1800;  // 30 minutes
    public const DEFAULT_TTL_TODAY_PRICES    = 3600;  // 1 hour
    public const DEFAULT_TTL_TOMORROW_PRICES = 14400; // 4 hours
    public const DEFAULT_TTL_CURRENT_PRICE   = 300;   // 5 minutes
    public const DEFAULT_TTL_CONSUMPTION     = 3600;  // 1 hour

    /**
     * @param array<string, int> $ttlConfig
     */
    public function __construct(
        private readonly TibberServiceInterface $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly array $ttlConfig = [],
        private readonly string $cachePrefix = 'tibber_',
    ) {
    }

    public function getViewer(): Viewer
    {
        $key  = $this->cachePrefix . 'viewer';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var Viewer $viewer */
            $viewer = $item->get();

            return $viewer;
        }

        $viewer = $this->inner->getViewer();
        $item->set($viewer);
        $item->expiresAfter($this->ttlConfig['viewer'] ?? self::DEFAULT_TTL_VIEWER);
        $this->cache->save($item);

        return $viewer;
    }

    /**
     * @return array<Home>
     */
    public function getHomes(): array
    {
        $key  = $this->cachePrefix . 'homes';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<Home> $homes */
            $homes = $item->get();

            return $homes;
        }

        $homes = $this->inner->getHomes();
        $item->set($homes);
        $item->expiresAfter($this->ttlConfig['homes'] ?? self::DEFAULT_TTL_HOMES);
        $this->cache->save($item);

        return $homes;
    }

    public function getHome(string $homeId): ?Home
    {
        $key  = $this->cachePrefix . 'home_' . md5($homeId);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var Home|null $home */
            $home = $item->get();

            return $home;
        }

        $home = $this->inner->getHome($homeId);
        $item->set($home);
        $item->expiresAfter($this->ttlConfig['home'] ?? self::DEFAULT_TTL_HOME);
        $this->cache->save($item);

        return $home;
    }

    public function getPriceInfo(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): ?PriceInfo
    {
        $key  = $this->cachePrefix . 'price_info_' . md5($homeId) . '_' . $resolution->value . '_' . date('Y-m-d');
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var PriceInfo|null $priceInfo */
            $priceInfo = $item->get();

            return $priceInfo;
        }

        $priceInfo = $this->inner->getPriceInfo($homeId, $resolution);
        $item->set($priceInfo);

        // If tomorrow's prices are not yet included, recheck after 5 minutes
        $ttl = (null !== $priceInfo && [] === $priceInfo->tomorrow)
            ? 300
            : ($this->ttlConfig['price_info'] ?? self::DEFAULT_TTL_PRICE_INFO);

        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $priceInfo;
    }

    public function getCurrentPrice(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): ?Price
    {
        $intervalSeconds   = PriceResolution::QUARTER_HOURLY === $resolution ? 900 : 3600;
        $intervalTimestamp = time() - (time() % $intervalSeconds);
        $key               = $this->cachePrefix . 'current_price_' . md5($homeId) . '_' . $resolution->value . '_' . $intervalTimestamp;
        $item              = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var Price|null $price */
            $price = $item->get();

            return $price;
        }

        $price = $this->inner->getCurrentPrice($homeId, $resolution);
        $item->set($price);

        // Expire gracefully at the end of the current pricing interval (15 min or 60 min)
        $configuredTtl    = $this->ttlConfig['current_price'] ?? self::DEFAULT_TTL_CURRENT_PRICE;
        $secondsRemaining = $intervalSeconds - (time() % $intervalSeconds);
        $effectiveTtl     = max(10, min($configuredTtl, $secondsRemaining));

        $item->expiresAfter($effectiveTtl);
        $this->cache->save($item);

        return $price;
    }

    /**
     * @return array<Price>
     */
    public function getTodaysPrices(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): array
    {
        $key  = $this->cachePrefix . 'today_prices_' . md5($homeId) . '_' . $resolution->value . '_' . date('Y-m-d');
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<Price> $prices */
            $prices = $item->get();

            return $prices;
        }

        $prices = $this->inner->getTodaysPrices($homeId, $resolution);
        $item->set($prices);
        $item->expiresAfter($this->ttlConfig['today_prices'] ?? self::DEFAULT_TTL_TODAY_PRICES);
        $this->cache->save($item);

        return $prices;
    }

    /**
     * @return array<Price>
     */
    public function getTomorrowsPrices(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): array
    {
        $key  = $this->cachePrefix . 'tomorrow_prices_' . md5($homeId) . '_' . $resolution->value . '_' . date('Y-m-d');
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<Price> $prices */
            $prices = $item->get();

            return $prices;
        }

        $prices = $this->inner->getTomorrowsPrices($homeId, $resolution);
        $item->set($prices);

        // If tomorrow's prices are not yet published, recheck after a short interval (e.g. 5 minutes)
        $ttl = [] === $prices
            ? 300
            : ($this->ttlConfig['tomorrow_prices'] ?? self::DEFAULT_TTL_TOMORROW_PRICES);

        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $prices;
    }

    /**
     * @return array<ConsumptionNode>
     */
    public function getConsumption(string $homeId, EnergyResolution $resolution, int $lastCount): array
    {
        $key  = $this->cachePrefix . 'consumption_' . md5($homeId) . '_' . $resolution->value . '_' . $lastCount;
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<ConsumptionNode> $consumption */
            $consumption = $item->get();

            return $consumption;
        }

        $consumption = $this->inner->getConsumption($homeId, $resolution, $lastCount);
        $item->set($consumption);
        $item->expiresAfter($this->ttlConfig['consumption'] ?? self::DEFAULT_TTL_CONSUMPTION);
        $this->cache->save($item);

        return $consumption;
    }

    public function sendPushNotification(string $title, string $message, ?AppScreen $screen = null): SendPushNotificationResult
    {
        return $this->inner->sendPushNotification($title, $message, $screen);
    }

    public function updateHome(UpdateHomeInput $input): ?Home
    {
        $home = $this->inner->updateHome($input);

        // Invalidate cached home and homes list
        $this->cache->deleteItems([
            $this->cachePrefix . 'home_' . md5($input->homeId),
            $this->cachePrefix . 'homes',
        ]);

        return $home;
    }

    public function getInnerService(): TibberServiceInterface
    {
        return $this->inner;
    }
}
